<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use App\Filament\Components\ImageUpload;
use App\Support\Images;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;

class ImageBlock extends SizedBlock
{
    public static function getId(): string
    {
        return 'image';
    }

    public static function getLabel(): string
    {
        return 'Image with size';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPhoto;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Upload an image and pick how big it shows in the text.')
            ->schema([
                ImageUpload::make('image', 'content/images', 1600)->label('Image')->required()->imagePreviewHeight('160'),
                TextInput::make('alt')->label('Image description (alt text)')->required()->maxLength(160)
                    ->helperText('What the image shows, for Google Images and screen readers.'),
                self::sizeField('medium'),
                self::alignField(),
                self::captionField(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'Image · '.self::SIZES[self::size($config)].(filled($config['alt'] ?? null) ? ' · '.$config['alt'] : '');
    }

    public static function toPreviewHtml(array $config): string
    {
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.image.preview', [
            'src' => self::src($config),
            'alt' => $config['alt'] ?? '',
            'caption' => $config['caption'] ?? null,
            'width' => self::previewWidth($config),
            'margin' => self::previewMargin($config),
        ])->render();
    }

    public static function toHtml(array $config, array $data): string
    {
        $path = self::path($config);
        if (! $path) {
            return '';
        }

        [$width, $height] = Cache::rememberForever('image-dimensions:'.$path, fn () => Images::dimensions($path)) ?? [null, null];

        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.image.index', [
            'src' => Images::url($path),
            'width' => $width,
            'height' => $height,
            'alt' => $config['alt'] ?? '',
            'caption' => $config['caption'] ?? null,
            'classes' => self::classes('image', $config),
        ])->render();
    }

    private static function path(array $config): ?string
    {
        $image = $config['image'] ?? null;
        if (is_array($image)) {
            $image = array_values($image)[0] ?? null;
        }

        return is_string($image) && filled($image) ? $image : null;
    }

    private static function src(array $config): ?string
    {
        return Images::url(self::path($config));
    }
}
