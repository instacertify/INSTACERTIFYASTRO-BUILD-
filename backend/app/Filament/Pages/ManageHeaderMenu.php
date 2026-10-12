<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\PicksSiteLinks;
use App\Models\Setting;
use App\Support\HeaderMenu;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class ManageHeaderMenu extends Page
{
    use PicksSiteLinks;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Header menu';

    protected static ?string $title = 'Header menu';

    protected static ?string $slug = 'header-menu';

    protected static ?int $navigationSort = -9;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageContent() ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Top bar items open a dropdown; a dropdown link with its own links opens a flyout to the side. Drag the ↕ handle or use the arrows to reorder; click an item to open it.';
    }

    public function mount(): void
    {
        $this->form->fill(['nav' => HeaderMenu::items()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([self::level(1)]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Save menu')->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        $setting = Setting::firstOrNew(['key' => 'site']);
        $setting->value = [...($setting->value ?? []), 'nav' => array_values($this->form->getState()['nav'] ?? [])];
        $setting->save();

        Notification::make()->success()->title('Header menu saved')->body('The website shows the changes after its next build.')->send();
    }

    private static function level(int $level): Repeater
    {
        [$name, $add, $hint] = match ($level) {
            1 => ['nav', 'Add top bar item', 'Top bar'],
            2 => ['children', 'Add dropdown link', 'Dropdown'],
            default => ['children', 'Add flyout link', 'Flyout'],
        };

        return Repeater::make($name)
            ->label($level === 1 ? 'Top bar' : ($level === 2 ? 'Dropdown links' : 'Flyout links'))
            ->helperText(match ($level) {
                1 => 'Keep it to 5–7 items so the bar fits on laptops.',
                2 => 'Leave empty for a plain link with no dropdown.',
                default => 'Listed under the dropdown link in the menu panel. Lists of more than 15 links continue in the next column.',
            })
            ->schema([
                ...self::linkFields(),
                ...($level === 2 ? [
                    TextInput::make('description')->label('Short note under the link')->maxLength(80)
                        ->helperText('Shown in small grey text under this heading in the menu panel.')
                        ->placeholder('e.g. BIS, CRS, WPC, TEC')->columnSpanFull(),
                ] : []),
                Toggle::make('hidden')->label('Hide from the website')->columnSpanFull(),
                ...($level < HeaderMenu::LEVELS ? [self::level($level + 1)->columnSpanFull()] : []),
            ])
            ->columns(2)
            ->collapsed()
            ->cloneable()
            ->reorderableWithButtons()
            ->defaultItems(0)
            ->maxItems($level === 1 ? 8 : 30)
            ->addActionLabel($add)
            ->itemLabel(function (array $state) use ($hint) {
                $count = count(array_filter($state['children'] ?? [], fn ($c) => empty($c['hidden'])));
                $label = ($state['label'] ?? null) ?: "New {$hint} item";

                return $label.($count ? " · {$count} links" : '').(empty($state['hidden']) ? '' : ' (hidden)');
            });
    }
}
