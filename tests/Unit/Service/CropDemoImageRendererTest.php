<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Service;

use App\Service\CropDemoImageRenderer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\Image\Bridge\Cloudinary\CloudinaryProvider;
use Symfony\UX\Image\Bridge\Ipx\IpxProvider;
use Symfony\UX\Image\Renderer\ImageRenderer;
use Symfony\UX\Image\Renderer\LayoutResolver;
use Symfony\UX\Image\Renderer\RenderOptions;

#[CoversClass(CropDemoImageRenderer::class)]
final class CropDemoImageRendererTest extends TestCase
{
    public function testThePageOfTheDemoRendersThroughIpx(): void
    {
        $image = $this->renderer('app_demo_image_crop')->render('/uploads/hero.jpg', 'Hero', new RenderOptions(width: 400, height: 300));

        self::assertStringStartsWith('https://ux.symfony.com/_ipx/', $image->imgAttributes['src']);
    }

    #[TestWith(['app_image'])]
    #[TestWith(['app_demo_upload_live_avatar'])]
    #[TestWith(['app_demo_image_crop_typo'])]
    public function testTheOtherPagesKeepTheProviderOfTheSite(string $route): void
    {
        $image = $this->renderer($route)->renderPicture('/uploads/hero.jpg', 'Hero', new RenderOptions(width: 400, height: 300));

        self::assertStringStartsWith('https://res.cloudinary.com/', $image->imgAttributes['src']);
    }

    public function testWithoutARequestTheProviderOfTheSiteRenders(): void
    {
        $image = $this->renderer(null)->render('/uploads/hero.jpg', 'Hero', new RenderOptions(width: 400, height: 300));

        self::assertStringStartsWith('https://res.cloudinary.com/', $image->imgAttributes['src']);
    }

    private function renderer(?string $route): CropDemoImageRenderer
    {
        $requestStack = new RequestStack();
        if (null !== $route) {
            $request = Request::create('/');
            $request->attributes->set('_route', $route);
            $requestStack->push($request);
        }

        return new CropDemoImageRenderer(
            new ImageRenderer(new CloudinaryProvider('ux-test', 'https://ux.symfony.com'), new LayoutResolver()),
            new ImageRenderer(new IpxProvider('ux.symfony.com', basePath: '/_ipx'), new LayoutResolver()),
            $requestStack,
        );
    }
}
