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

use App\Service\CropDemoPhotos;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\UX\Upload\Form\UploadType;
use Symfony\UX\Upload\Upload\UploadedFile;

/**
 * @extends AbstractType<array{photo?: UploadedFile}>
 */
final class CropDemoPhotoForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('photo', UploadType::class, [
            // JPEG, PNG or WebP, up to 5 MB: config/packages/ux_upload.yaml
            'uploader' => CropDemoPhotos::UPLOADER,
            'layout' => 'dropzone',
            'show_preview' => true,
            'help_text' => 'A JPEG, PNG or WebP photo, up to 5 MB',
            'constraints' => [new NotNull(message: 'Choose a photo first.')],
        ]);
    }
}
