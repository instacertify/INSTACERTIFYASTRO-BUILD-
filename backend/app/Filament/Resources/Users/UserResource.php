<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'team member';

    protected static ?string $navigationLabel = 'Team & roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required(),
            TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')->password()->revealable()
                ->required(fn (string $operation) => $operation === 'create')
                ->dehydrated(fn (?string $state) => filled($state))
                ->minLength(12)
                ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : 'At least 12 characters.'),
            Radio::make('role')->options(User::ROLES)->required()->default('editor'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->description(fn (User $record) => $record->email),
            TextColumn::make('role')->badge()->color(fn (string $state) => ['admin' => 'danger', 'editor' => 'info', 'sales' => 'success', 'hr' => 'warning'][$state] ?? 'gray'),
            TextColumn::make('created_at')->label('Added')->since(),
        ])->recordActions([
            Action::make('activity')->icon('heroicon-o-clock')->color('gray')
                ->url(fn (User $record) => ActivityResource::getUrl('index', ['filters' => ['causer_id' => ['value' => $record->id]]])),
            EditAction::make(),
            DeleteAction::make()->hidden(fn (User $record) => $record->is(auth()->user())),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
