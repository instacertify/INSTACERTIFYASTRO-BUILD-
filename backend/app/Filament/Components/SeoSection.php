<?php

namespace App\Filament\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;

/** One SEO panel for every content type, stored in the `seo` JSON column. */
class SeoSection
{
    /** @param  bool  $reviewer  Only for page types whose template shows the "Reviewed by" line. */
    public static function make(bool $reviewer = true): Section
    {
        return Section::make('SEO & sharing')
            ->icon('heroicon-o-magnifying-glass')
            ->description('Leave blank to use the title, summary and rich snippets automatically.')
            ->columnSpanFull()
            ->collapsed()
            ->statePath('seo')
            ->columns(2)
            ->schema([
                TextInput::make('title')
                    ->label('Meta title')
                    ->maxLength(70)
                    ->live(onBlur: true)
                    ->helperText(fn (?string $state) => self::counter($state, 60)),
                TextInput::make('keyword')->label('Primary keyword')->live(onBlur: true),
                Textarea::make('description')
                    ->label('Meta description')
                    ->rows(2)
                    ->maxLength(320)
                    ->live(onBlur: true)
                    ->helperText(fn (?string $state) => filled($state) && mb_strlen($state) < 70
                        ? mb_strlen($state).'/155 characters — too short; aim for 70–155. Articles, services and author pages use their summary instead.'
                        : self::counter($state, 155))
                    ->columnSpanFull(),
                TextInput::make('og_title')->label('Discover & social headline')
                    ->helperText('Used by Google Discover and social cards. Defaults to the title — keep it plain and specific, no clickbait.'),
                TextInput::make('og_description')->label('Social description'),
                ImageUpload::make('image', 'social', maxWidth: 1200)->label('Social sharing image')->columnSpanFull()
                    ->imagePreviewHeight('120')
                    ->helperText('Shown when the page is shared on WhatsApp, LinkedIn, Facebook or X. 1200 × 630 px works best. Leave empty to use the page photo.'),
                TextInput::make('canonical')->label('Canonical URL')->url()->helperText('Only if this page duplicates another URL.'),
                Select::make('robots')->placeholder('Index, follow (default)')->options([
                    'noindex,follow' => 'Hide from Google, follow links',
                    'index,nofollow' => 'Show in Google, don’t follow links',
                    'noindex,nofollow' => 'Hide from Google completely',
                ]),
                Fieldset::make('Expert review (E-E-A-T)')
                    ->visible($reviewer)
                    ->columns(3)
                    ->schema([
                        TextInput::make('reviewer')->label('Reviewed by'),
                        TextInput::make('reviewer_credentials')->label('Credentials'),
                        DatePicker::make('reviewed_at')->label('Reviewed on'),
                    ]),
                RichSnippetFields::make(),
            ]);
    }

    private static function counter(?string $state, int $ideal): string
    {
        $length = mb_strlen((string) $state);

        return $length > $ideal ? "{$length} characters — Google may cut it after ~{$ideal}." : "{$length}/{$ideal} characters";
    }
}
