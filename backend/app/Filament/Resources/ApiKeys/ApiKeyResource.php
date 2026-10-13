<?php

namespace App\Filament\Resources\ApiKeys;

use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Laravel\Sanctum\PersonalAccessToken;
use UnitEnum;

/** Keys for other tools (CRM, Zapier, Make) to read enquiries through /api/v1/integrations/leads. */
class ApiKeyResource extends Resource
{
    protected static ?string $model = PersonalAccessToken::class;

    protected static ?string $modelLabel = 'API key';

    protected static ?string $pluralModelLabel = 'API keys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'API keys';

    protected static ?int $navigationSort = 5;

    public const ABILITIES = ['leads:read' => 'Read enquiries (leads)'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No API keys yet')
            ->emptyStateDescription('Create one to let a CRM, Zapier or Make read new enquiries.')
            ->columns([
                TextColumn::make('name')->weight('medium')->searchable(),
                TextColumn::make('abilities')->label('Access')->badge()
                    ->formatStateUsing(fn (string $state) => self::ABILITIES[$state] ?? $state),
                TextColumn::make('tokenable.name')->label('Created by')->placeholder('—'),
                TextColumn::make('last_used_at')->label('Last used')->since()->placeholder('Never')->sortable(),
                TextColumn::make('expires_at')->label('Expires')->date('j M Y')->placeholder('Never')->sortable()
                    ->color(fn ($state) => $state?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')->label('Created')->date('j M Y')->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()->label('Revoke')
                    ->modalHeading('Revoke this API key?')
                    ->modalDescription('Tools using it stop getting enquiries immediately. This cannot be undone.')
                    ->successNotificationTitle('API key revoked'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageApiKeys::route('/')];
    }
}
