<?php

namespace App\Filament\Resources\ConsentLogs;

use App\Filament\Resources\ConsentLogs\Pages\ListConsentLogs;
use App\Models\ConsentLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ConsentLogResource extends Resource
{
    protected static ?string $model = ConsentLog::class;

    protected static ?string $modelLabel = 'cookie consent';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Cookie consents';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        $choice = fn (string $key) => IconColumn::make("choices.{$key}")->label(ucfirst($key))->boolean()->alignCenter();

        return $table
            ->defaultSort('created_at', 'desc')
            ->description('Proof of each visitor\'s cookie choice, with the IP address and browser it came from. Records (including the IP) are deleted automatically after the retention period in Site settings → Privacy & cookies.')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('action')->badge()
                    ->formatStateUsing(fn (string $state) => ConsentLog::ACTIONS[$state] ?? $state)
                    ->color(fn (string $state) => ['accept_all' => 'success', 'reject_all' => 'gray', 'withdraw' => 'danger'][$state] ?? 'info'),
                $choice('preferences'),
                $choice('analytics'),
                $choice('marketing'),
                TextColumn::make('policy_version')->label('Policy')->badge()->color('gray'),
                TextColumn::make('language')->label('Lang')->toggleable(),
                TextColumn::make('page')->placeholder('—')->limit(40)->toggleable(),
                TextColumn::make('ip')->label('IP address')->placeholder('—')->searchable()->copyable()->toggleable(),
                TextColumn::make('user_agent')->label('Browser')->placeholder('—')->limit(40)->tooltip(fn (ConsentLog $record) => $record->user_agent)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('consent_id')->label('Consent ID')->searchable()->copyable()->limit(13)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('action')->options(ConsentLog::ACTIONS),
                SelectFilter::make('policy_version')->label('Policy version')
                    ->options(fn () => ConsentLog::query()->distinct()->orderByDesc('policy_version')->pluck('policy_version', 'policy_version')->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListConsentLogs::route('/')];
    }
}
