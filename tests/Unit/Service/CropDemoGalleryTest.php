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

use App\Model\CropBox;
use App\Service\CropDemoGallery;
use App\Service\CropDemoPhotos;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

#[CoversClass(CropDemoGallery::class)]
final class CropDemoGalleryTest extends TestCase
{
    // A 2 x 1 PNG: a real image, wider than tall
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAYAAAD0In+KAAAAEElEQVR42mP8z8DwnwEIAA4SAf+yNPKgAAAAAElFTkSuQmCC';

    private string $directory;
    private MockClock $clock;
    private Session $session;
    private CropDemoGallery $gallery;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/crop-demo-gallery-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        $this->clock = new MockClock('2026-11-26 12:00:00');
        $this->session = new Session(new MockArraySessionStorage());
        $request = Request::create('/demos/image/crop');
        $request->setSession($this->session);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->gallery = new CropDemoGallery($requestStack, new CropDemoPhotos($this->directory, $this->clock));
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->directory.'/*') ?: []);
        rmdir($this->directory);
    }

    public function testAPhotoJoinsTheGalleryUncropped(): void
    {
        $this->gallery->add($name = $this->photo('a'), 'IMG_2048.png');

        [$photo] = $this->gallery->all();
        self::assertSame($name, $photo->name);
        self::assertSame('IMG_2048.png', $photo->originalName);
        self::assertSame('/uploads/crops/'.$name, $photo->path);
        self::assertSame([2, 1], [$photo->width, $photo->height]);
        self::assertNull($photo->crop);
    }

    public function testThePhotosKeepTheOrderOfTheirUpload(): void
    {
        $this->gallery->add($first = $this->photo('a'), 'first.png');
        $this->gallery->add($second = $this->photo('b'), 'second.png');
        $this->gallery->crop($first, new CropBox(0, 0, 1, 1));

        self::assertSame([$first, $second], array_map(static fn ($photo): string => $photo->name, $this->gallery->all()));
    }

    public function testAPhotoKeepsItsSelection(): void
    {
        $this->gallery->add($name = $this->photo('a'), 'IMG_2048.png');
        $this->gallery->crop($name, new CropBox(1, 0, 1, 1));

        self::assertEquals(new CropBox(1, 0, 1, 1), $this->gallery->find($name)?->crop);
    }

    public function testASelectionPastThePhotoIsNoSelection(): void
    {
        $this->gallery->add($name = $this->photo('a'), 'IMG_2048.png');
        $this->gallery->crop($name, new CropBox(1, 0, 2, 1));

        self::assertNull($this->gallery->find($name)?->crop);
    }

    public function testOnlyThePhotosOfTheGalleryAreFound(): void
    {
        $this->photo('a');

        self::assertNull($this->gallery->find(str_repeat('a', 32).'.png'), 'A photo of another visitor.');
        self::assertNull($this->gallery->find(''));
    }

    public function testAPhotoPastItsLifetimeLeavesTheGallery(): void
    {
        $this->gallery->add($name = $this->photo('a'), 'IMG_2048.png');
        $this->clock->sleep(CropDemoPhotos::LIFETIME);

        self::assertSame([], $this->gallery->all());
        self::assertNull($this->gallery->find($name));
        self::assertSame([], $this->session->get('crop_demo_gallery'), 'The session forgets it too.');
    }

    public function testRemovingAPhotoDeletesItsFile(): void
    {
        $this->gallery->add($name = $this->photo('a'), 'IMG_2048.png');

        $this->gallery->remove($name);

        self::assertSame([], $this->gallery->all());
        self::assertFileDoesNotExist($this->directory.'/'.$name);
    }

    public function testTheGalleryIsFullAtFourPhotos(): void
    {
        foreach (['a', 'b', 'c'] as $letter) {
            $this->gallery->add($this->photo($letter), $letter.'.png');
        }
        self::assertFalse($this->gallery->isFull());

        $this->gallery->add($this->photo('d'), 'd.png');
        self::assertTrue($this->gallery->isFull());
    }

    private function photo(string $letter): string
    {
        $name = str_repeat($letter, 32).'.png';
        file_put_contents($this->directory.'/'.$name, base64_decode(self::PNG));
        touch($this->directory.'/'.$name, $this->clock->now()->getTimestamp());

        return $name;
    }
}
