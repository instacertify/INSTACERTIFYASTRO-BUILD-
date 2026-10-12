<?php

namespace App\Filament\Resources\Industries\Schemas;

use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Models\Service;
use App\Support\TestCategories;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class IndustryForm
{
    private const KINDS = ['certification' => 'Certifications', 'registration' => 'Registrations & licences', 'quality' => 'Quality'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make()
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('/industries/'),
                        Textarea::make('summary')->required()->maxLength(500)->rows(2)->columnSpanFull(),
                        Textarea::make('intro')->rows(4)->columnSpanFull(),
                        TagsInput::make('highlights')->columnSpanFull(),
                    ]),
                Section::make()
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_published')->default(true),
                        IconPicker::make(),
                        ImageUpload::icon('icon_image', 'icons/industries'),
                        TextInput::make('sort')->numeric()->default(0),
                        ImageUpload::make('image', 'industries')->label('Photo')
                            ->helperText('Shown on the industry tiles and behind the page title. Landscape, at least 1280 px wide. PNG, JPG or WebP; converted to WebP automatically.'),
                    ]),
                Section::make('Option 1: Certification')
                    ->description('The marks, approvals and registrations this industry needs. Shown first on the industry page.')
                    ->icon('heroicon-o-check-badge')
                    ->columnSpan(2)
                    ->schema([
                        Textarea::make('certification_text')->label('One-line description')->rows(2)->maxLength(300)
                            ->placeholder('e.g. BIS CRS registration, WPC and TEC approvals, filed and followed up for you.'),
                        Select::make('service_paths')
                            ->label('Certification services')
                            ->helperText('Shown in this order. Drag to reorder.')
                            ->multiple()->searchable()->reorderable()->required()->optionsLimit(500)
                            ->options(fn () => self::grouped(Service::where('kind', '!=', 'testing'))),
                    ]),
                Section::make('Option 2: Testing')
                    ->description('Lab tests for this industry. The page also links to the full test list for each product-testing industry picked here.')
                    ->icon('heroicon-o-beaker')
                    ->columnSpan(2)
                    ->schema([
                        Textarea::make('testing_text')->label('One-line description')->rows(2)->maxLength(300)
                            ->placeholder('e.g. Safety, EMC and RoHS tests at NABL-accredited labs.'),
                        Select::make('test_categories')
                            ->label('Product-testing industries')
                            ->multiple()->searchable()->required()
                            ->options(TestCategories::options()),
                        Select::make('testing_paths')
                            ->label('Testing services')
                            ->helperText('Shown in this order. Leave empty to show the first tests from the product-testing industries above.')
                            ->multiple()->searchable()->reorderable()->optionsLimit(500)
                            ->options(fn () => Service::where('kind', 'testing')->orderBy('sort')->pluck('title', 'path')->all()),
                    ]),
                SeoSection::make(reviewer: false),
            ]);
    }

    /** @return array<string, array<string, string>> */
    private static function grouped(Builder $query): array
    {
        return $query->orderBy('title')->get(['path', 'title', 'kind'])
            ->groupBy(fn (Service $s) => self::KINDS[$s->kind] ?? ucfirst((string) $s->kind))
            ->map(fn ($services) => $services->pluck('title', 'path')->all())
            ->all();
    }
}
