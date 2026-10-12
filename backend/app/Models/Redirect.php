<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['from_path', 'to_path', 'status_code', 'is_active'])]
class Redirect extends Model
{
    use Audited;

    /** What the build can turn into a server rule (frontend/src/pages/redirects.caddy.ts). */
    public const PATH_PATTERN = '#^/[\w\-./]*$#';

    public const TARGET_PATTERN = '#^(/(?!/)|https?://)\S*$#';

    /** "Type" for an old URL that shows the target page itself (no redirect), as the old site did; the page's canonical tag names the main URL. */
    public const SAME_PAGE = 200;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            $redirect->from_path = self::normalizeFrom($redirect->from_path);
            $redirect->to_path = self::normalizeTo($redirect->to_path);
        });
    }

    /** "old-page/", "/old-page?x", "instacertify.com/old-page" and "https://www.instacertify.com/old-page" all become "/old-page". */
    public static function normalizeFrom(?string $path): string
    {
        $path = preg_replace('#^((https?:)?//[^/]+|(www\.)?instacertify\.com(?=[/?\#]|$))#i', '', trim((string) $path));

        return '/'.trim(parse_url($path, PHP_URL_PATH) ?? '', '/');
    }

    /** Links to this site become relative paths without a trailing slash, so visitors land in one hop. */
    public static function normalizeTo(?string $url): string
    {
        $url = trim((string) $url);
        if (preg_match('#^https?://(?:www\.)?instacertify\.com(?![\w.:-])(.*)$#i', $url, $m)) {
            $url = '/'.ltrim($m[1], '/');
        }
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }
        preg_match('#^([^?\#]*)(.*)$#', $url, $m);

        return '/'.trim($m[1], '/').$m[2];
    }

    /** The page part of an internal target ("/new-page?x=1" → "/new-page"); null for other sites. */
    public static function pathOf(string $url): ?string
    {
        return str_starts_with($url, '/') ? '/'.trim((string) strtok($url, '?#'), '/') : null;
    }

    /**
     * Follows the other redirects from $to to the final URL.
     *
     * @param  array<string, string>  $targets  from_path => to_path of the other active redirects
     * @return string|null null when the chain leads back to $from or loops
     */
    public static function resolve(string $from, string $to, array $targets): ?string
    {
        $seen = [$from => true];
        while (($path = self::pathOf($to)) !== null && isset($targets[$path])) {
            if (isset($seen[$path])) {
                return null;
            }
            $seen[$path] = true;
            $to = $targets[$path];
        }

        return self::pathOf($to) === $from ? null : $to;
    }
}
