<?php

namespace App\Support\Standards;

/**
 * Indian Standard numbers are written many ways ("IS 302 (Part 2/Sec 9): 2009", "302-2-9", "IS 302 : PART 2").
 * key() reduces them to "302-p2-s9" so rows from BIS lists, lab scopes and our own pages can be matched.
 */
class IsNumber
{
    public static function key(?string $value): ?string
    {
        $parts = self::parse($value);

        return $parts ? $parts['base'].($parts['part'] ? "-p{$parts['part']}" : '').($parts['sec'] ? "-s{$parts['sec']}" : '') : null;
    }

    /** "IS 302 (Part 2/Sec 9)", keeping an IS/IEC or IS/ISO prefix when the input had one. */
    public static function format(?string $value): ?string
    {
        $parts = self::parse($value);
        if (! $parts) {
            return null;
        }
        $suffix = collect([$parts['part'] ? "Part {$parts['part']}" : null, $parts['sec'] ? "Sec {$parts['sec']}" : null])->filter()->implode('/');

        return trim("{$parts['prefix']} {$parts['base']}".($suffix ? " ({$suffix})" : ''));
    }

    /** @return array{prefix: string, base: string, part: ?string, sec: ?string}|null */
    private static function parse(?string $value): ?array
    {
        $s = strtoupper(str_replace(['–', '—', '‐', '−'], '-', (string) $value));
        $prefix = 'IS';
        if (preg_match('/^\s*IS\s*\/\s*(IEC|ISO)\s*/', $s, $m)) {
            $prefix = "IS/{$m[1]}";
            $s = substr($s, strlen($m[0]));
        } else {
            $s = preg_replace('/^\s*IS\b\s*:?\s*/', '', $s);
        }
        if (! preg_match('/^\s*(\d+)/', $s, $m)) {
            return null;
        }

        $base = ltrim($m[1], '0') ?: '0';
        $rest = preg_split('/,|\/\s*(?:ISO|IEC)\b|\s(?:IEC|ISO)\s/', substr(ltrim($s), strlen($m[1])))[0];
        $rest = preg_replace('/\(\s*(?:19|20)\d\d\s*\)|:\s*(?:19|20)\d{2,3}\b/', ' ', $rest);

        $part = $sec = null;
        if (preg_match('/^\s*-\s*(\d+)(?:\s*-\s*(\d+))?/', $rest, $m)) {
            [$part, $sec] = [$m[1], $m[2] ?? null];
        } else {
            $part = preg_match('/PARTS?\s*[-:.]?\s*(\d+)/', $rest, $m) ? $m[1] : null;
            $sec = preg_match('/SEC(?:TION)?\s*[-:.]?\s*(\d+)/', $rest, $m) ? $m[1] : null;
        }

        return ['prefix' => $prefix, 'base' => $base, 'part' => $part ? ltrim($part, '0') : null, 'sec' => $sec ? ltrim($sec, '0') : null];
    }
}
