<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Model;

use App\Model\CropBox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CropBox::class)]
final class CropBoxTest extends TestCase
{
    public function testTheSelectionOfCropperJsIsRoundedDownInsideThePhoto(): void
    {
        // 0.6 + 1499.4 ends at the edge of a 1500-pixel photo: 0 + 1499 stays inside it
        $box = CropBox::fromCropper(['x' => 0.6, 'y' => 10.5, 'width' => 1499.4, 'height' => 99.9, 'rotate' => 0, 'scaleX' => 1, 'scaleY' => 1]);

        self::assertEquals(new CropBox(0, 10, 1499, 99), $box);
        self::assertTrue($box?->fitsIn(1500, 110));
    }

    public function testTheFloatNoiseOfCropperJsReadsAsTheIntegerItStandsFor(): void
    {
        self::assertEquals(new CropBox(150, 200, 1200, 1600), CropBox::fromCropper(['x' => 149.9999, 'y' => 199.99995, 'width' => 1199.9999, 'height' => 1600]));
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('provideIncompleteSelections')]
    public function testAnIncompleteSelectionIsNoSelection(array $data): void
    {
        self::assertNull(CropBox::fromCropper($data));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideIncompleteSelections(): iterable
    {
        // What the hidden field holds when the cropper never ran
        yield 'nothing selected' => [['x' => 0, 'y' => 0, 'width' => null, 'height' => null, 'rotate' => 0]];
        yield 'a missing value' => [['x' => 0, 'y' => 0, 'width' => 100]];
        yield 'a string' => [['x' => '0', 'y' => 0, 'width' => 100, 'height' => 100]];
        yield 'an empty box' => [['x' => 10, 'y' => 10, 'width' => 0.5, 'height' => 100]];
    }

    public function testTheUrlKeepsTheSelection(): void
    {
        $box = new CropBox(150, 200, 1200, 1600);

        self::assertSame('150,200,1200,1600', (string) $box);
        self::assertEquals($box, CropBox::fromString((string) $box));
    }

    #[DataProvider('provideInvalidStrings')]
    public function testAnInvalidUrlValueIsNoSelection(string $value): void
    {
        self::assertNull(CropBox::fromString($value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'three values' => ['1,2,3'];
        yield 'a negative value' => ['-1,0,10,10'];
        yield 'a decimal value' => ['0,0,10.5,10'];
        yield 'an empty box' => ['0,0,0,10'];
        yield 'too large' => ['0,0,100000,10'];
    }

    public function testABoxFitsInThePhotoOrNot(): void
    {
        $box = new CropBox(1000, 500, 500, 250);

        self::assertTrue($box->fitsIn(1500, 750));
        self::assertFalse($box->fitsIn(1499, 750));
        self::assertFalse($box->fitsIn(1500, 749));
    }

    public function testTheSquareOfABoxIsItsLargestFromTheSameCorner(): void
    {
        self::assertEquals(new CropBox(150, 200, 1200, 1200), new CropBox(150, 200, 1200, 1600)->toSquare());
        self::assertEquals(new CropBox(0, 10, 1080, 1080), new CropBox(0, 10, 1920, 1080)->toSquare());
        // A square stays as it is: a box that fits keeps fitting
        $square = new CropBox(300, 300, 900, 900);
        self::assertEquals($square, $square->toSquare());
    }

    public function testIpxAndCropperJsReadTheSameBox(): void
    {
        $box = new CropBox(150, 200, 1200, 1600);

        self::assertSame('150_200_1200_1600', $box->toIpx());
        self::assertSame(['x' => 150, 'y' => 200, 'width' => 1200, 'height' => 1600], $box->toCropperData());
    }

    public function testANegativeOrEmptyBoxIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CropBox(0, -1, 10, 10);
    }
}
