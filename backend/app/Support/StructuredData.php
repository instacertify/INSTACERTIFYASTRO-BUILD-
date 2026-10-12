<?php

namespace App\Support;

/** Custom JSON-LD that editors paste into a page's "Rich snippets" field. */
class StructuredData
{
    /** The automatic markup stays and the custom markup is added after it. */
    public const ADD = 'add';

    /** Only the custom markup is published (the site-wide organisation markup always stays). */
    public const REPLACE = 'replace';

    public const MAX_LENGTH = 20000;

    /** Google treats review stars a business publishes about itself as spam, and the site runs Google Ads. */
    private const BANNED = ['review', 'aggregateRating', 'Review', 'AggregateRating'];

    /** Removes a pasted <script type="application/ld+json"> wrapper and pretty-prints valid JSON. */
    public static function normalize(?string $markup): ?string
    {
        $markup = trim(preg_replace('#^\s*<script\b[^>]*>|</script>\s*$#i', '', (string) $markup));
        if ($markup === '') {
            return null;
        }

        $decoded = json_decode($markup, true);

        return is_array($decoded) ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $markup;
    }

    /** Why the markup cannot be published, or null when it can. */
    public static function problem(?string $markup): ?string
    {
        $markup = self::normalize($markup);
        if ($markup === null) {
            return null;
        }
        if (mb_strlen($markup) > self::MAX_LENGTH) {
            return 'Keep the markup under '.number_format(self::MAX_LENGTH).' characters.';
        }

        $decoded = json_decode($markup, true);
        if (! is_array($decoded) || $decoded === []) {
            return 'This is not valid JSON: '.(json_last_error() ? json_last_error_msg() : 'expected { … } or [ { … } ]').'. Paste the JSON-LD exactly as a generator or Google gives it.';
        }

        foreach (self::items($decoded) as $item) {
            if (! is_array($item) || array_is_list($item)) {
                return 'Each item must be an object like { "@type": "FAQPage", … }.';
            }
            if (! isset($item['@type']) && ! isset($item['@graph'])) {
                return 'Each item needs an "@type", for example "FAQPage", "Service" or "HowTo".';
            }
        }

        if ($banned = self::findBanned($decoded)) {
            return "Remove \"{$banned}\": Google does not allow review stars a business adds about itself, and they can get the site penalised.";
        }

        return null;
    }

    /**
     * The markup as a list of schema.org objects for the website, or an empty list when it is missing or invalid.
     *
     * @return list<array<string, mixed>>
     */
    public static function decode(?string $markup): array
    {
        if (self::problem($markup) !== null || ($markup = self::normalize($markup)) === null) {
            return [];
        }

        return array_map(fn (array $item) => ['@context' => 'https://schema.org', ...$item], self::items(json_decode($markup, true)));
    }

    /**
     * What the website needs from a saved SEO panel: how to combine the markup and the markup itself.
     *
     * @param  array<string, mixed>  $seo
     * @return array{schemaMode?: string, schema?: list<array<string, mixed>>}
     */
    public static function forApi(array $seo): array
    {
        $schema = self::decode($seo['schema'] ?? null);

        return $schema === [] ? [] : [
            'schemaMode' => ($seo['schema_mode'] ?? null) === self::REPLACE ? self::REPLACE : self::ADD,
            'schema' => $schema,
        ];
    }

    /** @param  array<mixed>  $decoded */
    private static function items(array $decoded): array
    {
        return array_is_list($decoded) ? $decoded : [$decoded];
    }

    /** @param  array<mixed>  $data */
    private static function findBanned(array $data): ?string
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::BANNED, true) || (in_array($value, self::BANNED, true) && $key === '@type')) {
                return is_string($key) && $key !== '@type' ? $key : $value;
            }
            if (is_array($value) && ($found = self::findBanned($value))) {
                return $found;
            }
        }

        return null;
    }
}
