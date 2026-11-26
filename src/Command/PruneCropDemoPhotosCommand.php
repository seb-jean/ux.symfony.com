<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Command;

use App\Service\CropDemoPhotos;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:crop-demo:prune', description: 'Deletes the photos of the Upload, Crop & Serve demo once their lifetime is over')]
final class PruneCropDemoPhotosCommand
{
    public function __construct(private readonly CropDemoPhotos $photos)
    {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->success(\sprintf('%d photo(s) deleted.', $this->photos->prune()));

        return 0;
    }
}
