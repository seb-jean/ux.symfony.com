<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig\Components;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent('Box')]
final class Box
{
    public string $color;

    public ?string $gradient = null;

    public string $titleTag = 'h3';

    public ?string $logoSize = null;

    public string $titleClass = '';
}
