<?php

namespace App\Filament\Forms\Components\RichEditor\RichContentCustomBlocks;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

class VideoBlock extends SizedBlock
{
    public static function getId(): string
    {
        return 'video';
    }

    public static function getLabel(): string
    {
        return 'YouTube video';
    }

    public static function getIcon(): Heroicon
    {
        return Heroicon::OutlinedPlayCircle;
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalDescription('Paste a YouTube link and pick how big the video shows in the text.')
            ->schema([
                TextInput::make('url')->label('YouTube link')->required()->url()->maxLength(300)
                    ->placeholder('https://www.youtube.com/watch?v=…')
                    ->helperText('Any YouTube link works: watch, share (youtu.be), Shorts or embed. A start time (?t=90) is kept.')
                    ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                        if (! self::parse((string) $value)) {
                            $fail('This is not a YouTube video link.');
                        }
                    }),
                TextInput::make('title')->label('Video title')->required()->maxLength(150)
                    ->helperText('Read out by screen readers and shown to Google. Use the video’s own title.'),
                self::sizeField('full'),
                self::alignField(),
                self::captionField(),
            ]);
    }

    public static function getPreviewLabel(array $config): string
    {
        return 'YouTube video · '.self::SIZES[self::size($config)].(filled($config['title'] ?? null) ? ' · '.$config['title'] : '');
    }

    public static function toPreviewHtml(array $config): string
    {
        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.video.preview', [
            'video' => self::parse((string) ($config['url'] ?? '')),
            'title' => $config['title'] ?? '',
            'width' => self::previewWidth($config),
            'margin' => self::previewMargin($config),
        ])->render();
    }

    public static function toHtml(array $config, array $data): string
    {
        $video = self::parse((string) ($config['url'] ?? ''));
        if (! $video) {
            return '';
        }

        return view('filament.forms.components.rich-editor.rich-content-custom-blocks.video.index', [
            'video' => $video,
            'title' => $config['title'] ?? 'YouTube video',
            'caption' => $config['caption'] ?? null,
            'classes' => self::classes('video', $config).($video['portrait'] ? ' is-portrait' : ''),
        ])->render();
    }

    /**
     * The video ID and start time of a YouTube link, or null when it isn't one.
     *
     * @return array{id: string, start: int, portrait: bool, embed: string, thumbnail: string}|null
     */
    public static function parse(string $url): ?array
    {
        $parts = parse_url(trim($url));
        $host = strtolower(preg_replace('/^(www\.|m\.|music\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $id = match (true) {
            $host === 'youtu.be' => trim($path, '/'),
            in_array($host, ['youtube.com', 'youtube-nocookie.com'], true) => preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) ? $m[1] : ($query['v'] ?? ''),
            default => '',
        };
        if (! is_string($id) || ! preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) {
            return null;
        }

        $start = $query['t'] ?? $query['start'] ?? '';
        $seconds = is_string($start) && preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?$/', $start, $t)
            ? ((int) ($t[1] ?? 0)) * 3600 + ((int) ($t[2] ?? 0)) * 60 + (int) ($t[3] ?? 0)
            : 0;

        return [
            'id' => $id,
            'start' => $seconds,
            'portrait' => str_starts_with($path, '/shorts/'),
            'embed' => "https://www.youtube-nocookie.com/embed/{$id}?rel=0".($seconds ? "&start={$seconds}" : ''),
            'thumbnail' => "https://i.ytimg.com/vi/{$id}/hqdefault.jpg",
        ];
    }
}
