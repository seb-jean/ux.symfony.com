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

use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\Image\Renderer\ImageRendererInterface;
use Symfony\UX\Image\Renderer\RenderedImage;
use Symfony\UX\Image\Renderer\RenderOptions;

/**
 * Renders the images of the Upload, Crop & Serve demo through IPX.
 *
 * UX Image reads one DSN, and this site keeps Cloudinary for its other images.
 * On the pages of the demo only, the ux:image component renders through IPX,
 * from CROP_DEMO_IMAGE_DSN. An application that serves its images with IPX
 * sets UX_IMAGE_DSN, and needs none of this.
 */
#[AsDecorator('ux_image.renderer')]
final class CropDemoImageRenderer implements ImageRendererInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly ImageRendererInterface $renderer,
        #[Autowire(service: 'app.crop_demo.image_renderer')]
        private readonly ImageRendererInterface $cropDemoRenderer,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function render(string $src, string $alt, RenderOptions $options): RenderedImage
    {
        return $this->selectRenderer()->render($src, $alt, $options);
    }

    public function renderPicture(string $src, string $alt, RenderOptions $options): RenderedImage
    {
        return $this->selectRenderer()->renderPicture($src, $alt, $options);
    }

    private function selectRenderer(): ImageRendererInterface
    {
        $route = $this->requestStack->getMainRequest()?->attributes->getString('_route') ?? '';

        return 'app_demo_image_crop' === $route ? $this->cropDemoRenderer : $this->renderer;
    }
}
