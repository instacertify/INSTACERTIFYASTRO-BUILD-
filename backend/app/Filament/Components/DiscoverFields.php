<?php

namespace App\Filament\Components;

use App\Models\Event;
use App\Models\Post;
use App\Support\Discover;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;

/** Form fields, sidebar checklist and table helpers shared by articles and events for Google Discover. */
class DiscoverFields
{
    public static function summary(): Textarea
    {
        return Textarea::make('excerpt')
            ->label('Summary')
            ->rows(2)
            ->maxLength(500)
            ->live(onBlur: true)
            ->helperText(function (?string $state) {
                $length = mb_strlen(trim((string) $state));

                return 'Shown on listing cards, Google Discover and social previews, and used as the meta description. '
                    .($length < 50 || $length > 160 ? "{$length} characters — 50–160 works best." : "{$length}/160 characters");
            });
    }

    public static function featuredImage(string $directory): FileUpload
    {
        return ImageUpload::make('image', $directory)
            ->label('Featured image')
            ->helperText('At least 1200 px wide (1600 × 900 px ideal), landscape 16:9. Use a real photo of the subject — no logos or text-heavy graphics. Use the crop tool to frame it; images are converted to WebP automatically.')
            ->imageEditorAspectRatioOptions(['16:9', '4:3', '1:1', null]);
    }

    public static function imageAlt(): TextInput
    {
        return TextInput::make('image_alt')
            ->label('Image description (alt text)')
            ->requiredWith('image')
            ->helperText('Describe what the photo shows, for Google Images and screen readers.');
    }

    public static function checklist(): Section
    {
        return Section::make('Google Discover')
            ->icon('heroicon-o-sparkles')
            ->description('Updated each time you save.')
            ->schema([
                Html::make(fn (Post|Event|null $record) => Discover::checklist($record)),
            ]);
    }

    public static function column(): IconColumn
    {
        return IconColumn::make('discover_ready')
            ->label('Discover')
            ->boolean()
            ->tooltip(fn (Post|Event $record) => $record->discover_ready ? 'Meets all required Discover checks' : implode("\n", $record->discover_issues ?? []))
            ->toggleable();
    }

    public static function filter(): TernaryFilter
    {
        return TernaryFilter::make('discover_ready')
            ->label('Google Discover')
            ->trueLabel('Discover ready')
            ->falseLabel('Needs work');
    }
}
