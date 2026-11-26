<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Controller\Demo\Image;

use App\Form\CropDemoPhotoForm;
use App\Form\CropDemoSelectionForm;
use App\Model\CropBox;
use App\Model\CropDemoPhoto;
use App\Service\CropDemoGallery;
use App\Service\CropDemoPhotos;
use App\Service\DemoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Cropperjs\Factory\CropperInterface;
use Symfony\UX\Cropperjs\Model\Crop;
use Symfony\UX\Image\ImageUrlGenerator;
use Symfony\UX\Upload\Upload\UploadedFile;

/**
 * One page: a grid of photos, the main one first, a free place for the next one, the crop in a dialog.
 *
 * Every answer replaces the Turbo Frame of the demo: the page itself never changes.
 */
final class CropDemoController extends AbstractController
{
    public function __construct(
        private readonly CropDemoPhotos $photos,
        private readonly CropDemoGallery $gallery,
        private readonly DemoRepository $demoRepository,
        private readonly CropperInterface $cropper,
        // IPX: the other pages of this site keep Cloudinary (config/services.yaml)
        #[Autowire(service: 'app.crop_demo.image_url_generator')]
        private readonly ImageUrlGenerator $imageUrlGenerator,
    ) {
    }

    #[Route('/demos/image/crop', name: 'app_demo_image_crop')]
    public function __invoke(Request $request): Response
    {
        if ($request->isMethod('POST') && $request->request->has('remove')) {
            // The cross of a photo: it leaves the grid and the server
            $this->gallery->remove($request->request->getString('remove'));

            return $this->redirectToRoute('app_demo_image_crop');
        }

        if ($request->isMethod('POST') && $request->request->has('order')) {
            // A photo dragged to another place: the names, in their new order
            $this->gallery->reorder(explode(',', $request->request->getString('order')));

            return $this->redirectToRoute('app_demo_image_crop');
        }

        $photo = $this->gallery->find($request->query->getString('edit'));

        return null === $photo ? $this->upload($request) : $this->crop($photo, $request);
    }

    private function upload(Request $request): Response
    {
        $form = $this->createForm(CropDemoPhotoForm::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid() && $this->gallery->isFull()) {
            $form->addError(new FormError(\sprintf('Remove a photo first: the demo keeps %d at most.', CropDemoGallery::MAX_PHOTOS)));
        }
        if ($form->isSubmitted() && $form->isValid()) {
            $upload = $form->get('photo')->getData();
            // The original goes where IPX reads it, and never changes: public/uploads/crops/<upload id>.jpg
            $photo = $this->photos->save($upload);
            $this->gallery->add($photo);

            // The same page, with the dialog open on the new photo
            return $this->redirectToRoute('app_demo_image_crop', ['edit' => $photo]);
        }

        return $this->renderDemo($form, submitted: $form->isSubmitted());
    }

    private function crop(CropDemoPhoto $photo, Request $request): Response
    {
        $form = $this->createForm(CropDemoSelectionForm::class, [
            // The Crop object can also cut the photo in PHP: this demo only reads the selection
            'crop' => $this->cropper->createCrop($this->photos->getFilename($photo->name)),
        ], [
            'photo_url' => $this->imageUrlGenerator->generate($photo->path, operations: ['ipx' => ['autoorient' => true]]),
            'selection' => $photo->crop,
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // x, y, width and height, in pixels of the upright photo: a square
            $selected = json_decode($form->get('crop')->getData()->getOptions(), true);
            $crop = \is_array($selected) ? CropBox::fromCropper($selected)?->toSquare() : null;
            if (null !== $crop && $crop->fitsIn($photo->width, $photo->height)) {
                $this->gallery->crop($photo->name, $crop);
                // "Make main photo" saves the crop as well
                if ($request->request->has('main')) {
                    $this->gallery->reorder([$photo->name]);
                }

                // The same page: the dialog closes, and the grid shows the crop
                return $this->redirectToRoute('app_demo_image_crop');
            }

            $form->addError(new FormError('Keep the square inside the photo.'));
        }

        return $this->renderDemo($this->createForm(CropDemoPhotoForm::class), $photo, $form, $form->isSubmitted());
    }

    /**
     * @param FormInterface<array{photo?: UploadedFile}> $upload
     * @param FormInterface<array{crop: Crop}>|null      $selection
     */
    private function renderDemo(FormInterface $upload, ?CropDemoPhoto $editing = null, ?FormInterface $selection = null, bool $submitted = false): Response
    {
        $demo = $this->demoRepository->find('image', 'crop');

        return $this->render($demo->getTemplate(), [
            'demo' => $demo,
            'gallery' => $this->gallery->all(),
            'full' => $this->gallery->isFull(),
            'max' => CropDemoGallery::MAX_PHOTOS,
            'upload' => $upload,
            'editing' => $editing,
            'selection' => $selection,
            // The only Image demo: the next ones are the Upload demos about photos
            'related' => [
                $this->demoRepository->find('upload', 'live-avatar'),
                $this->demoRepository->find('upload', 'dropzone'),
            ],
        ], new Response(status: $submitted ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
