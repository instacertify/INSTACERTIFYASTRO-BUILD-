<?php

namespace App\Filament\Resources\Vacancies;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\Vacancies\Pages\CreateVacancy;
use App\Filament\Resources\Vacancies\Pages\EditVacancy;
use App\Filament\Resources\Vacancies\Pages\ListVacancies;
use App\Models\Vacancy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class VacancyResource extends Resource
{
    protected static ?string $model = Vacancy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Careers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Group::make()->columnSpan(2)->schema([
                    Section::make('Role')->columns(2)->schema([
                        TextInput::make('title')->label('Job title')->required()->maxLength(120)->columnSpanFull()
                            ->placeholder('Certification Executive — BIS & WPC')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                        TextInput::make('slug')->required()->unique(ignoreRecord: true)->prefix('instacertify.com/careers/')->columnSpanFull(),
                        TextInput::make('department')->datalist(fn () => Vacancy::query()->whereNotNull('department')->distinct()->pluck('department')->all() ?: ['Certification', 'Testing', 'Sales', 'Operations', 'Accounts'])
                            ->placeholder('Certification'),
                        TextInput::make('openings')->label('Number of openings')->numeric()->minValue(1)->default(1)->required(),
                        Select::make('employment_type')->options(Vacancy::EMPLOYMENT_TYPES)->default('FULL_TIME')->required(),
                        ToggleButtons::make('workplace')->options(Vacancy::WORKPLACES)->inline()->default('onsite')->required(),
                        TextInput::make('city')->default('Noida')->placeholder('Noida'),
                        TextInput::make('region')->label('State')->default('Uttar Pradesh'),
                        TextInput::make('experience_min')->label('Experience from (years)')->numeric()->minValue(0)->maxValue(40)
                            ->helperText('Use 0 for freshers.'),
                        TextInput::make('experience_max')->label('Experience up to (years)')->numeric()->minValue(0)->maxValue(40)->gte('experience_min'),
                    ]),
                    Section::make('Job description')->schema([
                        Textarea::make('summary')->label('Short summary')->rows(2)->maxLength(300)->required()
                            ->helperText('One or two sentences shown on the careers page list and in search results.'),
                        RichEditor::make('description')->required()
                            ->placeholder('Use headings such as "What you will do", "What we are looking for" and "What we offer".')
                            ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['undo', 'redo']]),
                        TagsInput::make('skills')->label('Key skills')->placeholder('Add a skill and press Enter')
                            ->suggestions(['BIS CRS', 'ISI', 'WPC', 'TEC', 'CE marking', 'Documentation', 'Client handling', 'MS Excel', 'Lab coordination']),
                    ]),
                    Section::make('Salary')->columns(3)->compact()->schema([
                        TextInput::make('salary_min')->label('From (₹ per year)')->numeric()->minValue(0)->prefix('₹'),
                        TextInput::make('salary_max')->label('Up to (₹ per year)')->numeric()->minValue(0)->prefix('₹')->gte('salary_min'),
                        Toggle::make('salary_visible')->label('Show salary on the website')->inline(false)
                            ->helperText('Listings with a salary get more applications and rank better in Google Jobs.'),
                    ]),
                ]),
                Group::make()->columnSpan(1)->schema([
                    Section::make('Publishing')->schema([
                        ToggleButtons::make('status')->options(Vacancy::STATUSES)->default('draft')->required()->live()
                            ->colors(['draft' => 'gray', 'open' => 'success', 'closed' => 'danger']),
                        DateTimePicker::make('published_at')->label('Posted on')->seconds(false)
                            ->helperText('Set automatically when you open the vacancy.'),
                        DatePicker::make('closes_at')->label('Applications close on')
                            ->helperText('Optional. The vacancy disappears from the website once this day is over.'),
                        TextInput::make('sort')->numeric()->default(0)->helperText('Lower numbers are listed first.'),
                    ]),
                    Section::make('Hiring team')
                        ->description('HR and admins see every application. Add anyone else (e.g. the department head) who should review the applicants for this vacancy only.')
                        ->schema([
                            Select::make('hiringTeam')->hiddenLabel()->relationship('hiringTeam', 'name')->multiple()->preload()->searchable()
                                ->helperText('They also get an email for each new application.'),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount([
                'applications',
                'applications as new_applications_count' => fn ($q) => $q->where('status', 'new'),
            ]))
            ->columns([
                TextColumn::make('title')->searchable()->wrap()
                    ->description(fn (Vacancy $record) => collect([$record->department, $record->location()])->filter()->implode(' · ')),
                TextColumn::make('employment_type')->label('Type')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => Vacancy::EMPLOYMENT_TYPES[$state] ?? $state),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state, Vacancy $record) => $state === 'open' && ! $record->isOpen() ? 'Expired / scheduled' : str(Vacancy::STATUSES[$state] ?? $state)->before(' —'))
                    ->color(fn (string $state, Vacancy $record) => match (true) {
                        $state === 'open' && $record->isOpen() => 'success',
                        $state === 'closed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('applications_count')->label('Applicants')->sortable()
                    ->description(fn (Vacancy $record) => $record->new_applications_count ? "{$record->new_applications_count} new" : null),
                TextColumn::make('closes_at')->label('Closes')->date()->placeholder('—')->sortable(),
                TextColumn::make('published_at')->label('Posted')->since()->placeholder('Not yet')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(Vacancy::STATUSES),
                SelectFilter::make('department')->options(fn () => Vacancy::query()->whereNotNull('department')->distinct()->pluck('department', 'department')->all()),
            ])
            ->recordActions([
                Action::make('applicants')->label('Applicants')->icon('heroicon-o-users')->color('gray')
                    ->url(fn (Vacancy $record) => JobApplicationResource::getUrl('index', ['filters' => ['vacancy_id' => ['value' => $record->id]]])),
                ReplicateAction::make()->iconButton()->excludeAttributes(['applications_count', 'new_applications_count'])
                    ->beforeReplicaSaved(fn (Vacancy $replica) => $replica->fill(['slug' => $replica->slug.'-'.Str::lower(Str::random(4)), 'status' => 'draft', 'published_at' => null])),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVacancies::route('/'),
            'create' => CreateVacancy::route('/create'),
            'edit' => EditVacancy::route('/{record}/edit'),
        ];
    }
}
