<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Functional;

use App\Service\CropDemoGallery;
use App\Service\CropDemoPhotos;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Zenstruck\Browser\KernelBrowser as Browser;
use Zenstruck\Browser\Test\HasBrowser;

final class ImageCropDemoTest extends KernelTestCase
{
    use HasBrowser;

    private const URL = '/demos/image/crop';
    // The sample of the demo: stored 2000 x 1500 with the EXIF orientation 6, upright 1500 x 2000
    private const SAMPLE = __DIR__.'/../../public/uploads/gros-caillou-phone.jpg';
    // The square at the center of the upright sample, as the grid shows a photo nobody cropped
    private const CENTER = 'extract_0_250_1500_1500';
    // A square, with the float noise of Cropper.js
    private const SELECTION = ['x' => 149.9999, 'y' => 200.4, 'width' => 1199.9999, 'height' => 1200.0002, 'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1];

    /**
     * The first and the last line of every excerpt cut by line numbers:
     * a range that shifted shows half a statement, or nothing.
     */
    private const EXCERPTS = [
        'src/Form/CropDemoPhotoForm.php' => ["\$builder->add('photo', UploadType::class, [", ']);'],
        'src/Service/CropDemoPhotos.php' => ['public function save(UploadedFile $upload): string', '}'],
        'src/Form/CropDemoSelectionForm.php' => ["\$builder->add('crop', CropperType::class, [", ']);'],
        'templates/demos/image/_crop_dialog.html.twig' => ['<twig:Dialog:Content class="CropDemo_dialog sm:max-w-xl">', '</twig:Dialog:Content>'],
        'compose.yml' => ['ipx:', '- ./public:/srv/public:ro'],
    ];

    /** @var list<string> */
    private array $photosBefore;

    protected function setUp(): void
    {
        $this->photosBefore = self::photos();
    }

    protected function tearDown(): void
    {
        foreach (array_diff(self::photos(), $this->photosBefore) as $photo) {
            unlink($photo);
        }

        parent::tearDown();
    }

    public function testTheDemoStartsWithAnEmptyGrid(): void
    {
        $crawler = $this->browser()
            ->visit(self::URL)
            ->assertSuccessful()
            ->assertSeeIn('h1', 'Upload, Crop & Serve')
            ->crawler()
        ;

        self::assertSame(1, $crawler->filter('.eyebrows a[href$="/image"]')->count());
        self::assertStringEndsWith(self::URL, (string) $crawler->filter('link[rel="canonical"]')->attr('href'));
        foreach (['/upload', '/cropperjs', '/image'] as $package) {
            self::assertCount(1, $crawler->filter(\sprintf('.demo-introduction a[href="%s"]', $package)), 'The introduction links to '.$package);
        }

        // Every answer replaces the frame of the demo, and only it: no steps, no other page
        self::assertCount(1, $crawler->filter('turbo-frame#crop-demo:not([data-turbo-action]) .CropDemo'));
        self::assertCount(1, $crawler->filter('meta[name="turbo-cache-control"][content="no-cache"]'));

        // No photo yet: the field takes the first place, the main one, for one photo at a time
        self::assertSame('Your photos', $crawler->filter('.CropDemo_title')->text());
        self::assertCount(0, $crawler->filter('.CropDemo_photo'));
        self::assertCount(1, $crawler->filter('.CropDemo_grid > li:first-child.CropDemo_add .ux-upload--dropzone input[type="file"]:not([multiple])'));
        self::assertSame('Add a photo', $crawler->filter('.CropDemo_add .ux-upload__instruction')->text());
        self::assertStringContainsString('5 MB', $crawler->filter('.CropDemo_add .ux-upload__help')->text());
        // The other places open the same field
        self::assertCount(CropDemoGallery::MAX_PHOTOS - 1, $crawler->filter('.CropDemo_free label[for="crop_demo_photo_form_photo"]'));
        self::assertCount(0, $crawler->filter('dialog'));

        // Without JavaScript, a button sends the field; with it, the photo travels at once
        self::assertSame('crop-demo-upload', $crawler->selectButton('Add this photo')->attr('form'));
        // A photo dropped on another one: this form sends the new order
        self::assertCount(1, $crawler->filter('form[method="post"][action="'.self::URL.'"][data-crop-demo-target="order"] input[name="order"]'));

        // The sample is a real photo of the site: there is something to crop
        $sample = $crawler->filter('[data-controller="upload-samples"]');
        self::assertSame('photo', $sample->attr('data-upload-samples-kind-value'));
        self::assertSame('/uploads/gros-caillou-phone.jpg', $sample->attr('data-upload-samples-url-value'));
    }

    public function testTheDemoIsDiscoverable(): void
    {
        foreach (['/demos', '/image'] as $listing) {
            self::assertGreaterThanOrEqual(1, $this->browser()->visit($listing)->assertSuccessful()->crawler()->filter(\sprintf('a[href="%s"]', self::URL))->count(), $listing);
        }
        self::assertSame(1, $this->browser()->visit('/cropperjs')->assertSuccessful()->crawler()->filter(\sprintf('a[href="%s"]', self::URL))->count());

        self::assertStringContainsString(self::URL.'</loc>', $this->browser()->visit('/sitemap.xml')->content());
    }

    public function testAPhotoSentWithoutJavaScriptJoinsTheGridAndOpensTheDialog(): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);

        $this->assertTheDialogOpensOnTheNewPhoto($browser->crawler(), $name);
    }

    public function testAPhotoUploadedWithJavaScriptWaitsForItsForm(): void
    {
        $this->browser()
            ->visit(self::URL)
            ->use(function (KernelBrowser $client): void {
                $crawler = $client->getCrawler();
                $policy = $crawler->filter('.ux-upload')->attr('data-symfony--ux-upload--upload-policy-token-value');
                $form = $crawler->selectButton('Add this photo')->form();
                $path = sys_get_temp_dir().'/crop-demo-'.bin2hex(random_bytes(4)).'.jpg';
                copy(self::SAMPLE, $path);

                try {
                    // What the field does with JavaScript: the photo travels before the form
                    $client->request('POST', '/_ux/upload', [
                        'uploader' => CropDemoPhotos::UPLOADER,
                        'filename' => 'IMG_2048.jpg',
                        'fileSize' => filesize($path),
                        'mimeType' => 'image/jpeg',
                        'policyToken' => $policy,
                    ], ['file' => new UploadedFile($path, 'IMG_2048.jpg', 'image/jpeg', test: true)]);
                } finally {
                    unlink($path);
                }
                self::assertSame(200, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
                $reference = (string) $client->getResponse()->getContent();
                parse_str(json_decode($reference, true, flags: \JSON_THROW_ON_ERROR)['token'], $payload);
                // The other demos destroy a file the moment its upload completes: IPX needs this one
                self::assertFileExists(self::getContainer()->getParameter('app.upload_demo_dir').'/'.$payload['p']);

                $data = $form->getPhpValues();
                $data['crop_demo_photo_form']['photo'] = $reference;
                $crawler = $client->request('POST', $form->getUri(), $data);

                $added = array_values(array_diff(self::photos(), $this->photosBefore));
                self::assertCount(1, $added);
                $this->assertTheDialogOpensOnTheNewPhoto($crawler, basename($added[0]));
            })
        ;
    }

    public function testTheSquareComesBackToTheGrid(): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);

        $crawler = $this->save($browser, $name, self::SELECTION)
            ->assertSuccessful()
            // The same page, the dialog closed
            ->assertOn(self::URL)
            ->crawler()
        ;
        self::assertCount(0, $crawler->filter('dialog'));

        $photo = $crawler->filter('.CropDemo_photo');
        self::assertCount(1, $photo);

        $image = $photo->filter('img');
        self::assertSame(['480', '480'], [$image->attr('width'), $image->attr('height')]);
        // The main photo takes four places of the grid: the browser picks a wider candidate
        self::assertStringStartsWith('(min-width: 64rem) 24rem', (string) $image->attr('sizes'));
        $candidates = explode(', ', (string) $image->attr('srcset'));
        self::assertGreaterThan(1, \count($candidates));
        foreach ([(string) $image->attr('src'), ...$candidates] as $url) {
            // The rest of the site renders through Cloudinary: this demo through IPX
            self::assertMatchesRegularExpression('#^https://ux\.symfony\.com/_ipx/s_(\d+)x\1,[^/]*\bautoorient,extract_150_200_1200_1200\b[^/]*/uploads/crops/'.preg_quote($name, '#').'( \d+w)?$#', $url);
        }

        // Edit the photo: the dialog opens again, on the same square
        $crawler = $browser->visit(self::URL.'?edit='.$name)->assertSuccessful()->crawler();
        $this->assertTheDialogIsOpen($crawler, $name);
        self::assertSame(['x' => 150, 'y' => 200, 'width' => 1200, 'height' => 1200], $this->cropperOptions($crawler)['data']);
    }

    public function testASelectionThatIsNotSquareIsCutToItsSquare(): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);

        $this->save($browser, $name, ['x' => 100, 'y' => 100, 'width' => 1300, 'height' => 900])
            ->assertOn(self::URL)
            ->assertElementAttributeContains('.CropDemo_photo img', 'srcset', 'extract_100_100_900_900')
        ;
    }

    /**
     * @param array<string, int|null> $selection
     */
    #[TestWith([['x' => 400, 'y' => 0, 'width' => 1200, 'height' => 1200]], 'past the right edge')]
    #[TestWith([['x' => 0, 'y' => 0, 'width' => null, 'height' => null, 'rotate' => 0]], 'nothing selected')]
    public function testASelectionOutsideThePhotoIsRefused(array $selection): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);

        $this->save($browser, $name, $selection)
            ->assertStatus(422)
            ->assertSeeIn('dialog', 'Keep the square inside the photo.')
            ->assertSeeElement('[data-controller="dialog"][data-dialog-open-value] dialog')
            // The photo keeps the square at its center
            ->assertElementAttributeContains('.CropDemo_photo img', 'srcset', self::CENTER)
        ;
    }

    public function testTheCrossOfAPhotoDeletesIt(): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);
        $cross = $browser->crawler()->filter('.CropDemo_photo form[method="post"] button[name="remove"]');
        self::assertSame($name, $cross->attr('value'));
        self::assertSame(self::URL, $cross->closest('form')?->attr('action'));

        $browser
            ->post(self::URL, ['body' => ['remove' => $name]])
            ->assertOn(self::URL)
            ->assertNotSeeElement('.CropDemo_photo')
        ;
        self::assertFileDoesNotExist(self::getContainer()->getParameter('app.crop_demo_dir').'/'.$name);
    }

    public function testAPhotoDroppedOnAnotherOneTakesItsPlace(): void
    {
        $browser = $this->browser();
        [$first, $second, $third] = [$this->addPhoto($browser), $this->addPhoto($browser), $this->addPhoto($browser)];
        self::assertSame([$first, $second, $third], $this->grid($browser->visit(self::URL)->crawler()));

        // The third photo dropped on the first: the order of every name
        $crawler = $browser
            ->post(self::URL, ['body' => ['order' => implode(',', [$third, $first, $second])]])
            ->assertOn(self::URL)
            ->crawler()
        ;
        self::assertSame([$third, $first, $second], $this->grid($crawler));
        // The main photo takes the main place: its own alt and its wider candidates
        $main = $crawler->filter('.CropDemo_photo img')->first();
        self::assertSame('Photo 1', $main->attr('alt'));
        self::assertStringStartsWith('(min-width: 64rem) 24rem', (string) $main->attr('sizes'));
        self::assertStringStartsWith('(min-width: 64rem) 12rem', (string) $crawler->filter('.CropDemo_photo img')->eq(1)->attr('sizes'));
    }

    public function testMakeMainPhotoSavesTheCropAndPutsThePhotoFirst(): void
    {
        $browser = $this->browser();
        $first = $this->addPhoto($browser);
        $second = $this->addPhoto($browser);

        // The dialog of the main photo has nothing to make main
        self::assertCount(0, $browser->visit(self::URL.'?edit='.$first)->crawler()->filter('dialog button[name="main"]'));
        self::assertCount(1, $browser->visit(self::URL.'?edit='.$second)->crawler()->filter('dialog button[type="submit"][name="main"]'));

        $crawler = $browser
            ->post(self::URL.'?edit='.$second, ['body' => ['crop_demo_selection_form' => ['crop' => ['options' => json_encode(self::SELECTION)]], 'main' => '1']])
            ->assertOn(self::URL)
            ->crawler()
        ;
        self::assertSame([$second, $first], $this->grid($crawler));
        self::assertStringContainsString('extract_150_200_1200_1200', (string) $crawler->filter('.CropDemo_photo img')->first()->attr('srcset'));
    }

    public function testAVisitorOnlyReachesTheirOwnPhotos(): void
    {
        $name = $this->addPhoto($this->browser());

        // Another session knows the name of the photo, not its gallery
        $this->browser()
            ->visit(self::URL.'?edit='.$name)
            ->assertSuccessful()
            ->assertNotSeeElement('dialog')
            ->assertNotSeeElement('.CropDemo_photo')
            ->post(self::URL, ['body' => ['remove' => $name]])
            ->post(self::URL, ['body' => ['order' => $name]])
            ->assertNotSeeElement('.CropDemo_photo')
        ;
        self::assertFileExists(self::getContainer()->getParameter('app.crop_demo_dir').'/'.$name);
    }

    public function testTheSamplesTakeTurns(): void
    {
        $browser = $this->browser();
        $samples = [];
        foreach ([0, 1, 2, 3] as $count) {
            $samples[] = $browser->visit(self::URL)->crawler()->filter('[data-controller="upload-samples"]')->attr('data-upload-samples-url-value');
            if ($count < 3) {
                $this->addPhoto($browser);
            }
        }

        self::assertSame(['/uploads/gros-caillou-phone.jpg', '/uploads/col-de-saint-bonnet-phone.jpg', '/uploads/cherries-phone.jpg', '/uploads/gros-caillou-phone.jpg'], $samples);
        foreach (array_unique($samples) as $sample) {
            // A phone photo, light enough for the upload quota of a network
            self::assertFileExists($file = \dirname(__DIR__, 2).'/public'.$sample);
            self::assertLessThan(1024 * 1024, filesize($file));
        }
    }

    public function testTheDemoKeepsFivePhotosAtMost(): void
    {
        $browser = $this->browser();
        for ($i = 0; $i < CropDemoGallery::MAX_PHOTOS; ++$i) {
            $this->addPhoto($browser);
        }

        $crawler = $browser->visit(self::URL)->assertSuccessful()->crawler();
        self::assertCount(CropDemoGallery::MAX_PHOTOS, $crawler->filter('.CropDemo_photo'));
        self::assertCount(0, $crawler->filter('.CropDemo_add, .CropDemo_free'), 'No free place past the fifth photo.');
        self::assertCount(0, $crawler->filter('[data-controller="upload-samples"]'));

        // A sixth photo sent all the same is refused
        $path = sys_get_temp_dir().'/IMG_2048.jpg';
        copy(self::SAMPLE, $path);
        try {
            $browser->post(self::URL, ['files' => ['crop_demo_photo_form' => ['photo' => new UploadedFile($path, 'IMG_2048.jpg', 'image/jpeg', test: true)]]])
                ->assertStatus(422)
                ->assertSeeIn('.CropDemo_errors', 'Remove a photo first')
            ;
        } finally {
            @unlink($path);
        }
        self::assertCount(CropDemoGallery::MAX_PHOTOS, $browser->visit(self::URL)->crawler()->filter('.CropDemo_photo'));
    }

    public function testAPhotoPastItsLifetimeLeavesTheGrid(): void
    {
        $browser = $this->browser();
        $name = $this->addPhoto($browser);
        touch(self::getContainer()->getParameter('app.crop_demo_dir').'/'.$name, time() - CropDemoPhotos::LIFETIME - 60);

        $browser
            ->visit(self::URL.'?edit='.$name)
            ->assertSuccessful()
            ->assertNotSeeElement('dialog')
            ->assertNotSeeElement('.CropDemo_photo')
            ->assertSeeElement('.CropDemo_grid > li:first-child.CropDemo_add')
        ;
    }

    public function testEveryCodeExcerptShowsWholeStatements(): void
    {
        $crawler = $this->browser()->visit(self::URL)->assertSuccessful()->crawler();

        $excerpts = [];
        foreach ($crawler->filter('.UploadDemoStory .Terminal') as $terminal) {
            $terminal = new Crawler($terminal);
            // An excerpt cut by line numbers shows them in a gutter
            $lines = array_map(static fn (string $line): string => trim((string) preg_replace('/^\s*\d+/', '', $line)), explode("\n", $terminal->filter('.Terminal_content')->text(null, false)));
            $excerpts[] = [$terminal->filter('.Terminal_header')->text(), array_values(array_filter($lines, static fn (string $line): bool => '' !== $line))];
        }
        self::assertCount(7, $excerpts, 'Upload, keep, crop in a dialog, serve, host and run locally.');
        self::assertCount(7, $crawler->filter('.UploadDemoStory .code-description h2'), 'Every excerpt comes with a titled explanation.');

        foreach (self::EXCERPTS as $file => [$first, $last]) {
            $found = array_values(array_filter($excerpts, static fn (array $excerpt): bool => str_contains($excerpt[0], $file)));
            self::assertCount(1, $found, $file);
            self::assertSame($first, $found[0][1][0], $file);
            self::assertSame($last, end($found[0][1]), $file);
        }
    }

    /**
     * Sends the sample without JavaScript, and returns the name the photo has on the server.
     */
    private function addPhoto(Browser $browser): string
    {
        $path = sys_get_temp_dir().'/IMG_2048.jpg';
        copy(self::SAMPLE, $path);

        try {
            $browser
                ->visit(self::URL)
                ->attachFile('crop_demo_photo_form[photo]', $path)
                ->click('Add this photo')
                ->assertSuccessful()
            ;
        } finally {
            unlink($path);
        }

        // The same page, the dialog open on the new photo
        parse_str((string) parse_url($browser->client()->getRequest()->getUri(), \PHP_URL_QUERY), $query);
        self::assertIsString($query['edit'] ?? null);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $query['edit']);

        return $query['edit'];
    }

    /**
     * Sends a selection from the dialog of a photo, as the Save button does.
     *
     * @param array<string, mixed> $selection
     */
    private function save(Browser $browser, string $name, array $selection): Browser
    {
        return $browser->post(self::URL.'?edit='.$name, ['body' => ['crop_demo_selection_form' => ['crop' => ['options' => json_encode($selection)]]]]);
    }

    /**
     * @return list<string> the names of the photos, in the order of the grid
     */
    private function grid(Crawler $crawler): array
    {
        return $crawler->filter('.CropDemo_photo')->each(static fn (Crawler $photo): string => (string) $photo->attr('data-name'));
    }

    private function assertTheDialogOpensOnTheNewPhoto(Crawler $crawler, string $name): void
    {
        // The photo is moved where IPX reads it
        self::assertFileEquals(self::SAMPLE, self::getContainer()->getParameter('app.crop_demo_dir').'/'.$name);

        // The new photo in the grid: the main one, the square at its center, upright
        $photo = $crawler->filter('.CropDemo_photo');
        self::assertCount(1, $photo);
        self::assertSame($name, $photo->attr('data-name'));
        self::assertSame('true', $photo->attr('draggable'));
        self::assertSame('Main photo', $photo->filter('.CropDemo_badge')->text());
        self::assertSame(self::URL.'?edit='.$name, $photo->filter('a.CropDemo_edit')->attr('href'));
        self::assertSame('Edit photo 1', $photo->filter('a.CropDemo_edit')->text());
        self::assertSame('Remove photo 1', $photo->filter('button[name="remove"]')->attr('aria-label'));
        $image = $photo->filter('img');
        self::assertSame(['480', '480', 'Photo 1'], [$image->attr('width'), $image->attr('height'), $image->attr('alt')]);
        self::assertMatchesRegularExpression('#^https://ux\.symfony\.com/_ipx/s_480x480,[^/]*\bautoorient,'.self::CENTER.'\b[^/]*/uploads/crops/'.preg_quote($name, '#').'$#', (string) $image->attr('src'));
        // The field takes the next place, the others stay free
        self::assertCount(1, $crawler->filter('.CropDemo_photo + .CropDemo_add'));
        self::assertCount(CropDemoGallery::MAX_PHOTOS - 2, $crawler->filter('.CropDemo_free'));

        $this->assertTheDialogIsOpen($crawler, $name);
        // A new photo starts from the square at its center: the main one has nothing to make main
        self::assertSame(['x' => 0, 'y' => 250, 'width' => 1500, 'height' => 1500], $this->cropperOptions($crawler)['data']);
        self::assertCount(0, $crawler->filter('dialog button[name="main"]'));
    }

    private function assertTheDialogIsOpen(Crawler $crawler, string $name): void
    {
        // Open before Cropper.js measures it: the dialog controller opens it as it connects
        $dialog = $crawler->filter('[data-controller="dialog"][data-dialog-open-value] dialog');
        self::assertCount(1, $dialog);
        self::assertSame('Edit photo', $dialog->filter('h2')->text());
        self::assertSame(self::URL.'?edit='.$name, $dialog->filter('form')->attr('action'));
        self::assertSame('Save', $dialog->filter('button[type="submit"][autofocus]')->text());
        // The zoom waits for the cropper: without JavaScript, there is none
        self::assertCount(1, $dialog->filter('[data-crop-frame-target="zoom"][hidden] input[type="range"][data-crop-frame-target="slider"]'));
        // The cropper shows the photo as IPX turns it upright, under a square that stays still
        self::assertSame(
            'https://ux.symfony.com/_ipx/autoorient/uploads/crops/'.$name,
            $dialog->filter('[data-symfony--ux-cropperjs--cropper-public-url-value]')->attr('data-symfony--ux-cropperjs--cropper-public-url-value'),
        );
        $options = $this->cropperOptions($crawler);
        self::assertSame([1, 'move', false, false, 1], [$options['aspectRatio'], $options['dragMode'], $options['cropBoxMovable'], $options['cropBoxResizable'], $options['viewMode']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function cropperOptions(Crawler $crawler): array
    {
        $options = $crawler->filter('[data-symfony--ux-cropperjs--cropper-options-value]')->attr('data-symfony--ux-cropperjs--cropper-options-value');

        return json_decode((string) $options, true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private static function photos(): array
    {
        return glob(self::getContainer()->getParameter('app.crop_demo_dir').'/*') ?: [];
    }
}
