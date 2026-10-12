<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class OverdueFollowUps extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Follow-ups due';

    public static function canView(): bool
    {
        return auth()->user()?->canManageLeads() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Lead::overdue()->with(['service', 'assignee'])->orderBy('follow_up_at'))
            ->paginated([5])
            ->emptyStateHeading('Nothing overdue')
            ->emptyStateDescription('Every open lead has a future follow-up or none set.')
            ->columns([
                TextColumn::make('follow_up_at')->label('Due')->since()->color('danger'),
                TextColumn::make('name')->description(fn (Lead $record) => $record->company),
                TextColumn::make('service.title')->label('Service')->placeholder('—'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (string $state) => Lead::STATUSES[$state] ?? $state),
                TextColumn::make('assignee.name')->label('Owner')->placeholder('Unassigned'),
            ])
            ->recordActions([
                Action::make('open')->url(fn (Lead $record) => LeadResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
