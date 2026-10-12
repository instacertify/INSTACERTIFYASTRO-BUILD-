<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PicksSiteLinks;
use App\Models\Setting;
use App\Support\FooterMenu;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageFooter extends Page
{
    use PicksSiteLinks;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Footer';

    protected static ?string $title = 'Footer';

    protected static ?string $slug = 'footer';

    protected static ?int $navigationSort = -8;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageContent() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'The company blurb, phone, email, address and social icons come from Site settings. Drag the ↕ handle or use the arrows to reorder; click an item to open it.';
    }

    public function mount(): void
    {
        $this->form->fill(FooterMenu::settings());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Link columns')
                ->description('Shown side by side next to the company details. Three columns fit best.')
                ->schema([
                    Repeater::make('columns')->hiddenLabel()
                        ->schema([
                            TextInput::make('title')->label('Column heading')->required()->maxLength(40),
                            Toggle::make('hidden')->label('Hide from the website')->inline(false),
                            self::links('links', 'Add link', 15)->label('Links')->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->collapsed()
                        ->reorderableWithButtons()
                        ->defaultItems(0)
                        ->maxItems(4)
                        ->addActionLabel('Add column')
                        ->itemLabel(function (array $state) {
                            $count = count(array_filter($state['links'] ?? [], fn ($l) => empty($l['hidden'])));

                            return (($state['title'] ?? null) ?: 'New column').($count ? " · {$count} links" : '').(empty($state['hidden']) ? '' : ' (hidden)');
                        }),
                ]),
            Section::make('Newsletter box')
                ->statePath('newsletter')
                ->columns(2)
                ->schema([
                    Toggle::make('show')->label('Show the newsletter sign-up')->live()->columnSpanFull(),
                    TextInput::make('title')->label('Heading')->required()->maxLength(60)->visible(fn (Get $get) => $get('show')),
                    TextInput::make('text')->label('Short text')->required()->maxLength(160)->visible(fn (Get $get) => $get('show')),
                ]),
            Section::make('Bottom bar')
                ->description('The thin strip at the very bottom. The “Cookie settings” button is always shown, because visitors must be able to change their cookie choice.')
                ->schema([
                    self::links('portals', 'Add login link', 4)->label('Login links')
                        ->helperText('Shown with a sign-in icon, e.g. the ERP or client portal.'),
                    self::links('bottomLinks', 'Add link', 6)->label('Other links'),
                    TextInput::make('copyright')->label('Copyright line')->required()->maxLength(120)
                        ->helperText('{year} is replaced with the current year.'),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save footer')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $footer = [
            'columns' => array_values(array_map(fn (array $column) => [...$column, 'links' => array_values($column['links'] ?? [])], $state['columns'] ?? [])),
            'portals' => array_values($state['portals'] ?? []),
            'bottomLinks' => array_values($state['bottomLinks'] ?? []),
            'newsletter' => [...FooterMenu::settings()['newsletter'], ...($state['newsletter'] ?? [])],
            'copyright' => $state['copyright'],
        ];

        $setting = Setting::firstOrNew(['key' => 'site']);
        $setting->value = [...($setting->value ?? []), 'footer' => $footer];
        $setting->save();

        Notification::make()->success()->title('Footer saved')->body('The website shows the changes after its next build.')->send();
    }

    private static function links(string $name, string $add, int $max): Repeater
    {
        return Repeater::make($name)
            ->schema([
                ...self::linkFields(40),
                Toggle::make('hidden')->label('Hide from the website')->columnSpanFull(),
            ])
            ->columns(2)
            ->collapsed()
            ->reorderableWithButtons()
            ->defaultItems(0)
            ->maxItems($max)
            ->addActionLabel($add)
            ->itemLabel(fn (array $state) => (($state['label'] ?? null) ?: 'New link').(empty($state['hidden']) ? '' : ' (hidden)'));
    }
}
