<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Support\HtmlString;

/**
 * Google Discover readiness, per https://developers.google.com/search/docs/appearance/google-discover
 * (large ≥1200 px landscape image with max-image-preview:large, non-clickbait headline, visible byline and date).
 */
class Discover
{
    public const MIN_WIDTH = 1200;

    public const MIN_PIXELS = 300_000;

    private const CLICKBAIT = [
        "you won['’]?t believe", 'shocking', 'jaw[- ]dropping', 'mind[- ]blowing', 'blow your mind', 'this one (simple )?trick',
        'what happens next', 'unbelievable', 'insane', 'gone wrong', 'must[- ]see', 'nobody tells you', 'secret they',
        'will shock', 'can[\'’]?t miss', 'click here', 'number \d+ will',
    ];

    /**
     * @return list<array{key: string, label: string, ok: bool, required: bool, hint: string}>
     */
    public static function checks(Post|Event $record): array
    {
        $isPost = $record instanceof Post;
        $width = (int) $record->image_width;
        $height = (int) $record->image_height;
        $title = trim((string) $record->title);
        $summary = trim((string) ($record->excerpt ?: ($record->seo['description'] ?? '')));
        $words = str_word_count(strip_tags((string) $record->content));
        $reused = $record->image && $record::query()->where('image', $record->image)->whereKeyNot($record->getKey())->exists();
        $published = $record->status === 'published' && (! $record->published_at || $record->published_at->isPast());

        $checks = [
            ['image', 'Featured image', (bool) $record->image, true, 'Add a large, relevant photo. Discover shows nothing without one.'],
            ['image_size', 'Image at least 1200 px wide', $width >= self::MIN_WIDTH && $width * $height > self::MIN_PIXELS, true,
                $width ? "Current image is {$width} × {$height} px. Upload one at least 1200 px wide (1600 × 900 px works best)." : 'Upload an image at least 1200 px wide (1600 × 900 px works best).'],
            ['image_landscape', 'Landscape 16:9 image', $width && $height && $width / $height >= 1.3, false, 'Use the crop tool in the image editor to frame the photo as 16:9.'],
            ['image_unique', 'Image unique to this article', $record->image && ! $reused && ! str_contains(strtolower((string) $record->image), 'logo'), false, 'Avoid logos and images reused across articles — Google treats them as generic.'],
            ['image_alt', 'Image description (alt text)', filled($record->image_alt) && $record->image_alt !== $title, false, 'Describe what the photo shows, e.g. "Technician testing a charger in an EMC chamber".'],
            ['headline', 'Clear, non-clickbait headline', self::headlineProblem($title) === null, true, self::headlineProblem($title) ?? ''],
            ['summary', 'Summary of 50–300 characters', mb_strlen($summary) >= 50 && mb_strlen($summary) <= 300, false, 'Write 1–2 plain sentences saying what the reader will learn.'],
            ['published', 'Published with a visible date', $published, true, 'Publish the article (scheduled and draft articles are not shown).'],
            ['indexable', 'Visible to Google', ! str_starts_with((string) ($record->seo['robots'] ?? ''), 'noindex'), true, 'Remove "Hide from Google" in SEO & sharing.'],
        ];

        if ($isPost) {
            $checks[] = ['byline', 'Author byline', (bool) $record->author_id, true, 'Assign an author — Google expects clear authorship.'];
            $checks[] = ['depth', 'At least 300 words', $words >= 300, false, "{$words} words. Discover favours articles that tell the full story or add unique insight."];
        }

        return array_map(fn (array $c) => ['key' => $c[0], 'label' => $c[1], 'ok' => $c[2], 'required' => $c[3], 'hint' => $c[4]], $checks);
    }

    /** Why a headline would hurt in Discover, or null when it is fine. */
    public static function headlineProblem(?string $title): ?string
    {
        $title = trim((string) $title);
        $length = mb_strlen($title);
        $letters = preg_replace('/[^A-Za-z]/', '', $title);

        return match (true) {
            $length < 20 => 'Too short to explain the story — aim for 40–100 characters.',
            $length > 110 => "{$length} characters — Discover cuts long headlines. Aim for 40–100.",
            (bool) preg_match('/'.implode('|', self::CLICKBAIT).'/iu', $title) => 'Reads as clickbait. Say plainly what the article covers.',
            (bool) preg_match('/[!?]{2,}/', $title) => 'Avoid repeated "!" or "?" — Google treats it as sensational.',
            strlen($letters) > 12 && strlen(preg_replace('/[^A-Z]/', '', $letters)) / strlen($letters) > 0.7 => 'Avoid ALL-CAPS headlines.',
            default => null,
        };
    }

    public static function headlineHint(?string $title): string
    {
        $length = mb_strlen(trim((string) $title));

        return self::headlineProblem($title) ?? "{$length} characters. Clear and specific — good for Search and Discover.";
    }

    /** @param  list<array{key: string, label: string, ok: bool, required: bool, hint: string}>  $checks */
    public static function ready(array $checks): bool
    {
        return collect($checks)->every(fn (array $c) => $c['ok'] || ! $c['required']);
    }

    public static function checklist(Post|Event|null $record): HtmlString
    {
        if (! $record?->exists) {
            return new HtmlString('<p class="text-sm text-gray-500">Save to see the Google Discover checklist.</p>');
        }

        $items = collect(self::checks($record))->map(function (array $c) {
            [$icon, $colour] = $c['ok'] ? ['✓', '#059669'] : ($c['required'] ? ['✕', '#dc2626'] : ['!', '#d97706']);
            $hint = $c['ok'] ? '' : '<br><span style="color:#6b7280">'.e($c['hint']).'</span>';

            return "<li style=\"display:flex;gap:.5rem;margin:.35rem 0\"><span style=\"color:{$colour};font-weight:700;width:1rem\">{$icon}</span><span>".e($c['label'])."{$hint}</span></li>";
        })->implode('');

        return new HtmlString('<ul style="font-size:.875rem;line-height:1.35">'.$items.'</ul>');
    }
}
