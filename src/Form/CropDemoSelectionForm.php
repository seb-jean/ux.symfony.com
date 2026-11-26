<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form;

use App\Model\CropBox;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\Cropperjs\Form\CropperType;
use Symfony\UX\Cropperjs\Model\Crop;

/**
 * @extends AbstractType<array{crop: Crop}>
 */
final class CropDemoSelectionForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('crop', CropperType::class, [
            // The photo turned upright by IPX: the pixels IPX cuts
            'public_url' => $options['photo_url'],
            'cropper_options' => [
                // A square that stays still: the visitor drags and zooms the photo under it
                'aspectRatio' => 1,
                'dragMode' => 'move',
                'cropBoxMovable' => false,
                'cropBoxResizable' => false,
                'toggleDragModeOnDblclick' => false,
                // The photo always covers the whole square
                'viewMode' => 1,
                // The crop the grid shows: the center of the photo, until the visitor moves it
                'data' => $options['selection']->toCropperData(),
                // The photo and its frame: no checkerboard, no light square, no cross
                'background' => false,
                'highlight' => false,
                'center' => false,
                // IPX applied the EXIF orientation already
                'checkOrientation' => false,
                // In development, IPX runs on another origin
                'checkCrossOrigin' => false,
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('photo_url')
            ->setAllowedTypes('photo_url', 'string')
            ->setRequired('selection')
            ->setAllowedTypes('selection', CropBox::class)
        ;
    }
}
