<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;

/**
 * A block placed in article or page text at a size the editor picks. The website styles the classes from
 * {@see self::classes()} (prose-body in the frontend's global.css).
 */
abstract class SizedBlock extends RichContentCustomBlock
{
    public const SIZES = ['small' => 'Small', 'medium' => 'Medium', 'full' => 'Full width'];

    public const SIZE_HINTS = ['small' => 'Half the text column', 'medium' => 'Two thirds of the text column', 'full' => 'The whole text column'];

    /** Share of the text column per size, for the preview in the editor. */
    public const SIZE_WIDTHS = ['small' => '50%', 'medium' => '66%', 'full' => '100%'];

    public const ALIGNMENTS = ['center' => 'Centre', 'left' => 'Left, text wraps', 'right' => 'Right, text wraps'];

    protected static function sizeField(string $default = 'full'): Radio
    {
        return Radio::make('size')->label('Size')->options(self::SIZES)->descriptions(self::SIZE_HINTS)
            ->default($default)->required()->inline()
            ->helperText('On phones every size fills the screen width.');
    }

    protected static function alignField(): ToggleButtons
    {
        return ToggleButtons::make('align')->label('Position')->options(self::ALIGNMENTS)->default('center')->inline()
            ->helperText('Left or right only applies to the small and medium sizes.');
    }

    protected static function captionField(): TextInput
    {
        return TextInput::make('caption')->label('Caption (optional)')->maxLength(200);
    }

    protected static function size(array $config): string
    {
        return array_key_exists($config['size'] ?? '', self::SIZES) ? $config['size'] : 'full';
    }

    /** The figure's classes, e.g. "embed embed-video size-medium align-center". */
    protected static function classes(string $kind, array $config): string
    {
        $size = self::size($config);
        $align = $size !== 'full' && array_key_exists($config['align'] ?? '', self::ALIGNMENTS) ? $config['align'] : 'center';

        return "embed embed-{$kind} size-{$size} align-{$align}";
    }

    protected static function previewWidth(array $config): string
    {
        return self::SIZE_WIDTHS[self::size($config)];
    }

    protected static function previewMargin(array $config): string
    {
        return match (self::size($config) === 'full' ? 'center' : ($config['align'] ?? 'center')) {
            'left' => '0 auto 0 0',
            'right' => '0 0 0 auto',
            default => '0 auto',
        };
    }
}
