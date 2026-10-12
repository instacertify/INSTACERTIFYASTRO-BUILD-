<?php

namespace App\Filament\Pages;

use App\Support\SitePublisher;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

/** Shows whether saved changes are live on the website, and lets editors publish straight away. */
class ManagePublishing extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Publish website';

    protected static ?string $title = 'Publish website';

    protected static ?string $slug = 'publish-website';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageContent() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish now')
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->disabled(fn () => ! SitePublisher::configured())
                ->action(function () {
                    $started = rescue(fn () => SitePublisher::request(), false, report: false);

                    Notification::make()
                        ->title($started ? 'Publishing started' : 'The site builder could not be reached')
                        ->body($started
                            ? 'The website is being rebuilt with your latest changes. This takes a few minutes; refresh this page to follow it.'
                            : 'Try again in a minute. If it keeps failing, ask your developer to check the "builder" service on the server.')
                        ->color($started ? 'success' : 'danger')
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('How changes go live')
                ->icon(Heroicon::OutlinedInformationCircle)
                ->schema([
                    Text::make('Every change you save in the admin — pages, blog posts, images, menus, settings — starts publishing a few seconds after you save. Rebuilding the website then takes a few minutes. Saving without changing anything leaves the website as it is.'),
                    Text::make('Nothing else changes the website, except dates you set: it republishes on its own when a scheduled post or vacancy goes live, an event ends, a vacancy closes or a standard becomes mandatory. Use “Publish now” to republish straight away.'),
                ]),
            Section::make('Status')
                ->icon(Heroicon::OutlinedSignal)
                ->schema(fn () => array_map(fn (string $line) => Text::make($line), self::statusLines(SitePublisher::configured(), SitePublisher::status()))),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $status
     * @return list<string>
     */
    public static function statusLines(bool $configured, ?array $status): array
    {
        if (! $configured) {
            return ['Automatic publishing is not set up on this server, so changes appear when the website is rebuilt by your developer.'];
        }
        if ($status === null) {
            return ['The site builder is not answering right now. Saved changes are kept and go live once it is back.'];
        }

        $when = fn (?string $time) => $time ? Carbon::parse($time)->timezone('Asia/Kolkata')->format('j M Y, g:i a') : null;
        $lines = [match ($status['status'] ?? null) {
            'building' => 'Publishing now — started '.$when($status['startedAt'] ?? null).'.'.(! empty($status['queued']) ? ' Newer changes will be published straight after.' : ''),
            'failed' => 'The last publish did not complete, so the website still shows the previous version.',
            default => 'Up to date.',
        }];
        if (! empty($status['publishedAt'])) {
            $lines[] = 'Last published: '.$when($status['publishedAt']).(isset($status['pages']) ? ' ('.number_format($status['pages']).' pages)' : '').'.';
        }
        if (($status['status'] ?? null) === 'failed' && ! empty($status['error'])) {
            $lines[] = 'Reason: '.str($status['error'])->before("\n")->limit(300);
        }

        return $lines;
    }
}
