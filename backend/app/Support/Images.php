<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Images
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('instacertify.media_disk'));
    }

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://', '//']) ? $path : self::disk()->url($path);
    }

    /** Turns every uploaded tile icon ("iconImage") in a block of page content into a public URL. */
    public static function iconUrls(mixed $value): mixed
    {
        return self::urlsFor($value, ['iconImage']);
    }

    /**
     * Turns every uploaded file stored under one of the given keys, at any depth of a block of page content, into a public URL.
     *
     * @param  list<string>  $keys
     */
    public static function urlsFor(mixed $value, array $keys): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = in_array($key, $keys, true) && (is_string($item) || $item === null) ? self::url($item) : self::urlsFor($item, $keys);
        }

        return $value;
    }

    /**
     * Pixel size of a stored image, or null for remote/unreadable/vector files.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(?string $path): ?array
    {
        if (blank($path) || Str::startsWith($path, ['http://', 'https://', '//']) || ! self::disk()->exists($path)) {
            return null;
        }

        $size = @getimagesizefromstring((string) self::disk()->get($path));

        return $size ? [$size[0], $size[1]] : null;
    }

    /**
     * Re-encode raster images as WebP (max 1600px wide). SVG/GIF and unreadable files are stored as-is.
     *
     * @return string Stored path relative to the media disk
     */
    public static function store(string $binary, string $directory, string $name, int $maxWidth = 1600): string
    {
        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: Str::random(8);
        $webp = self::toWebp($binary, $maxWidth);

        if ($webp === null) {
            $extension = self::extensionFor($binary);
            if ($extension === 'svg') {
                $binary = self::cleanSvg($binary);
            }
            $path = self::freePath(trim($directory, '/')."/{$base}", $extension);
            self::disk()->put($path, $binary, 'public');

            return $path;
        }

        $path = self::freePath(trim($directory, '/')."/{$base}", 'webp');
        self::disk()->put($path, $webp, 'public');

        return $path;
    }

    /**
     * The extension comes from the file's content, never its name: media is public, and a file named
     * shell.php that only looks like a GIF must not be saved as something the server could run.
     */
    private static function extensionFor(string $binary): string
    {
        return match ((new \finfo(FILEINFO_MIME_TYPE))->buffer($binary)) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/svg+xml', 'image/svg' => 'svg',
            default => str_starts_with(ltrim($binary), '<') && preg_match('#<svg\b#i', $binary) ? 'svg' : 'bin',
        };
    }

    /** Two uploads with the same file name must not overwrite each other. */
    private static function freePath(string $base, string $extension): string
    {
        $path = "{$base}.{$extension}";
        while (self::disk()->exists($path)) {
            $path = "{$base}-".Str::lower(Str::random(6)).".{$extension}";
        }

        return $path;
    }

    /** SVGs are served from our own domain, so scripts, event handlers and external references are removed. */
    public static function cleanSvg(string $svg): string
    {
        $svg = preg_replace('#<(script|foreignObject)\b.*?</\1\s*>#is', '', $svg) ?? '';
        $svg = preg_replace('#<(script|foreignObject)\b[^>]*/?>#i', '', $svg) ?? '';
        $svg = preg_replace('#<(iframe|embed|object|handler|listener)\b.*?(</\1\s*>|/>)#is', '', $svg) ?? '';
        $svg = preg_replace('#[\s/]+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', ' ', $svg) ?? '';
        // Links and animations may not point at scripts or embedded documents, including entity-encoded ones like &#106;avascript:.
        $svg = preg_replace('#\s+(?:xlink:)?(?:href|values|to|from)\s*=\s*("[^"]*(?:javascript|data:(?!image/(?:png|jpe?g|gif|webp))|&\#)[^"]*"|\'[^\']*(?:javascript|data:(?!image/(?:png|jpe?g|gif|webp))|&\#)[^\']*\')#i', '', $svg) ?? '';

        return $svg;
    }

    /**
     * Store a favicon: ICO files as-is, other images trimmed of transparent edges and fitted (never cropped)
     * into a square transparent PNG of at most 512px.
     *
     * @return string|null Stored path relative to the media disk, or null when the file is not a usable image
     */
    public static function storeFavicon(string $binary, string $directory = 'branding'): ?string
    {
        $name = trim($directory, '/').'/favicon-'.Str::lower(Str::random(8));

        if (str_starts_with($binary, "\x00\x00\x01\x00")) {
            self::disk()->put("{$name}.ico", $binary, 'public');

            return "{$name}.ico";
        }

        if (str_starts_with(ltrim($binary), '<') || ! function_exists('imagepng')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        imagepalettetotruecolor($image);
        if (max(imagesx($image), imagesy($image)) > 1024) {
            $ratio = 1024 / max(imagesx($image), imagesy($image));
            $smaller = imagescale($image, max(1, (int) round(imagesx($image) * $ratio)), max(1, (int) round(imagesy($image) * $ratio)));
            if ($smaller !== false) {
                imagedestroy($image);
                $image = $smaller;
            }
        }
        [$x, $y, $w, $h] = self::opaqueBounds($image);
        $side = max($w, $h);
        $size = min($side, 512);
        $scale = $size / $side;
        $square = imagecreatetruecolor($size, $size);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 0, 0, 0, 127));
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        imagecopyresampled($square, $image, intdiv($size - $dw, 2), intdiv($size - $dh, 2), $x, $y, $dw, $dh, $w, $h);
        imagedestroy($image);

        ob_start();
        imagepng($square, null, 9);
        $png = (string) ob_get_clean();
        imagedestroy($square);

        self::disk()->put("{$name}.png", $png, 'public');

        return "{$name}.png";
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, width and height of the visible (not fully transparent) area
     */
    private static function opaqueBounds(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        [$left, $top, $right, $bottom] = [$width, $height, -1, -1];

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ((imagecolorat($image, $x, $y) >> 24 & 0x7F) < 127) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return $right < 0 ? [0, 0, $width, $height] : [$left, $top, $right - $left + 1, $bottom - $top + 1];
    }

    /**
     * Store a client logo with its empty margins (transparent or near-white) cut off, so every logo
     * fills the same height in the logo strip. Logos on a coloured background are kept whole.
     *
     * @return string Stored path relative to the media disk
     */
    public static function storeLogo(string $binary, string $directory, string $name): string
    {
        return self::storeTrimmed($binary, $directory, $name, maxWidth: 800, maxHeight: 160);
    }

    /**
     * Store a tile icon with its empty margins (transparent or near-white) cut off, so the artwork fills the
     * icon's box on the website. SVGs are stored as drawn.
     *
     * @return string Stored path relative to the media disk
     */
    public static function storeIcon(string $binary, string $directory, string $name): string
    {
        return self::storeTrimmed($binary, $directory, $name, maxWidth: 256, maxHeight: 256);
    }

    private static function storeTrimmed(string $binary, string $directory, string $name, int $maxWidth, int $maxHeight): string
    {
        $image = str_starts_with(ltrim($binary), '<') || str_starts_with($binary, 'GIF') || ! function_exists('imagewebp') ? false : @imagecreatefromstring($binary);
        if ($image === false) {
            return self::store($binary, $directory, $name, $maxWidth);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        if (max(imagesx($image), imagesy($image)) > 1200) {
            $ratio = 1200 / max(imagesx($image), imagesy($image));
            $smaller = imagescale($image, max(1, (int) round(imagesx($image) * $ratio)), max(1, (int) round(imagesy($image) * $ratio)));
            if ($smaller !== false) {
                imagedestroy($image);
                $image = $smaller;
            }
        }

        [$x, $y, $w, $h] = self::marginBounds($image);
        $scale = min(1, $maxHeight / $h, $maxWidth / $w);
        $dw = max(1, (int) round($w * $scale));
        $dh = max(1, (int) round($h * $scale));
        $logo = imagecreatetruecolor($dw, $dh);
        imagealphablending($logo, false);
        imagesavealpha($logo, true);
        imagefill($logo, 0, 0, imagecolorallocatealpha($logo, 0, 0, 0, 127));
        imagecopyresampled($logo, $image, 0, 0, $x, $y, $dw, $dh, $w, $h);
        imagedestroy($image);

        ob_start();
        imagewebp($logo, null, 90);
        $webp = (string) ob_get_clean();
        imagedestroy($logo);

        $base = Str::slug(pathinfo($name, PATHINFO_FILENAME)) ?: Str::random(8);
        $path = self::freePath(trim($directory, '/')."/{$base}", 'webp');
        self::disk()->put($path, $webp, 'public');

        return $path;
    }

    /**
     * The area inside the image's empty margin. The margin colour is taken from the corners and only counts
     * when they are all transparent, or all the same near-white colour.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, width and height
     */
    private static function marginBounds(\GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $whole = [0, 0, $width, $height];
        $rgba = fn (int $c) => [$c >> 16 & 255, $c >> 8 & 255, $c & 255, $c >> 24 & 0x7F];
        $corners = array_map($rgba, [
            imagecolorat($image, 0, 0), imagecolorat($image, $width - 1, 0),
            imagecolorat($image, 0, $height - 1), imagecolorat($image, $width - 1, $height - 1),
        ]);

        $transparent = collect($corners)->every(fn (array $c) => $c[3] >= 120);
        $white = collect($corners)->every(fn (array $c) => $c[3] < 8 && min($c[0], $c[1], $c[2]) >= 235);
        if (! $transparent && ! $white) {
            return $whole;
        }

        $isMargin = $transparent
            ? fn (array $c) => $c[3] >= 120
            : fn (array $c) => $c[3] >= 120 || min($c[0], $c[1], $c[2]) >= 235;
        [$left, $top, $right, $bottom] = [$width, $height, -1, -1];
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if (! $isMargin($rgba(imagecolorat($image, $x, $y)))) {
                    $left = min($left, $x);
                    $right = max($right, $x);
                    $top = min($top, $y);
                    $bottom = max($bottom, $y);
                }
            }
        }

        return $right < 0 ? $whole : [$left, $top, $right - $left + 1, $bottom - $top + 1];
    }

    public static function toWebp(string $binary, int $maxWidth = 1600, int $quality = 80): ?string
    {
        if (! function_exists('imagewebp') || str_starts_with(ltrim($binary), '<') || str_starts_with($binary, 'GIF')) {
            return null;
        }

        $image = @imagecreatefromstring($binary);
        if ($image === false) {
            return null;
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        if (imagesx($image) > $maxWidth) {
            $scaled = imagescale($image, $maxWidth);
            imagedestroy($image);
            $image = $scaled;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $quality);
        $data = ob_get_clean();
        imagedestroy($image);

        return $data ?: null;
    }
}
