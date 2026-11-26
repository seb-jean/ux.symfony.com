<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Service;

use App\Service\CropDemoPhotos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\UX\Upload\Storage\InMemoryStorage;
use Symfony\UX\Upload\Test\UploadedFileFactory;

#[CoversClass(CropDemoPhotos::class)]
final class CropDemoPhotosTest extends TestCase
{
    private const ID = '0123456789abcdef0123456789abcdef';
    private const UPLOADS = __DIR__.'/../../../public/uploads';

    private string $directory;
    private MockClock $clock;
    private CropDemoPhotos $photos;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/crop-demo-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        $this->clock = new MockClock('2026-11-26 12:00:00');
        $this->photos = new CropDemoPhotos($this->directory, $this->clock);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testAnUploadIsSavedUnderItsId(): void
    {
        $storage = new InMemoryStorage();
        $upload = new UploadedFileFactory(self::ID, CropDemoPhotos::UPLOADER, 'IMG_2048.jpg', 'image/jpeg', 5)->create($storage, 'photo');

        self::assertSame(self::ID.'.jpg', $this->photos->save($upload));
        self::assertStringEqualsFile($this->directory.'/'.self::ID.'.jpg', 'photo');
        self::assertSame('/uploads/crops/'.self::ID.'.jpg', $this->photos->getPublicPath(self::ID.'.jpg'));
    }

    public function testAFormSentTwiceFindsThePhotoThere(): void
    {
        $storage = new InMemoryStorage();
        $upload = new UploadedFileFactory(self::ID, CropDemoPhotos::UPLOADER, 'IMG_2048.png', 'image/png', 5)->create($storage, 'photo');
        $this->photos->save($upload);
        // The cleanup took the temporary upload since
        $upload->delete();

        self::assertSame(self::ID.'.png', $this->photos->save($upload));
    }

    public function testAPhotoIsGoneAfterItsLifetime(): void
    {
        $name = self::ID.'.jpg';
        touch($this->directory.'/'.$name, $this->clock->now()->getTimestamp());

        self::assertTrue($this->photos->has($name));

        $this->clock->sleep(CropDemoPhotos::LIFETIME);
        self::assertFalse($this->photos->has($name), 'An expired photo is gone before the cleanup.');
    }

    public function testOnlyTheNameOfAnUploadNamesAPhoto(): void
    {
        touch($this->directory.'/notes.jpg', $this->clock->now()->getTimestamp());

        self::assertFalse($this->photos->has('notes.jpg'));
        self::assertFalse($this->photos->has('../crops/'.self::ID.'.jpg'));
        self::assertFalse($this->photos->has(self::ID.'.jpg'), 'No such photo.');
    }

    public function testTheCleanupDeletesTheExpiredPhotosOnly(): void
    {
        $now = $this->clock->now()->getTimestamp();
        touch($this->directory.'/old.jpg', $now - CropDemoPhotos::LIFETIME);
        touch($this->directory.'/recent.jpg', $now - CropDemoPhotos::LIFETIME + 1);

        self::assertSame(1, $this->photos->prune());
        self::assertFileDoesNotExist($this->directory.'/old.jpg');
        self::assertFileExists($this->directory.'/recent.jpg');
    }

    public function testTheCleanupOfAMissingDirectoryDeletesNothing(): void
    {
        self::assertSame(0, new CropDemoPhotos($this->directory.'/missing', $this->clock)->prune());
    }

    #[RequiresPhpExtension('exif')]
    public function testThePhotoIsMeasuredUprightAsIpxTurnsIt(): void
    {
        // Stored 2000 x 1500 with the EXIF orientation 6, like a phone held upright
        copy(self::UPLOADS.'/gros-caillou-phone.jpg', $this->directory.'/'.self::ID.'.jpg');

        self::assertSame([1500, 2000], $this->photos->getDimensions(self::ID.'.jpg'));
    }

    public function testAPhotoWithoutOrientationKeepsItsSize(): void
    {
        copy(self::UPLOADS.'/col-de-saint-bonnet.jpg', $this->directory.'/'.self::ID.'.jpg');

        self::assertSame([4000, 3000], $this->photos->getDimensions(self::ID.'.jpg'));
    }
}
