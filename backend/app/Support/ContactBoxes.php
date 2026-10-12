<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Wording on and around the enquiry boxes (Website → Contact boxes), stored as `contactBoxes` in the
 * site settings. Defaults come from content/contact-boxes.json, which the website also uses when it is
 * built without the API.
 */
class ContactBoxes
{
    /**
     * The shipped wording, with the response promise from Site settings → Contact as the second
     * point of the bottom-of-page box.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $defaults = once(function () {
            $file = rtrim((string) config('instacertify.content_path'), '/\\').'/contact-boxes.json';

            return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        });
        $promise = Setting::get('site', [])['contact']['responseTime'] ?? null;
        if (filled($promise) && isset($defaults['cta']['points'][1])) {
            $defaults['cta']['points'][1] = trim($promise);
        }

        return $defaults;
    }

    /** @return array<string, array<string, mixed>> */
    public static function saved(): array
    {
        return Setting::get('site', [])['contactBoxes'] ?? [];
    }

    /**
     * Saved wording over the defaults: an empty text field falls back to the default wording,
     * while a saved list (e.g. bullet points) replaces the default list, even when emptied.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function settings(): array
    {
        return self::merge(self::defaults(), self::saved());
    }

    /**
     * Saved wording with the default lists filled in, for the admin form: text fields stay empty
     * so the default shows in grey, lists start with the default items so they can be edited.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function forForm(): array
    {
        $saved = self::saved();
        foreach (self::defaults() as $box => $fields) {
            foreach ($fields as $key => $default) {
                if (is_array($default) && ! is_array($saved[$box][$key] ?? null)) {
                    $saved[$box][$key] = $default;
                }
            }
        }

        return $saved;
    }

    /** The default wording for one field, shown in grey in the admin. */
    public static function default(string $box, string $key): ?string
    {
        $value = self::defaults()[$box][$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>
     */
    private static function merge(array $defaults, array $saved): array
    {
        foreach ($saved as $key => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                continue;
            }
            $default = $defaults[$key] ?? null;
            if (is_array($value) && array_is_list($value)) {
                $value = array_values(array_filter($value, fn ($item) => is_array($item) ? filled($item['title'] ?? null) : filled($item)));
            }
            $defaults[$key] = is_array($value) && is_array($default) && ! array_is_list($default)
                ? self::merge($default, $value)
                : (is_string($value) ? trim($value) : $value);
        }

        return $defaults;
    }
}
