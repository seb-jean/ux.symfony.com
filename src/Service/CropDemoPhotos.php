<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Service;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\Upload\Upload\UploadedFile;

/**
 * The photos of the Upload, Crop & Serve demo.
 *
 * A photo lives under public/, where IPX reads the originals, and is named after
 * the id of its upload. This server is public: it deletes each photo after
 * LIFETIME seconds, and a photo past that age is gone even before the cleanup.
 */
final class CropDemoPhotos
{
    public const LIFETIME = 600;
    public const UPLOADER = 'photos';
    public const PUBLIC_PATH = '/uploads/crops';
    public const NAME_PATTERN = '[0-9a-f]{32}\.(?:jpg|png|webp)';

    public function __construct(
        #[Autowire('%app.crop_demo_dir%')]
        private readonly string $directory,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Moves an upload where IPX reads it, and returns the name of the photo.
     */
    public function save(UploadedFile $upload): string
    {
        // jpg, png or webp: the uploader accepts no other type
        $name = $upload->getId().'.'.$upload->guessExtension();

        // A form sent twice: the photo is there already
        if (!is_file($this->directory.'/'.$name)) {
            $upload->move($this->directory, $name);
        }

        return $name;
    }

    public function has(string $name): bool
    {
        return 1 === preg_match('/^'.self::NAME_PATTERN.'$/', $name)
            && is_file($path = $this->getFilename($name))
            && filemtime($path) > $this->clock->now()->getTimestamp() - self::LIFETIME;
    }

    public function getFilename(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    /**
     * Deletes a photo the visitor removed, without waiting for the cleanup.
     */
    public function delete(string $name): void
    {
        if (1 === preg_match('/^'.self::NAME_PATTERN.'$/', $name)) {
            @unlink($this->getFilename($name));
        }
    }

    /**
     * The path the templates give to UX Image: the same for IPX and the browser.
     */
    public function getPublicPath(string $name): string
    {
        return self::PUBLIC_PATH.'/'.$name;
    }

    /**
     * The size of the photo turned upright, as IPX "autoorient" turns it.
     *
     * @return array{int, int}
     */
    public function getDimensions(string $name): array
    {
        $path = $this->getFilename($name);
        $size = getimagesize($path) ?: throw new \RuntimeException(\sprintf('The photo "%s" is not an image.', $name));

        // EXIF orientations 5 to 8 turn the photo a quarter: its width becomes its height
        $orientation = \IMAGETYPE_JPEG === $size[2] && \function_exists('exif_read_data') ? (@exif_read_data($path)['Orientation'] ?? 1) : 1;

        return $orientation >= 5 && $orientation <= 8 ? [$size[1], $size[0]] : [$size[0], $size[1]];
    }

    /**
     * Deletes the photos older than LIFETIME seconds, and returns how many.
     */
    public function prune(): int
    {
        if (!is_dir($this->directory)) {
            return 0;
        }

        $deleted = 0;
        $limit = $this->clock->now()->getTimestamp() - self::LIFETIME;
        foreach (new \FilesystemIterator($this->directory) as $file) {
            if ($file->isFile() && $file->getMTime() <= $limit && @unlink($file->getPathname())) {
                ++$deleted;
            }
        }

        return $deleted;
    }
}
