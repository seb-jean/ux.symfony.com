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
 * A photo of the Upload, Crop & Serve demo, as the grid shows it.
 */
final class CropDemoPhoto
{
    /**
     * @param string  $name   the file on the server: <upload id>.<extension>
     * @param string  $path   the public path UX Image gives to IPX
     * @param int     $width  of the photo turned upright
     * @param int     $height of the photo turned upright
     * @param CropBox $crop   the square the grid shows: the center of the photo, until the visitor moves it
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly int $width,
        public readonly int $height,
        public readonly CropBox $crop,
    ) {
    }
}
