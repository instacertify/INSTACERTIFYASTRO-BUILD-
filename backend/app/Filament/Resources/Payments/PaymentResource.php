<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Payment;
use App\Support\PaymentSettings;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only: payments are only ever changed by the gateway. Refunds are made in the gateway's dashboard. */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'CRM';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'name', 'email', 'invoice_number'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Payment')->columns(2)->schema([
                TextEntry::make('amount')->weight('bold')->formatStateUsing(fn ($state, Payment $record) => $record->formattedAmount()),
                TextEntry::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Payment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Payment::STATUS_COLORS[$state] ?? 'gray'),
                TextEntry::make('invoice_number')->label('Invoice')->placeholder('—')->copyable(),
                TextEntry::make('reference')->copyable(),
                TextEntry::make('created_at')->label('Started')->dateTime(),
                TextEntry::make('paid_at')->label('Paid')->dateTime()->placeholder('—'),
                TextEntry::make('failure_reason')->label('Why it failed')->placeholder('—')->columnSpanFull()
                    ->visible(fn (Payment $record) => filled($record->failure_reason)),
                TextEntry::make('note')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Payer')->columns(2)->schema([
                TextEntry::make('name')->weight('bold'),
                TextEntry::make('company')->placeholder('—'),
                TextEntry::make('email')->copyable()->url(fn (Payment $record) => "mailto:{$record->email}"),
                TextEntry::make('phone')->copyable()->placeholder('—'),
            ]),
            Section::make('Gateway')->columns(3)->columnSpanFull()
                ->description('Look these up in the gateway dashboard, e.g. to refund the payment.')
                ->schema([
                    TextEntry::make('gateway')->formatStateUsing(fn (string $state) => PaymentSettings::GATEWAYS[$state] ?? $state),
                    TextEntry::make('gateway_order_id')->label('Order / session ID')->placeholder('—')->copyable(),
                    TextEntry::make('gateway_payment_id')->label('Payment ID')->placeholder('—')->copyable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Started')->since()->sortable(),
                TextColumn::make('name')->searchable(['name', 'email', 'company'])
                    ->description(fn (Payment $record) => $record->company ?: $record->email),
                TextColumn::make('invoice_number')->label('Invoice')->searchable()->placeholder('—'),
                TextColumn::make('amount')->sortable()->alignEnd()
                    ->formatStateUsing(fn ($state, Payment $record) => $record->formattedAmount()),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Payment::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => Payment::STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('gateway')->toggleable(isToggledHiddenByDefault: true)
                    ->formatStateUsing(fn (string $state) => PaymentSettings::GATEWAYS[$state] ?? $state),
                TextColumn::make('reference')->searchable()->copyable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Payment::STATUSES)->default('paid'),
                SelectFilter::make('gateway')->options(PaymentSettings::GATEWAYS),
            ])
            ->recordActions([ViewAction::make()->iconButton()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
