<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Form\Extension;

use App\Service\CropDemoPhotos;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\UX\Upload\Form\UploadType;
use Symfony\UX\Upload\Upload\UploadedFile;

/**
 * Destroys the files a browser without JavaScript sends along with a form.
 *
 * With JavaScript, the file travels first and UploadDemoDestroyListener destroys
 * it. Without, the field stores it while the form is handled: it is destroyed
 * here, whether the form is valid or not. The field keeps its signed reference.
 *
 * Except the photo of the Upload, Crop & Serve demo: its controller moves it for
 * IPX, and App\Service\CropDemoPhotos deletes it after 10 minutes.
 */
final class UploadDemoDestroyExtension extends AbstractTypeExtension
{
    public static function getExtendedTypes(): iterable
    {
        return [UploadType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, static function (FormEvent $event): void {
            $data = $event->getForm()->getData();
            foreach (\is_array($data) ? $data : [$data] as $upload) {
                if ($upload instanceof UploadedFile && CropDemoPhotos::UPLOADER !== $upload->getUploaderName()) {
                    $upload->delete();
                }
            }
        });
    }
}
