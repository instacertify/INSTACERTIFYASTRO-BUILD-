<?php

namespace App\Filament\Components;

use App\Support\StructuredData;
use Closure;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/** Lets editors add to, or replace, the structured data the website generates for a page. Stored as `schema_mode` and `schema`. */
class RichSnippetFields
{
    public static function make(): Fieldset
    {
        return Fieldset::make('Rich snippets (structured data)')
            ->columns(1)
            ->columnSpanFull()
            ->schema([
                Select::make('schema_mode')
                    ->label('Markup for Google')
                    ->placeholder('Automatic (recommended)')
                    ->options([
                        StructuredData::ADD => 'Automatic, plus my markup below',
                        StructuredData::REPLACE => 'Only my markup below (replaces the automatic markup)',
                    ])
                    ->live()
                    ->helperText('Automatic markup covers breadcrumbs, FAQs, articles, services, events and jobs from the page content. The company details are always included.'),
                CodeEditor::make('schema')
                    ->label('Custom JSON-LD')
                    ->language(Language::Json)
                    ->visible(fn (Get $get) => filled($get('schema_mode')))
                    ->required(fn (Get $get) => filled($get('schema_mode')))
                    ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail) {
                        if ($problem = StructuredData::problem($value)) {
                            $fail($problem);
                        }
                    }])
                    ->dehydrateStateUsing(fn (?string $state) => StructuredData::normalize($state))
                    ->helperText(new HtmlString(
                        'Paste one object or a list, e.g. <code>{"@type": "HowTo", "name": "…", "step": […]}</code>. '
                        .'A pasted &lt;script&gt; tag is removed automatically. Only mark up what visitors can see on the page; review stars are not allowed. '
                        .'After publishing, check the live page in <a href="https://search.google.com/test/rich-results" target="_blank" rel="noopener" class="underline">Google’s Rich Results Test</a>.'
                    )),
            ]);
    }
}
