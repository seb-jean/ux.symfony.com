<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Model;

/**
 * A selection in a photo, in pixels of the photo turned upright.
 *
 * Cropper.js measures it, the URL keeps it, IPX cuts it: the photo itself never changes.
 */
final class CropBox
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
        public readonly int $width,
        public readonly int $height,
    ) {
        if ($x < 0 || $y < 0 || $width < 1 || $height < 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid crop box "%d,%d,%d,%d".', $x, $y, $width, $height));
        }
    }

    /**
     * The selection Cropper.js sends, in fractional pixels.
     *
     * Rounding down keeps the box inside the photo: x + width never exceeds the right edge.
     * The thousandth absorbs the float noise of Cropper.js, which reads 150 back as 149.9999.
     *
     * @param array<mixed> $data
     */
    public static function fromCropper(array $data): ?self
    {
        $values = [];
        foreach (['x', 'y', 'width', 'height'] as $key) {
            if (!\is_int($data[$key] ?? null) && !\is_float($data[$key] ?? null)) {
                return null;
            }
            $values[] = (int) floor(max(0, $data[$key]) + .001);
        }

        [$x, $y, $width, $height] = $values;

        return $width >= 1 && $height >= 1 ? new self($x, $y, $width, $height) : null;
    }

    /**
     * The selection as the URL keeps it: "x,y,width,height".
     */
    public static function fromString(string $value): ?self
    {
        if (!preg_match('/^(\d{1,5}),(\d{1,5}),(\d{1,5}),(\d{1,5})$/', $value, $matches)) {
            return null;
        }

        [, $x, $y, $width, $height] = array_map('intval', $matches);

        return $width >= 1 && $height >= 1 ? new self($x, $y, $width, $height) : null;
    }

    /**
     * The largest square at the center of a photo: its crop until the visitor moves it.
     */
    public static function centeredSquare(int $width, int $height): self
    {
        $side = min($width, $height);

        return new self(intdiv($width - $side, 2), intdiv($height - $side, 2), $side, $side);
    }

    public function fitsIn(int $width, int $height): bool
    {
        return $this->x + $this->width <= $width && $this->y + $this->height <= $height;
    }

    /**
     * The largest square of the selection, from the same top left corner.
     *
     * Cropper.js keeps a square selection square, give or take its rounding: the
     * server holds to it all the same.
     */
    public function toSquare(): self
    {
        $side = min($this->width, $this->height);

        return new self($this->x, $this->y, $side, $side);
    }

    /**
     * The arguments of the IPX "extract" operation: left, top, width and height.
     */
    public function toIpx(): string
    {
        return implode('_', [$this->x, $this->y, $this->width, $this->height]);
    }

    /**
     * The "data" option of Cropper.js: the selection the cropper starts from.
     *
     * @return array{x: int, y: int, width: int, height: int}
     */
    public function toCropperData(): array
    {
        return ['x' => $this->x, 'y' => $this->y, 'width' => $this->width, 'height' => $this->height];
    }

    public function __toString(): string
    {
        return implode(',', [$this->x, $this->y, $this->width, $this->height]);
    }
}
