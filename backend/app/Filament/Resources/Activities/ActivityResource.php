<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Models\User;
use App\Support\AuditTrail;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $modelLabel = 'activity';

    protected static ?string $pluralModelLabel = 'activity log';

    protected static ?string $navigationLabel = 'Activity log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    private const LOGS = ['settings' => 'Site settings, home page & menu', 'auth' => 'Sign-ins', 'export' => 'CSV exports'];

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->schema([
                TextEntry::make('causer.name')->label('By')->placeholder('System / website visitor'),
                TextEntry::make('created_at')->label('When')->dateTime('d M Y, H:i:s'),
                TextEntry::make('event')->label('Action')->badge()->color(fn (?string $state) => self::color($state))
                    ->formatStateUsing(fn (?string $state) => AuditTrail::EVENTS[$state] ?? Str::headline((string) $state)),
                TextEntry::make('area')->label('What')->state(fn (Activity $record) => AuditTrail::area($record)),
                TextEntry::make('subject_title')->label('Record')->state(fn (Activity $record) => AuditTrail::record($record))->placeholder('—')
                    ->url(fn (Activity $record) => AuditTrail::url($record))->color(fn (Activity $record) => AuditTrail::url($record) ? 'primary' : null),
                TextEntry::make('description')->label('Summary')->visible(fn (Activity $record) => $record->description !== $record->event),
                TextEntry::make('properties.ip')->label('IP address')->placeholder('—')->copyable(),
                TextEntry::make('properties.user_agent')->label('Browser')->placeholder('—')->columnSpan(2),
            ]),
            Section::make('What changed')->columns(2)
                ->visible(fn (Activity $record) => filled($record->properties['old'] ?? null) || filled($record->properties['attributes'] ?? null))
                ->schema([
                    KeyValueEntry::make('before')->label('Before')->keyLabel('Field')->state(fn (Activity $record) => self::flatten($record->properties['old'] ?? [])),
                    KeyValueEntry::make('after')->label('After')->keyLabel('Field')->state(fn (Activity $record) => self::flatten($record->properties['attributes'] ?? [])),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['causer', 'subject']))
            ->description('Everything staff do in the admin: what they created, edited or deleted (with before/after values), settings and menu changes, sign-ins and CSV exports.')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->description(fn (Activity $record) => $record->created_at->diffForHumans())->sortable(),
                TextColumn::make('causer.name')->label('By')->placeholder('System'),
                TextColumn::make('event')->label('Action')->badge()->color(fn (?string $state) => self::color($state))
                    ->formatStateUsing(fn (?string $state) => AuditTrail::EVENTS[$state] ?? Str::headline((string) $state)),
                TextColumn::make('area')->label('What')->state(fn (Activity $record) => AuditTrail::area($record)),
                TextColumn::make('subject_title')->label('Record')->placeholder('—')->wrap()->limit(60)
                    ->state(fn (Activity $record) => AuditTrail::record($record))
                    ->url(fn (Activity $record) => AuditTrail::url($record))
                    ->color(fn (Activity $record) => AuditTrail::url($record) ? 'primary' : null),
                TextColumn::make('changed')->label('Fields changed')->placeholder('—')->limit(60)->wrap()
                    ->state(fn (Activity $record) => implode(', ', array_keys($record->properties['attributes'] ?? [])) ?: null),
                TextColumn::make('properties.ip')->label('IP address')->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('causer_id')->label('By')->options(fn () => User::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('event')->label('Action')->options(AuditTrail::EVENTS),
                SelectFilter::make('area')->label('What')
                    ->options(fn () => [
                        ...Activity::query()->whereNotNull('subject_type')->distinct()->pluck('subject_type')
                            ->mapWithKeys(fn (string $type) => [$type => AuditTrail::area(new Activity(['subject_type' => $type]))])
                            ->sort()->all(),
                        ...collect(self::LOGS)->mapWithKeys(fn ($label, $log) => ["log:{$log}" => $label])->all(),
                    ])
                    ->query(fn (Builder $query, array $data) => match (true) {
                        blank($data['value'] ?? null) => $query,
                        str_starts_with($data['value'], 'log:') => $query->whereNull('subject_type')->where('log_name', substr($data['value'], 4)),
                        default => $query->where('subject_type', $data['value']),
                    }),
                Filter::make('created_at')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([ViewAction::make()]);
    }

    private static function color(?string $event): string
    {
        return match ($event) {
            'created' => 'success',
            'deleted', 'login_failed' => 'danger',
            'exported', 'password_changed', 'cv_viewed' => 'warning',
            'login', 'logout' => 'gray',
            default => 'info',
        };
    }

    /** @return array<string, string> */
    private static function flatten(array $values): array
    {
        return collect($values)->map(fn ($v) => is_scalar($v) || $v === null ? Str::limit(strip_tags((string) $v), 300) : Str::limit(json_encode($v, JSON_UNESCAPED_UNICODE), 300))->all();
    }

    public static function getPages(): array
    {
        return ['index' => ListActivities::route('/')];
    }
}
