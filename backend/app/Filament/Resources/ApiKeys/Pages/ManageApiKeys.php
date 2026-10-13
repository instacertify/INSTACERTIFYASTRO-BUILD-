<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ManageApiKeys extends ManageRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected static ?string $title = 'API keys';

    public function getSubheading(): string|Htmlable|null
    {
        $url = e(rtrim(config('app.url'), '/').'/api/v1/integrations/leads');

        return new HtmlString('Let a CRM, Zapier, Make or your own script read enquiries. Send the key as <code>Authorization: Bearer &lt;key&gt;</code> to '
            ."<code>GET {$url}</code> (newest first; filters: <code>created_after</code>, <code>updated_after</code> as dates, <code>status</code>, <code>type</code>, <code>per_page</code> up to 100) "
            ."or <code>GET {$url}/{id}</code>. Read-only; IP addresses and internal notes are never included.");
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')->label('Create API key')->icon(Heroicon::OutlinedPlus)
                ->modalHeading('Create API key')
                ->modalSubmitActionLabel('Create key')
                ->schema([
                    TextInput::make('name')->label('Name')->required()->maxLength(80)->placeholder('Zoho CRM')
                        ->helperText('Where the key is used, so you know which one to revoke later.'),
                    CheckboxList::make('abilities')->label('Access')->options(ApiKeyResource::ABILITIES)
                        ->default(array_keys(ApiKeyResource::ABILITIES))->required(),
                    Select::make('expires')->label('Expires')->required()->default('365')->selectablePlaceholder(false)
                        ->options(['30' => 'In 30 days', '90' => 'In 90 days', '365' => 'In 1 year', 'never' => 'Never']),
                ])
                ->action(function (array $data): void {
                    $token = auth()->user()->createToken(
                        $data['name'],
                        array_values(array_intersect($data['abilities'], array_keys(ApiKeyResource::ABILITIES))),
                        $data['expires'] === 'never' ? null : now()->addDays((int) $data['expires']),
                    );

                    Notification::make()->success()->persistent()
                        ->title('API key created — copy it now')
                        ->body(new HtmlString('<p>This is the only time the key is shown:</p><p><code style="word-break:break-all;user-select:all">'
                            .e($token->plainTextToken).'</code></p><p>Lost it? Revoke it and create a new one.</p>'))
                        ->send();
                }),
        ];
    }
}
