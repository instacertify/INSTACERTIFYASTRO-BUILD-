<?php

namespace App\Support;

use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\ImageBlock;
use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\TableBlock;
use App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks\VideoBlock;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;

/**
 * Article and page text is stored as HTML in which the editor's blocks (video, image, table with a size) are empty
 * placeholders. This swaps each placeholder for the block's HTML and leaves every other tag as it was, so content
 * imported from the old site is not re-parsed.
 */
final class RichContent
{
    /** @var list<class-string<RichContentCustomBlock>> */
    public const BLOCKS = [VideoBlock::class, ImageBlock::class, TableBlock::class];

    /** Renders the blocks in an HTML string, or in every string of an array (a service's detail sections). */
    public static function render(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::render(...), $value);
        }
        if (! is_string($value) || ! str_contains($value, 'data-type="customBlock"')) {
            return $value;
        }

        return preg_replace_callback(
            '#<div\b(?=[^>]*\sdata-type="customBlock")[^>]*>\s*</div>#i',
            fn (array $match) => self::block($match[0]),
            $value,
        );
    }

    private static function block(string $tag): string
    {
        $id = self::attribute($tag, 'data-id');
        $config = json_decode(self::attribute($tag, 'data-config') ?? '', true);
        $block = collect(self::BLOCKS)->first(fn (string $class) => $class::getId() === $id);

        return $block && is_array($config) ? (string) $block::toHtml($config, []) : '';
    }

    private static function attribute(string $tag, string $name): ?string
    {
        return preg_match('/\s'.preg_quote($name, '/').'="([^"]*)"/i', $tag, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : null;
    }
}
