<?php

namespace App\Support\Legacy;

use App\Support\Images;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Downloads legacy images once, re-encodes them as WebP into the media disk and remembers
 * old URL => new path in storage/app/legacy/media-map.json so re-runs are instant.
 */
class MediaImporter
{
    /** @var array<string, string|false> */
    private array $map = [];

    private string $mapFile;

    public function __construct(
        private readonly string $siteUrl,
        private readonly string $apiOrigin,
        private readonly bool $enabled = true,
    ) {
        $this->mapFile = storage_path('app/legacy/media-map.json');
        $this->map = File::exists($this->mapFile) ? json_decode(File::get($this->mapFile), true) : [];
    }

    public function absolute(string $src): string
    {
        // Some legacy posts hold the site origin twice: "https://instacertify.comhttps://instacertify.com/wp-content/...".
        $src = preg_replace('#^https?://[^/]+(?=https?://)#i', '', trim($src));
        if (Str::startsWith($src, '//')) {
            return 'https:'.$src;
        }
        if (Str::startsWith($src, ['http://', 'https://'])) {
            return $src;
        }

        $origin = Str::startsWith($src, '/storage/') ? $this->apiOrigin : $this->siteUrl;

        return rtrim($origin, '/').'/'.ltrim($src, '/');
    }

    /** @param  iterable<string>  $sources */
    public function prefetch(iterable $sources): int
    {
        if (! $this->enabled) {
            return 0;
        }

        $pending = collect($sources)->filter()->map(fn ($s) => $this->absolute($s))
            ->unique()->reject(fn ($url) => array_key_exists($url, $this->map) || ! $this->isImage($url))->values();

        foreach ($pending->chunk(8) as $chunk) {
            $responses = Http::pool(fn (Pool $pool) => $chunk->map(
                fn ($url) => $pool->as($url)->timeout(30)->withUserAgent('InstacertifyMigration/1.0')->get($url)
            )->all());

            foreach ($responses as $url => $response) {
                $this->map[$url] = ($response instanceof Response && $response->successful())
                    ? Images::store($response->body(), 'legacy/'.$this->folderFor($url), basename(parse_url($url, PHP_URL_PATH)))
                    : false;
            }

            $this->save();
        }

        return $pending->count();
    }

    /** Download every <img> referenced in the given HTML fragments in parallel batches. */
    public function prefetchHtml(iterable $fragments): int
    {
        $sources = [];
        foreach ($fragments as $html) {
            if (is_string($html) && preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
                array_push($sources, ...$m[1]);
            }
        }

        return $this->prefetch($sources);
    }

    /** New public URL for a legacy image (downloads it if needed); falls back to the original URL. */
    public function url(?string $src): ?string
    {
        if (! self::looksLikeImageSource($src)) {
            return null;
        }

        $path = $this->path($src);

        return $path ? Images::url($path) : $this->absolute($src);
    }

    /** Stored path on the media disk, or null if unavailable. */
    public function path(?string $src): ?string
    {
        if (blank($src) || ! $this->enabled) {
            return null;
        }

        $url = $this->absolute($src);
        if (! array_key_exists($url, $this->map)) {
            $this->prefetch([$url]);
        }

        return ($this->map[$url] ?? null) ?: null;
    }

    /** Legacy records sometimes hold "0" or a bare word instead of an image path. */
    public static function looksLikeImageSource(?string $src): bool
    {
        return filled($src) && (bool) preg_match('#[/.]#', $src);
    }

    private function isImage(string $url): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|webp|gif|svg|avif)(\?.*)?$/i', $url);
    }

    private function folderFor(string $url): string
    {
        return preg_match('#/(20\d{2})/(\d{2})/#', $url, $m) ? "{$m[1]}/{$m[2]}" : 'misc';
    }

    private function save(): void
    {
        File::ensureDirectoryExists(dirname($this->mapFile));
        File::put($this->mapFile, json_encode($this->map, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
