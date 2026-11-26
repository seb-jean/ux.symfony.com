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

use App\Model\CropBox;
use App\Model\CropDemoPhoto;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The photos a visitor uploaded to the Upload, Crop & Serve demo, in their order, with their crops.
 *
 * The session keeps the list: the name of each photo and its crop. The first
 * photo is the main one. The files themselves are on the disk, until
 * App\Service\CropDemoPhotos deletes them.
 */
final class CropDemoGallery
{
    public const MAX_PHOTOS = 5;

    private const SESSION_KEY = 'crop_demo_gallery';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly CropDemoPhotos $photos,
    ) {
    }

    /**
     * @return list<CropDemoPhoto> the photos still on the server, the main one first
     */
    public function all(): array
    {
        $entries = $this->entries();
        // A photo past its lifetime leaves the gallery too
        $kept = array_filter($entries, $this->photos->has(...), \ARRAY_FILTER_USE_KEY);
        if (\count($kept) !== \count($entries)) {
            $this->save($kept);
        }

        return array_map($this->photo(...), array_keys($kept), $kept);
    }

    public function find(string $name): ?CropDemoPhoto
    {
        $entries = $this->entries();

        return \array_key_exists($name, $entries) && $this->photos->has($name) ? $this->photo($name, $entries[$name]) : null;
    }

    public function isFull(): bool
    {
        return \count($this->all()) >= self::MAX_PHOTOS;
    }

    public function add(string $name): void
    {
        $this->save([...$this->entries(), $name => null]);
    }

    public function crop(string $name, CropBox $crop): void
    {
        $entries = $this->entries();
        if (\array_key_exists($name, $entries)) {
            $entries[$name] = (string) $crop;
            $this->save($entries);
        }
    }

    /**
     * Puts these photos first, in this order, and the others after them: the first photo is the main one.
     *
     * @param list<string> $names
     */
    public function reorder(array $names): void
    {
        $entries = $this->entries();
        $first = [];
        foreach ($names as $name) {
            if (\array_key_exists($name, $entries)) {
                $first[$name] = $entries[$name];
            }
        }

        $this->save($first + $entries);
    }

    public function remove(string $name): void
    {
        $entries = $this->entries();
        if (\array_key_exists($name, $entries)) {
            unset($entries[$name]);
            $this->save($entries);
            $this->photos->delete($name);
        }
    }

    /**
     * @return array<string, ?string> the crop of each photo, "x,y,width,height", or null for the center
     */
    private function entries(): array
    {
        $entries = $this->requestStack->getSession()->get(self::SESSION_KEY, []);

        return \is_array($entries) ? $entries : [];
    }

    /**
     * @param array<string, ?string> $entries
     */
    private function save(array $entries): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $entries);
    }

    private function photo(string $name, ?string $crop): CropDemoPhoto
    {
        [$width, $height] = $this->photos->getDimensions($name);
        $crop = null !== $crop ? CropBox::fromString($crop) : null;

        return new CropDemoPhoto(
            $name,
            $this->photos->getPublicPath($name),
            $width,
            $height,
            $crop?->fitsIn($width, $height) ? $crop : CropBox::centeredSquare($width, $height),
        );
    }
}
