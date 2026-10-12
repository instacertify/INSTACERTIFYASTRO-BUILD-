<?php

namespace App\Filament\Resources\Services\Tables;

use App\Models\Service;
use App\Support\TestCategories;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('title')
                    ->description(fn (Service $record) => '/'.$record->path)
                    ->searchable(['title', 'path', 'summary'])
                    ->wrap(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Service::KINDS[$state] ?? $state),
                TextColumn::make('region')
                    ->formatStateUsing(fn (?string $state) => Service::REGIONS[$state] ?? $state)
                    ->placeholder('—'),
                IconColumn::make('detail')
                    ->label('Full page')
                    ->boolean()
                    ->state(fn (Service $record) => ! empty($record->detail)),
                ToggleColumn::make('is_featured')->label('Featured'),
                ToggleColumn::make('is_published')->label('Live'),
                TextColumn::make('updated_at')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('kind')->options(Service::KINDS),
                SelectFilter::make('region')->options(Service::REGIONS),
                SelectFilter::make('group')->options(Service::GROUPS),
                SelectFilter::make('test_categories')->label('Testing industry')->options(TestCategories::options())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $q, string $slug) => $q->whereJsonContains('test_categories', $slug))),
                TernaryFilter::make('is_published')->label('Live'),
            ])
            ->recordActions([
                Action::make('view')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Service $record) => rtrim(config('instacertify.frontend_url'), '/').$record->url(), shouldOpenInNewTab: true),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
