<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Components\FaqRepeater;
use App\Filament\Components\IconPicker;
use App\Filament\Components\ImageUpload;
use App\Filament\Components\SeoSection;
use App\Models\Service;
use App\Support\TestCategories;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Listing')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')->required()->maxLength(255),
                        TextInput::make('path')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('instacertify.com/')
                            ->helperText('Changing a live URL? Add a redirect so Google rankings carry over.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', basename((string) $state))),
                        Select::make('kind')->options(Service::KINDS)->required()->live(),
                        Select::make('region')->options(Service::REGIONS),
                        Select::make('group')->label('Category')->options(Service::GROUPS)->searchable(),
                        TextInput::make('slug')->required()->disabled()->dehydrated(),
                        Textarea::make('summary')->required()->maxLength(500)->rows(2)->columnSpanFull()
                            ->helperText('One or two sentences shown on cards and in search results.'),
                        CheckboxList::make('test_categories')->label('Industries on the Product testing page')
                            ->options(TestCategories::options())
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->columnSpanFull()
                            ->helperText('Tick every industry this test belongs to. It is listed under each one.')
                            ->visible(fn (Get $get) => $get('kind') === 'testing'),
                    ]),
                Section::make('Visibility')
                    ->columnSpan(1)
                    ->schema([
                        Toggle::make('is_published')->label('Live on website')->default(true),
                        Toggle::make('is_featured')->helperText('Shown on the homepage and in menus.'),
                        ImageUpload::make('icon', 'services/icons')->label('Logo / mark')->imageEditor(false),
                    ]),
                Tabs::make('Page content')
                    ->columnSpanFull()
                    ->statePath('detail')
                    ->tabs([
                        Tab::make('Hero')->icon('heroicon-o-sparkles')->columns(2)->schema([
                            TextInput::make('eyebrow')->placeholder('e.g. BIS CRS Certification'),
                            TextInput::make('headline')->placeholder('Defaults to the title'),
                            Textarea::make('lead')->rows(3)->columnSpanFull(),
                            Repeater::make('keyFacts')->label('Key facts')
                                ->schema([TextInput::make('label')->required(), TextInput::make('value')->required()])
                                ->columns(2)->maxItems(4)->defaultItems(0)->columnSpanFull(),
                            Repeater::make('includes')->label('What we handle')
                                ->simple(TextInput::make('value')->required())
                                ->defaultItems(0)->columnSpanFull(),
                        ]),
                        Tab::make('Overview')->icon('heroicon-o-document-text')->schema([
                            ImageUpload::inEditor(RichEditor::make('body')->label('Introduction'), 'services/inline')
                                ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['table', 'attachFiles', 'customBlocks'], ['undo', 'redo']]),
                            Repeater::make('sections')->label('Additional sections')
                                ->schema([
                                    TextInput::make('label')->placeholder('Small label, e.g. "Products covered"'),
                                    TextInput::make('heading')->required(),
                                    ImageUpload::inEditor(RichEditor::make('html')->label('Content')->required(), 'services/inline')
                                        ->toolbarButtons([['bold', 'italic', 'link'], ['h3'], ['bulletList', 'orderedList'], ['table', 'attachFiles', 'customBlocks'], ['undo', 'redo']]),
                                ])
                                ->itemLabel(fn (array $state) => $state['heading'] ?? null)
                                ->collapsed()->defaultItems(0),
                        ]),
                        Tab::make('Requirements')->icon('heroicon-o-clipboard-document-list')->schema([
                            Section::make('Why it is mandatory')->statePath('consequences')->compact()->schema([
                                TextInput::make('title'),
                                Textarea::make('text')->rows(2),
                                Repeater::make('items')->schema([TextInput::make('title')->required(), Textarea::make('text')->rows(2)])
                                    ->columns(2)->defaultItems(0),
                            ]),
                            Repeater::make('documents')->label('Documents required')
                                ->schema([
                                    TextInput::make('title')->required()->placeholder('e.g. Company documents'),
                                    Repeater::make('items')->simple(TextInput::make('value')->required())->defaultItems(1),
                                ])
                                ->itemLabel(fn (array $state) => $state['title'] ?? null)
                                ->collapsed()->defaultItems(0),
                            Section::make('Product table')->statePath('table')->compact()->collapsed()->schema([
                                TextInput::make('title'),
                                TextInput::make('note'),
                                TagsInput::make('columns')->placeholder('Add column'),
                                Repeater::make('rows')->schema([TagsInput::make('cells')->hiddenLabel()->placeholder('Cells in column order')])->defaultItems(0),
                            ]),
                        ]),
                        Tab::make('Process & cost')->icon('heroicon-o-banknotes')->schema([
                            Repeater::make('steps')->label('Process steps')
                                ->schema([
                                    TextInput::make('title')->required(),
                                    TextInput::make('duration')->placeholder('e.g. 3–5 days'),
                                    Textarea::make('text')->rows(2)->columnSpanFull(),
                                    IconPicker::make()->placeholder('Picked from the title')
                                        ->helperText('Optional. Left empty, the icon is picked from the title.'),
                                    ImageUpload::icon('iconImage', 'icons/services'),
                                ])
                                ->columns(2)->itemLabel(fn (array $state) => $state['title'] ?? null)->collapsed()->defaultItems(0),
                            Repeater::make('timeline')
                                ->schema([TextInput::make('phase')->required(), TextInput::make('duration')->required()])
                                ->columns(2)->defaultItems(0),
                            Repeater::make('costs')->label('Cost components')
                                ->schema([TextInput::make('title')->required(), TextInput::make('text')->label('Amount / note')->required()])
                                ->columns(2)->defaultItems(0),
                            Repeater::make('validity')->label('Validity & renewal')
                                ->simple(TextInput::make('value')->required())->defaultItems(0),
                            Section::make('Expert tip')->statePath('tip')->compact()->collapsed()->schema([
                                TextInput::make('title'),
                                Textarea::make('text')->rows(2),
                            ]),
                        ]),
                        Tab::make('Section titles')->icon('heroicon-o-bars-3-bottom-left')->schema([
                            Section::make('Custom headings')->description('Leave blank to use the default heading for each section.')
                                ->statePath('headings')->compact()->columns(2)->schema([
                                    TextInput::make('body')->label('Introduction')->placeholder('What is …?'),
                                    TextInput::make('documents')->placeholder('Documents required'),
                                    TextInput::make('products')->label('Product table tab')->placeholder('Products'),
                                    TextInput::make('steps')->label('Process')->placeholder('How it works'),
                                    TextInput::make('timeline')->placeholder('Timeline'),
                                    TextInput::make('costs')->placeholder('Cost'),
                                    TextInput::make('validity')->placeholder('Validity & renewal'),
                                    TextInput::make('faqs')->label('FAQs')->placeholder('Frequently asked questions'),
                                ]),
                            Section::make('Section intros')->description('A short paragraph shown under the section heading.')
                                ->statePath('intros')->compact()->schema([
                                    Textarea::make('documents')->rows(2),
                                    Textarea::make('steps')->label('Process')->rows(2),
                                    Textarea::make('timeline')->rows(2),
                                    Textarea::make('costs')->rows(3),
                                    Textarea::make('validity')->rows(2),
                                ]),
                        ]),
                        Tab::make('FAQs & related')->icon('heroicon-o-question-mark-circle')->schema([
                            FaqRepeater::make('faqs'),
                            Select::make('related')->label('Related services')
                                ->multiple()
                                ->searchable()
                                ->options(fn () => Service::orderBy('title')->pluck('title', 'path')),
                        ]),
                    ]),
                SeoSection::make(),
            ]);
    }

    /**
     * The website stores table rows as plain lists of cells; the editor edits each row as {cells: [...]}.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function tableRowsForEditor(array $data): array
    {
        if (is_array($data['detail']['table']['rows'] ?? null)) {
            $data['detail']['table']['rows'] = array_map(
                fn ($row) => ['cells' => array_values(array_filter((array) $row, 'is_string'))],
                $data['detail']['table']['rows'],
            );
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function tableRowsForWebsite(array $data): array
    {
        if (is_array($data['detail']['table']['rows'] ?? null)) {
            $data['detail']['table']['rows'] = array_values(array_map(
                fn ($row) => array_values((array) ($row['cells'] ?? [])),
                $data['detail']['table']['rows'],
            ));
        }

        return $data;
    }
}
