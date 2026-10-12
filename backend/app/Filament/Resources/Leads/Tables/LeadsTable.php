<?php

namespace App\Filament\Resources\Leads\Tables;

use App\Jobs\ProcessNewLead;
use App\Models\Lead;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class LeadsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Received')
                    ->since()
                    ->sortable(),
                TextColumn::make('name')
                    ->description(fn (Lead $record) => $record->company)
                    ->searchable(['name', 'company']),
                TextColumn::make('email')
                    ->copyable()
                    ->searchable()
                    ->description(fn (Lead $record) => $record->phone),
                TextColumn::make('requirement')
                    ->label('Needs')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state) => Lead::REQUIREMENTS[$state] ?? $state)
                    ->placeholder('—'),
                TextColumn::make('service.title')
                    ->label('Service')
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('timeline')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'urgent' ? 'danger' : 'gray'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Lead::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        'new' => 'info',
                        'contacted' => 'warning',
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'primary',
                    }),
                TextColumn::make('assignee.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('follow_up_at')
                    ->label('Follow-up')
                    ->since()
                    ->sortable()
                    ->placeholder('—')
                    ->color(fn (Lead $record) => $record->follow_up_at?->isPast() && in_array($record->status, Lead::OPEN_STATUSES) ? 'danger' : null),
                TextColumn::make('emailed_at')
                    ->label('Team emailed')
                    ->since()
                    ->placeholder('Not sent')
                    ->color(fn (Lead $record) => $record->emailed_at || ! $record->consented_at ? 'gray' : 'danger')
                    ->toggleable(),
                TextColumn::make('utm_source')
                    ->label('Source')
                    ->placeholder('direct')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip')
                    ->label('IP address')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(Lead::STATUSES)->multiple(),
                SelectFilter::make('type')->options(Lead::TYPES),
                SelectFilter::make('requirement')->label('Needs')->options(Lead::REQUIREMENTS),
                SelectFilter::make('service_path')
                    ->label('Service')
                    ->relationship('service', 'title')
                    ->searchable(),
                SelectFilter::make('assigned_to')
                    ->label('Owner')
                    ->relationship('assignee', 'name'),
                TernaryFilter::make('emailed')
                    ->label('Team emailed')
                    ->nullable()
                    ->attribute('emailed_at')
                    ->trueLabel('Sent')
                    ->falseLabel('Not sent'),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->visible(fn (Lead $record) => filled($record->phone))
                    ->url(fn (Lead $record) => 'https://wa.me/'.ltrim($record->phone, '+'), shouldOpenInNewTab: true),
                Action::make('status')
                    ->label('Update')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->fillForm(fn (Lead $record) => $record->only(['status', 'follow_up_at', 'assigned_to']))
                    ->schema([
                        ToggleButtons::make('status')->options(Lead::STATUSES)->inline()->required(),
                        DateTimePicker::make('follow_up_at')->label('Next follow-up')->seconds(false),
                        Select::make('assigned_to')->label('Owner')->options(fn () => User::pluck('name', 'id')),
                        Textarea::make('note')->label('Add a note')->rows(2),
                    ])
                    ->action(function (Lead $record, array $data) {
                        $note = trim($data['note'] ?? '');
                        unset($data['note']);
                        if ($note !== '') {
                            $data['notes'] = trim(now()->format('d M Y H:i').' — '.auth()->user()->name.": {$note}\n".$record->notes);
                        }
                        $record->update($data);
                    }),
                Action::make('email')
                    ->label('Email the team again')
                    ->icon('heroicon-o-envelope')
                    ->color('danger')
                    ->visible(fn (Lead $record) => $record->emailed_at === null && $record->consented_at !== null)
                    ->action(fn (Lead $record) => self::emailTeam(collect([$record]))),
                EditAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('email')
                        ->label('Email the team again')
                        ->icon('heroicon-o-envelope')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => self::emailTeam($records)),
                    BulkAction::make('assign')
                        ->icon('heroicon-o-user-plus')
                        ->schema([Select::make('assigned_to')->label('Owner')->options(fn () => User::pluck('name', 'id'))->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update($data)),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** @param  iterable<Lead>  $leads */
    private static function emailTeam(iterable $leads): void
    {
        $count = 0;
        foreach ($leads as $lead) {
            // On a synchronous queue a mail outage must not break the page; the failure shows on the email settings page.
            rescue(fn () => ProcessNewLead::dispatch($lead));
            $count++;
        }

        Notification::make()->success()
            ->title($count === 1 ? 'Email queued' : "{$count} emails queued")
            ->body('Sent to the recipients under Settings → Email & notifications.')
            ->send();
    }
}
