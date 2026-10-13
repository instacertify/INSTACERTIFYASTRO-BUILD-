<?php

namespace App\Support;

/**
 * Removes click-tracking parameters from links pasted into content, e.g. "?utm_source=chatgpt.com" that ChatGPT
 * adds to every link it cites, or Facebook's "fbclid". Other query parameters are kept.
 */
class TrackingParams
{
    private const PARAMS = 'utm_[a-z_]+|fbclid|gclid|gbraid|wbraid|msclkid|dclid|yclid|mc_cid|mc_eid|_hsenc|_hsmi|igshid|srsltid';

    /** Share tags social apps add to profile links ("?s=11" from the X app, "?igsh=" from Instagram). */
    private const SHARE_PARAMS = 's|si|t|igsh|rdid';

    /** Cleans every link in a string (plain URL or HTML) or in a nested array of strings. */
    public static function clean(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => self::clean($item), $value);
        }
        if (! is_string($value) || ! str_contains($value, '://') || ! preg_match('#[?&;]('.self::PARAMS.')=#i', $value)) {
            return $value;
        }

        return preg_replace_callback('#https?://[^\s"\'<>]+#i', fn (array $m) => self::url($m[0]), $value);
    }

    /** A social profile link without tracking or share tags. */
    public static function profileUrl(?string $url): ?string
    {
        return blank($url) ? $url : self::url(self::url($url), self::SHARE_PARAMS);
    }

    private static function url(string $url, string $params = self::PARAMS): string
    {
        // Works on raw links and on links inside HTML, where "&" is written "&amp;".
        $clean = preg_replace('#(?<=[?&;])(?:'.$params.')=[^&\#\s"\'<>]*(?:&amp;|&)?#i', '', $url);

        return preg_replace('#(?:\?|&amp;|&)+(?=\#|$)#', '', $clean);
    }
}
