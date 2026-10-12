<?php

namespace App\Support;

class MapEmbed
{
    private const HOSTS = ['www.google.com', 'google.com', 'maps.google.com', 'www.google.co.in'];

    /** The iframe src from pasted Google Maps embed code or link; null if it isn't an embeddable Google Maps URL. */
    public static function src(?string $input): ?string
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/\ssrc\s*=\s*["\']([^"\']+)["\']/i', $input, $m)) {
            $input = $m[1];
        }

        $url = html_entity_decode($input);
        $parts = parse_url($url);
        $embeddable = ($parts['scheme'] ?? '') === 'https'
            && in_array(strtolower($parts['host'] ?? ''), self::HOSTS, true)
            && (str_starts_with($parts['path'] ?? '', '/maps/embed') || str_contains($parts['query'] ?? '', 'output=embed'));

        return $embeddable ? $url : null;
    }

    public static function fromAddress(string $address): string
    {
        return 'https://www.google.com/maps?q='.rawurlencode($address).'&output=embed';
    }

    public static function directions(string $address): string
    {
        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($address);
    }
}
