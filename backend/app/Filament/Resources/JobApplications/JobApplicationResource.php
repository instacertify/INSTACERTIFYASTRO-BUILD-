<?php

namespace App\Filament\Resources\JobApplications;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Filament\Resources\JobApplications\Pages\ViewJobApplication;
use App\Models\JobApplication;
use App\Models\Vacancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Number;
use UnitEnum;

class JobApplicationResource extends Resource
{
    protected static ?string $model = JobApplication::class;

    protected static ?string $modelLabel = 'application';

    protected static ?string $navigationLabel = 'Applications';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Careers';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', 'new')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(2)->schema([
                Section::make('Candidate')->columns(2)->schema([
                    TextEntry::make('name')->weight('bold'),
                    TextEntry::make('vacancy.title')->label('Applied for')->placeholder('General application'),
                    TextEntry::make('email')->copyable()->url(fn (JobApplication $record) => $record->email ? "mailto:{$record->email}" : null),
                    TextEntry::make('phone')->copyable()->placeholder('—'),
                    TextEntry::make('city')->placeholder('—'),
                    TextEntry::make('linkedin_url')->label('LinkedIn / portfolio')->placeholder('—')
                        ->url(fn (JobApplication $record) => $record->linkedin_url, shouldOpenInNewTab: true),
                    TextEntry::make('experience_years')->label('Experience')->placeholder('—')
                        ->formatStateUsing(fn ($state) => $state.' '.str('year')->plural((float) $state)),
                    TextEntry::make('notice_period')->placeholder('—')
                        ->formatStateUsing(fn (?string $state) => JobApplication::NOTICE_PERIODS[$state] ?? $state),
                    TextEntry::make('current_ctc')->label('Current CTC')->placeholder('—'),
                    TextEntry::make('expected_ctc')->label('Expected CTC')->placeholder('—'),
                    TextEntry::make('cover_letter')->label('Cover note')->placeholder('—')->columnSpanFull()
                        ->formatStateUsing(fn (?string $state) => nl2br(e($state)))->html(),
                ]),
            ]),
            Grid::make(1)->columnSpan(1)->schema([
                Section::make('Pipeline')->schema([
                    TextEntry::make('status')->badge()
                        ->formatStateUsing(fn (string $state) => JobApplication::STATUSES[$state] ?? $state)
                        ->color(fn (string $state) => JobApplication::STATUS_COLORS[$state] ?? 'gray'),
                    TextEntry::make('rating')->placeholder('Not rated')
                        ->formatStateUsing(fn (?int $state) => str_repeat('★', (int) $state).str_repeat('☆', 5 - (int) $state)),
                    TextEntry::make('notes')->placeholder('No notes yet')
                        ->formatStateUsing(fn (?string $state) => nl2br(e($state)))->html(),
                ]),
                Section::make('CV')->schema([
                    TextEntry::make('cv_name')->label('File')->placeholder('Deleted under the retention policy')
                        ->helperText(fn (JobApplication $record) => $record->cv_size ? Number::fileSize($record->cv_size) : null),
                ]),
                Section::make('Consent & source')->collapsed()
                    ->description('What the candidate agreed to, for DPDP/GDPR requests.')
                    ->schema([
                        TextEntry::make('created_at')->label('Received')->dateTime(),
                        TextEntry::make('consented_at')->label('Consent given')->dateTime()->placeholder('—'),
                        TextEntry::make('consent_version')->label('Privacy notice version')->placeholder('—'),
                        TextEntry::make('source_page')->placeholder('—'),
                        TextEntry::make('utm_source')->label('Campaign source')->placeholder('direct'),
                        TextEntry::make('anonymised_at')->label('Anonymised')->dateTime()
                            ->visible(fn (JobApplication $record) => (bool) $record->anonymised_at),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
                TextColumn::make('name')->searchable(['name', 'email', 'phone'])
                    ->description(fn (JobApplication $record) => $record->city),
                TextColumn::make('vacancy.title')->label('Position')->placeholder('General application')->wrap(),
                TextColumn::make('experience_years')->label('Exp.')->sortable()->placeholder('—')
                    ->formatStateUsing(fn ($state) => "{$state} yrs"),
                TextColumn::make('notice_period')->label('Notice')->placeholder('—')->toggleable()
                    ->formatStateUsing(fn (?string $state) => JobApplication::NOTICE_PERIODS[$state] ?? $state),
                TextColumn::make('rating')->placeholder('—')->sortable()
                    ->formatStateUsing(fn (?int $state) => str_repeat('★', (int) $state)),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => JobApplication::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => JobApplication::STATUS_COLORS[$state] ?? 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')->options(JobApplication::STATUSES)->multiple(),
                SelectFilter::make('vacancy_id')->label('Position')
                    ->options(fn () => Vacancy::query()
                        ->when(! auth()->user()->canManageCareers(), fn (Builder $q) => $q->whereHas('hiringTeam', fn (Builder $q) => $q->whereKey(auth()->id())))
                        ->orderByDesc('published_at')->pluck('title', 'id')->all()),
            ])
            ->recordActions([
                self::cvAction()->iconButton(),
                self::updateAction(),
                ViewAction::make()->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('status')->label('Change status')->icon('heroicon-o-arrow-path')
                        ->schema([ToggleButtons::make('status')->options(JobApplication::STATUSES)->inline()->required()])
                        ->action(fn (Collection $records, array $data) => $records->each->update($data)),
                    DeleteBulkAction::make()->authorize(fn () => auth()->user()->canManageCareers()),
                ]),
            ]);
    }

    public static function cvAction(): Action
    {
        return Action::make('cv')->label('Open CV')->icon('heroicon-o-document-arrow-down')->color('gray')
            ->visible(fn (JobApplication $record) => filled($record->cv_path))
            ->url(fn (JobApplication $record) => route('filament.admin.job-applications.cv', $record), shouldOpenInNewTab: true);
    }

    public static function updateAction(): Action
    {
        return Action::make('update')->label('Update')->icon('heroicon-o-pencil-square')->color('gray')
            ->authorize(fn (JobApplication $record) => auth()->user()->can('update', $record))
            ->fillForm(fn (JobApplication $record) => $record->only(['status', 'rating']))
            ->schema([
                ToggleButtons::make('status')->options(JobApplication::STATUSES)->colors(JobApplication::STATUS_COLORS)->inline()->required(),
                ToggleButtons::make('rating')->options([1 => '★', 2 => '★★', 3 => '★★★', 4 => '★★★★', 5 => '★★★★★'])->inline(),
                Textarea::make('note')->label('Add a note')->rows(3)->helperText('Internal only. Saved with your name and the date.'),
            ])
            ->action(function (JobApplication $record, array $data) {
                $note = trim($data['note'] ?? '');
                unset($data['note']);
                if ($note !== '') {
                    $data['notes'] = trim(now()->format('d M Y H:i').' — '.auth()->user()->name.": {$note}\n".$record->notes);
                }
                $record->update($data);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJobApplications::route('/'),
            'view' => ViewJobApplication::route('/{record}'),
        ];
    }
}
