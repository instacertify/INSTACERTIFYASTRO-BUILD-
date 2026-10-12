<?php

namespace App\Filament\Components;

use App\Support\Images;
use App\Support\RichContent;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImageUpload
{
    public const TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml', 'image/avif'];

    public static function make(string $name = 'image', string $directory = 'uploads', int $maxWidth = 1600): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->acceptedFileTypes(self::TYPES)
            ->imageEditor()
            ->disk(config('instacertify.media_disk'))
            ->directory($directory)
            ->visibility('public')
            ->maxSize(8 * 1024)
            ->helperText('PNG, JPG, WebP, GIF, AVIF or SVG, up to 8 MB. Photos are converted to WebP and resized automatically.')
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => self::store($file, $directory, $maxWidth));
    }

    /** A small uploaded icon that replaces the picked Google icon next to it. */
    public static function icon(string $name = 'iconImage', string $directory = 'icons'): FileUpload
    {
        return self::make($name, $directory, maxWidth: 256)
            ->label('Or upload your own icon')
            ->imageEditor(false)
            ->imagePreviewHeight('64')
            ->maxSize(1024)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => Images::storeIcon($file->get(), $directory.'/'.now()->format('Y/m'), $file->getClientOriginalName()))
            ->helperText('Optional. Replaces the icon above. Use a transparent PNG, WebP or SVG. Empty space around the icon is trimmed automatically.');
    }

    /** A client logo; empty margins are cut off so all logos in the strip are the same height. */
    public static function logo(string $name = 'image', string $directory = 'logos'): FileUpload
    {
        return self::make($name, $directory)
            ->imageEditor(false)
            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file) => Images::storeLogo($file->get(), $directory, $file->getClientOriginalName()))
            ->helperText('Transparent PNG or SVG works best. Empty space around the logo is trimmed automatically.');
    }

    /**
     * Lets editors drop images into the text (stored on the media disk as WebP, resizable by dragging) and insert a
     * YouTube video, an image or a table at a chosen size from the blocks panel.
     */
    public static function inEditor(RichEditor $editor, string $directory): RichEditor
    {
        return $editor
            ->customBlocks(RichContent::BLOCKS)
            ->resizableImages()
            ->fileAttachmentsDisk(config('instacertify.media_disk'))
            ->fileAttachmentsDirectory($directory)
            ->fileAttachmentsVisibility('public')
            ->fileAttachmentsAcceptedFileTypes(self::TYPES)
            ->fileAttachmentsMaxSize(8 * 1024)
            ->saveUploadedFileAttachmentUsing(fn (TemporaryUploadedFile $file) => self::store($file, $directory, 1600));
    }

    private static function store(TemporaryUploadedFile $file, string $directory, int $maxWidth): string
    {
        return Images::store($file->get(), $directory.'/'.now()->format('Y/m'), $file->getClientOriginalName(), $maxWidth);
    }
}
