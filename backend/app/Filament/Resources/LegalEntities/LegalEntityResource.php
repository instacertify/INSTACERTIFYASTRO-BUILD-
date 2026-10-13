<?php

namespace App\Filament\Resources\LegalEntities;

use App\Filament\Resources\LegalEntities\Pages\CreateLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\EditLegalEntity;
use App\Filament\Resources\LegalEntities\Pages\ListLegalEntities;
use App\Models\LegalEntity;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

/** The companies behind the website, e.g. the Indian company and a future US company sharing one brand. */
class LegalEntityResource extends Resource
{
    protected static ?string $model = LegalEntity::class;

    protected static ?string $modelLabel = 'company entity';

    protected static ?string $pluralModelLabel = 'company entities';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Company entities';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(5)
            ->components([
                Group::make()->columnSpan(3)->schema([
                    Section::make('Company')->columns(2)->schema([
                        TextInput::make('name')->label('Registered company name')->required()->maxLength(150)->columnSpanFull()
                            ->placeholder('Instacertify Inc.'),
                        TextInput::make('market')->label('Market')->required()->maxLength(60)->placeholder('United States')
                            ->helperText('Shown as the heading above this company in the footer when more than one is listed.'),
                        Select::make('country')->label('Country of registration')->options(LegalEntity::COUNTRIES)->required()->searchable()->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set) {
                                if (blank($get('registration_label')) && isset(LegalEntity::REGISTRATION_LABELS[$state])) {
                                    $set('registration_label', LegalEntity::REGISTRATION_LABELS[$state]);
                                }
                            }),
                        TextInput::make('registration_label')->label('Registration type')->maxLength(20)->placeholder('CIN, EIN, Company No.'),
                        TextInput::make('registration_number')->maxLength(60)->placeholder('U74999UP2022PTC170291'),
                        Textarea::make('address')->label('Registered address')->rows(3)->maxLength(500)->columnSpanFull()
                            ->placeholder('A-34, Sector 63A, Noida, Uttar Pradesh 201301, India'),
                    ]),
                    Section::make('Contact details')->columns(2)
                        ->description('Optional. Leave empty to use the main phone and email from Site settings.')
                        ->schema([
                            TextInput::make('phone')->tel()->maxLength(30)->placeholder('+1 555 010 0199'),
                            TextInput::make('email')->email()->maxLength(255)->placeholder('us@instacertify.com'),
                        ]),
                ]),
                Group::make()->columnSpan(2)->schema([
                    Section::make('On the website')->schema([
                        Toggle::make('show_on_website')->label('Show in the footer')->default(false)
                            ->helperText('Lists the company name and address in the footer. Switch it on for each company once it is ready to be shown.'),
                        Toggle::make('is_primary')->label('Main company')->default(fn () => ! LegalEntity::where('is_primary', true)->exists())
                            ->helperText('Named in the legal notices, the grievance officer details and the landing page footers. Making this the main company replaces the previous one.'),
                        TextInput::make('sort')->label('Order')->numeric()->default(fn () => (int) LegalEntity::max('sort') + 1)
                            ->helperText('Lower numbers are listed first in the footer.'),
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
                TextColumn::make('name')->label('Company')->searchable()->weight('semibold')
                    ->description(fn (LegalEntity $record) => $record->registration_number ? "{$record->registration_label} {$record->registration_number}" : null),
                TextColumn::make('market')->badge()->color('info'),
                TextColumn::make('country')->formatStateUsing(fn (string $state) => LegalEntity::COUNTRIES[$state] ?? $state),
                IconColumn::make('is_primary')->label('Main')->boolean()->trueIcon('heroicon-s-star')->falseIcon('heroicon-o-minus')->trueColor('warning'),
                ToggleColumn::make('show_on_website')->label('In footer'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()->iconButton()->hidden(fn (LegalEntity $record) => $record->is_primary)])
            ->emptyStateHeading('No company entities yet')
            ->emptyStateDescription('Add the companies that trade under the Instacertify brand, one per market.');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLegalEntities::route('/'),
            'create' => CreateLegalEntity::route('/create'),
            'edit' => EditLegalEntity::route('/{record}/edit'),
        ];
    }
}
