<?php

namespace App\Filament\Resources\Subscribers;

use App\Filament\Resources\Subscribers\Pages\ManageSubscribers;
use App\Models\Subscriber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SubscriberResource extends Resource
{
    protected static ?string $model = Subscriber::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Subscriber::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['active' => 'success', 'pending' => 'warning'][$state] ?? 'gray'),
                TextColumn::make('source_page')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('Signed up')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(Subscriber::STATUSES)])
            ->recordActions([
                Action::make('unsubscribe')->icon('heroicon-o-no-symbol')->color('gray')->requiresConfirmation()
                    ->visible(fn (Subscriber $record) => $record->status !== 'unsubscribed')
                    ->action(fn (Subscriber $record) => $record->update(['status' => 'unsubscribed', 'unsubscribed_at' => now()])),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSubscribers::route('/')];
    }
}
