<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig\Components\Toolkit;

use App\Enum\ToolkitKitId;
use App\Service\CommonMark\ConverterFactory;
use App\Service\Toolkit\ToolkitService;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Normalizer\SlugNormalizer;
use League\CommonMark\Parser\MarkdownParser;
use Symfony\UX\Toolkit\Recipe\Recipe;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class ComponentDoc
{
    public ToolkitKitId $kitId;
    public Recipe $component;

    private ?string $markdownContent = null;

    public function __construct(
        private readonly ToolkitService $toolkitService,
        private readonly ConverterFactory $converterFactory,
        private readonly SlugNormalizer $slugNormalizer = new SlugNormalizer(),
    ) {
    }

    public function getMarkdownContent(): string
    {
        return $this->markdownContent ??= $this->toolkitService->renderRecipeMarkdown($this->kitId, $this->component);
    }

    /**
     * @return list<array{level: int, title: string, id: string}>
     */
    public function getTocItems(): array
    {
        $items = [];
        $slugCounts = [];

        $parser = new MarkdownParser(($this->converterFactory)()->getEnvironment());
        $document = $parser->parse($this->getMarkdownContent());

        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading || !\in_array($node->getLevel(), [2, 3], true)) {
                continue;
            }

            $title = '';
            foreach ($node->iterator() as $inline) {
                if ($inline instanceof Text) {
                    $title .= $inline->getLiteral();
                }
            }
            $title = trim($title);

            $slug = $this->slugNormalizer->normalize($title);
            $slugCounts[$slug] ??= 0;
            $suffix = $slugCounts[$slug] > 0 ? '-'.$slugCounts[$slug] : '';
            ++$slugCounts[$slug];

            $items[] = [
                'level' => $node->getLevel(),
                'title' => $title,
                'id' => ConverterFactory::HEADING_ID_PREFIX.'-'.$slug.$suffix,
            ];
        }

        return $items;
    }
}
