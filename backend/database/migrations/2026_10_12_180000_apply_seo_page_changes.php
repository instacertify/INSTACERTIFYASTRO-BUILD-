<?php

use App\Models\Post;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * October 2026 on-page SEO changes: new titles and descriptions, answer-first openings, extra sections and FAQs.
 * Existing content is kept; new sections are added and only listed sentences are corrected.
 */
return new class extends Migration
{
    private const FILES = ['p1', 'p2', 'p3'];

    private const BACKUP = 'seo-backup-2026-10.json';

    /** Closing headings of a blog post; new sections go above the first one so they stay inside the article. */
    private const CLOSING = '/conclusion|ready for|your path|get your|certify smarter|stay .* compliant|partner with|future of|why expert guidance/i';

    /** Tab topics from ServicePage.astro. A section matching none of them joins the tab of the section before it. */
    private const TOPICS = '/validity|renewal|\bcosts?\b|\bfees?\b|pricing|investment|process|procedure|roadmap|\bsteps?\b|how to apply|journey|timeline|document|dossier|checklist|paperwork|evidence folder|technical file|\bproducts?\b|scope|categor|covered|sectors|overview|what is|understanding|about|why|mandatory|who can apply|who needs/';

    public function up(): void
    {
        $entries = $this->entries();
        $this->backup($entries);

        foreach ($entries as $entry) {
            $entry['type'] === 'post' ? $this->updatePost($entry) : $this->updateService($entry);
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BACKUP)) {
            return;
        }

        $backup = json_decode($disk->get(self::BACKUP), true);
        foreach ($backup['post'] ?? [] as $slug => $original) {
            if ($post = $this->post($slug)) {
                $this->restore($post, $original);
            }
        }
        foreach ($backup['service'] ?? [] as $path => $original) {
            if ($service = Service::firstWhere('path', $path)) {
                $this->restore($service, $original);
            }
        }

        $disk->delete(self::BACKUP);
    }

    /** @return list<array<string, mixed>> */
    private function entries(): array
    {
        return array_merge(...array_map(fn (string $file) => require database_path("data/seo-updates/{$file}.php"), self::FILES));
    }

    private function post(string $slug): ?Post
    {
        return Post::where('type', 'blog')->firstWhere('slug', $slug);
    }

    /** @param  list<array<string, mixed>>  $entries */
    private function backup(array $entries): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists(self::BACKUP)) {
            return;
        }

        $backup = ['post' => [], 'service' => []];
        foreach ($entries as $entry) {
            if ($entry['type'] === 'post' && $post = $this->post($entry['key'])) {
                $backup['post'][$entry['key']] = [
                    'seo' => $post->seo, 'content' => $post->content, 'faqs' => $post->faqs, 'updated_at' => $post->updated_at?->toDateTimeString(),
                ];
            } elseif ($entry['type'] === 'service' && $service = Service::firstWhere('path', $entry['key'])) {
                $backup['service'][$entry['key']] = [
                    'seo' => $service->seo, 'summary' => $service->summary, 'detail' => $service->detail, 'updated_at' => $service->updated_at?->toDateTimeString(),
                ];
            }
        }

        // Fresh test databases have none of these pages; an empty file would block the real backup later.
        if ($backup['post'] || $backup['service']) {
            $disk->put(self::BACKUP, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    /** @param  array<string, mixed>  $original */
    private function restore(Model $model, array $original): void
    {
        $model->forceFill($original);
        $model->timestamps = false;
        $model->save();
    }

    /** @param  array<string, mixed>  $entry */
    private function updatePost(array $entry): void
    {
        $post = $this->post($entry['key']);
        if (! $post) {
            return;
        }

        $post->seo = $this->seo($post->seo, $entry);

        $content = $this->replace($post->content ?? '', $entry['replace'] ?? []);
        $existing = $this->headings($content);
        $answer = $entry['answer'] ?? null;
        if ($answer !== null && ! str_contains($content, $answer)) {
            $content = $answer.$content;
        }

        $afterAnswer = $answer !== null ? strpos($content, $answer) + strlen($answer) : 0;
        $content = substr_replace($content, $this->sectionsHtml($entry['top'] ?? [], $existing), $afterAnswer, 0);
        $content = substr_replace($content, $this->sectionsHtml($entry['sections'] ?? [], $existing), $this->insertAt($content, $entry['before'] ?? null), 0);
        $post->content = $content;

        $faqs = $this->faqs($this->replace($post->faqs ?? [], $entry['replace'] ?? []), $entry);
        if ($faqs || $post->faqs !== null) {
            $post->faqs = $faqs;
        }

        if ($post->isDirty()) {
            $post->save();
        }
    }

    /** @param  array<string, mixed>  $entry */
    private function updateService(array $entry): void
    {
        $service = Service::firstWhere('path', $entry['key']);
        if (! $service) {
            return;
        }

        $service->seo = $this->seo($service->seo, $entry);
        if (isset($entry['summary'])) {
            $service->summary = $entry['summary'];
        }

        $detail = $this->replace($service->detail ?? [], $entry['replace'] ?? []);
        foreach (['lead', 'headline'] as $key) {
            if (isset($entry[$key])) {
                $detail[$key] = $entry[$key];
            }
        }

        // On tabbed pages a section with no topic would land in the tab of the section before it, so those go first (Overview).
        $sections = $detail['sections'] ?? [];
        $existing = array_map(fn (array $s) => $this->normalise($s['heading'] ?? ''), $sections);
        $tabbed = in_array($service->kind, ['certification', 'registration'], true);
        $front = $back = [];
        foreach ($entry['sections'] ?? [] as [$heading, $html]) {
            if (in_array($this->normalise($heading), $existing, true)) {
                continue;
            }
            $existing[] = $this->normalise($heading);
            if ($tabbed && ! preg_match(self::TOPICS, mb_strtolower($heading))) {
                $front[] = ['heading' => $heading, 'html' => $html];
            } else {
                $back[] = ['heading' => $heading, 'html' => $html];
            }
        }
        if ($front || $back) {
            $detail['sections'] = [...$front, ...$sections, ...$back];
        }

        $faqs = $this->faqs($detail['faqs'] ?? [], $entry);
        if ($faqs) {
            $detail['faqs'] = $faqs;
        }

        $service->detail = $detail;
        if ($service->isDirty()) {
            $service->save();
        }
    }

    /**
     * @param  array<string, mixed>|null  $seo
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>|null
     */
    private function seo(?array $seo, array $entry): ?array
    {
        $seo ??= [];
        if (isset($entry['title'])) {
            $seo['title'] = $seo['og_title'] = $entry['title'];
        }
        if (isset($entry['description'])) {
            $seo['description'] = $seo['og_description'] = $entry['description'];
        }
        if (isset($entry['canonical'])) {
            $seo['canonical'] = $entry['canonical'];
        }

        return $seo ?: null;
    }

    /**
     * Existing FAQs first without repeats, then the new questions, then any corrected answers.
     *
     * @param  list<array<string, mixed>>  $faqs
     * @param  array<string, mixed>  $entry
     * @return list<array<string, mixed>>
     */
    private function faqs(array $faqs, array $entry): array
    {
        $new = array_map(fn (array $faq) => ['q' => $faq[0], 'a' => $faq[1]], $entry['faqs'] ?? []);
        $answers = [];
        foreach ($entry['faq_answers'] ?? [] as $question => $answer) {
            $answers[$this->normalise($question)] = $answer;
        }

        $seen = [];
        $out = [];
        foreach ([...$faqs, ...$new] as $faq) {
            $key = $this->normalise($faq['q'] ?? '');
            if ($key !== '' && isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if (isset($answers[$key])) {
                $faq['a'] = $answers[$key];
            }
            $out[] = $faq;
        }

        return $out;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $sections
     * @param  list<string>  $existing
     */
    private function sectionsHtml(array $sections, array &$existing): string
    {
        $html = '';
        foreach ($sections as [$heading, $body]) {
            if (in_array($this->normalise($heading), $existing, true)) {
                continue;
            }
            $existing[] = $this->normalise($heading);
            $html .= '<h2>'.e($heading, false).'</h2>'.$body;
        }

        return $html;
    }

    private function insertAt(string $content, ?string $before): int
    {
        preg_match_all('/<h2\b[^>]*>(.*?)<\/h2>/is', $content, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as $i => [$text]) {
            $text = html_entity_decode(strip_tags($text));
            if ($before !== null ? str_contains($text, $before) : preg_match(self::CLOSING, $text)) {
                return $matches[0][$i][1];
            }
        }

        return strlen($content);
    }

    /** @return list<string> */
    private function headings(string $content): array
    {
        preg_match_all('/<h[23]\b[^>]*>(.*?)<\/h[23]>/is', $content, $matches);

        return array_map($this->normalise(...), $matches[1]);
    }

    private function normalise(string $text): string
    {
        $text = mb_strtolower(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5));

        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }

    /**
     * @param  array<string, string>  $map
     */
    private function replace(mixed $value, array $map): mixed
    {
        if (! $map) {
            return $value;
        }
        if (is_string($value)) {
            return str_replace(array_keys($map), array_values($map), $value);
        }
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) use ($map) {
                if (is_string($item)) {
                    $item = str_replace(array_keys($map), array_values($map), $item);
                }
            });
        }

        return $value;
    }
};
