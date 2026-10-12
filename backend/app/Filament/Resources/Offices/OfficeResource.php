<?php

namespace App\Filament\Resources\Offices;

use App\Filament\Resources\Offices\Pages\CreateOffice;
use App\Filament\Resources\Offices\Pages\EditOffice;
use App\Filament\Resources\Offices\Pages\ListOffices;
use App\Models\Office;
use App\Support\MapEmbed;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use UnitEnum;

class OfficeResource extends Resource
{
    protected static ?string $model = Office::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    private const STATES = [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
        'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
        'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana',
        'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
    ];

    private const COUNTRIES = [
        'IN' => 'India', 'AE' => 'United Arab Emirates', 'CN' => 'China', 'HK' => 'Hong Kong', 'TW' => 'Taiwan',
        'KR' => 'South Korea', 'JP' => 'Japan', 'SG' => 'Singapore', 'VN' => 'Vietnam', 'DE' => 'Germany',
        'GB' => 'United Kingdom', 'US' => 'United States',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(5)
            ->components([
                Group::make()->columnSpan(3)->schema([
                    Section::make('Location')->columns(2)->schema([
                        TextInput::make('name')->label('Office name')->required()->maxLength(80)->placeholder('Delhi NCR')
                            ->helperText('Shown as the card title on the contact page, usually the city or region.'),
                        Select::make('type')->options(Office::TYPES)->default('branch')->required()->selectablePlaceholder(false)
                            ->helperText('Making this the head office turns the previous head office into a branch.'),
                        Textarea::make('street')->label('Street address')->rows(2)->required()->columnSpanFull()->live(onBlur: true)
                            ->placeholder('A-34, Sector 63 A')
                            ->helperText('Building, street and area. City, state and PIN code go in the fields below.'),
                        TextInput::make('city')->maxLength(80)->placeholder('Noida')->live(onBlur: true),
                        TextInput::make('region')->label('State')->maxLength(80)->datalist(self::STATES)->placeholder('Uttar Pradesh')->live(onBlur: true),
                        TextInput::make('postal_code')->label('PIN / postal code')->maxLength(12)->placeholder('201301')->live(onBlur: true)
                            ->rule(fn (Get $get) => $get('country') === 'IN' ? 'regex:/^\d{6}$/' : 'string')
                            ->validationMessages(['regex' => 'An Indian PIN code has 6 digits.']),
                        Select::make('country')->options(self::COUNTRIES)->default('IN')->required()->searchable()->live(),
                        TextInput::make('landmark')->label('Landmark / how to find us')->maxLength(255)->columnSpanFull()
                            ->placeholder('Opposite Essar petrol pump · 5 min from Sector 62 metro'),
                    ]),
                    Section::make('Contact details')->columns(2)
                        ->description('Optional, shown on this office\'s card. The main phone and email from Site settings always appear at the top of the contact page.')
                        ->schema([
                            TextInput::make('phone')->label('Office phone')->tel()->maxLength(30)->placeholder('+91 99991 18039'),
                            TextInput::make('email')->label('Office email')->email()->maxLength(255)->placeholder('vadodara@instacertify.com'),
                            TextInput::make('contact_person')->label('Contact person')->maxLength(80)->placeholder('Rahul Sharma, Branch Manager'),
                            TextInput::make('hours')->label('Opening hours')->maxLength(120)->placeholder('Mon–Sat, 9:30 am – 6:30 pm'),
                        ]),
                ]),
                Group::make()->columnSpan(2)->schema([
                    Section::make('Map')->schema([
                        Textarea::make('map')->label('Google Maps embed code')->rows(3)->live(onBlur: true)
                            ->placeholder('<iframe src="https://www.google.com/maps/embed?pb=…"></iframe>')
                            ->helperText('In Google Maps: find the office → Share → Embed a map → Copy HTML, and paste it here. Leave empty to place the pin from the address.')
                            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) {
                                if (filled($value) && MapEmbed::src($value) === null) {
                                    $fail('Paste the embed code from Google Maps (Share → Embed a map), or a google.com/maps/embed link.');
                                }
                            }),
                        Html::make(fn (Get $get) => self::mapPreview($get)),
                    ]),
                    Section::make('Publishing')->schema([
                        Toggle::make('is_published')->label('Show on the contact page')->default(true),
                        TextInput::make('sort')->label('Order')->numeric()->default(fn () => (int) Office::max('sort') + 1)
                            ->helperText('Lower numbers are listed first. You can also drag offices in the list.'),
                    ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')->label('Office')->searchable()->weight('semibold')
                    ->description(fn (Office $record) => $record->contact_person),
                TextColumn::make('type')->badge()
                    ->formatStateUsing(fn (string $state) => Office::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => Office::TYPE_COLORS[$state] ?? 'gray'),
                TextColumn::make('street')->label('Address')->wrap()->searchable(['street', 'city', 'region', 'postal_code'])
                    ->formatStateUsing(fn (Office $record) => $record->fullAddress()),
                TextColumn::make('phone')->placeholder('Main number')->toggleable(),
                TextColumn::make('map')->label('Map')->badge()
                    ->state(fn (Office $record) => MapEmbed::src($record->map) ? 'Embedded' : 'From address')
                    ->color(fn (string $state) => $state === 'Embedded' ? 'success' : 'gray'),
                ToggleColumn::make('is_published')->label('On website'),
            ])
            ->recordActions([
                Action::make('directions')->label('Map')->icon('heroicon-o-map')->color('gray')
                    ->url(fn (Office $record) => $record->mapLink(), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make()->iconButton(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('No offices yet')
            ->emptyStateDescription('Add your head office and branches. They appear on the contact page with an interactive map.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffices::route('/'),
            'create' => CreateOffice::route('/create'),
            'edit' => EditOffice::route('/{record}/edit'),
        ];
    }

    private static function mapPreview(Get $get): HtmlString
    {
        $office = new Office([
            'street' => (string) $get('street'),
            'city' => $get('city'),
            'region' => $get('region'),
            'postal_code' => $get('postal_code'),
            'map' => $get('map'),
        ]);

        if (blank($office->street) && MapEmbed::src($office->map) === null) {
            return new HtmlString('<div style="display:flex;align-items:center;justify-content:center;height:16rem;padding:1.5rem;border:1px dashed rgb(209 213 219);border-radius:.75rem;text-align:center;font-size:.875rem;color:rgb(107 114 128)">Enter the address or paste the embed code to preview the map.</div>');
        }

        $source = MapEmbed::src($office->map) ? 'Showing your embedded map' : 'Pin placed from the address. Paste the embed code for an exact location';

        return new HtmlString(sprintf(
            '<div><iframe src="%s" title="Map preview" style="display:block;width:100%%;height:18rem;border:1px solid rgb(229 231 235);border-radius:.75rem" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe><p style="margin-top:.5rem;font-size:.75rem;color:rgb(107 114 128)">%s · <a href="%s" target="_blank" rel="noopener" style="font-weight:600;color:var(--primary-600)">Open in Google Maps</a></p></div>',
            e($office->mapEmbed()),
            e($source),
            e($office->mapLink()),
        ));
    }
}
