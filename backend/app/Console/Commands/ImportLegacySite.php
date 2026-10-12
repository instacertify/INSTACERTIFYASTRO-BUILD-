<?php

namespace App\Console\Commands;

use App\Models\Author;
use App\Models\Category;
use App\Models\Event;
use App\Models\Faq;
use App\Models\Industry;
use App\Models\LandingPage;
use App\Models\Office;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\Testimonial;
use App\Support\Legacy\HtmlCleaner;
use App\Support\Legacy\MediaImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

#[Signature('legacy:import
    {--only=* : Limit to sections: services, standards, posts, events, pages, rendered, faqs, testimonials, industries, settings}
    {--path=* : With --only=pages or --only=posts, import just these legacy paths}
    {--refresh : Re-download listings from the legacy API instead of using the local cache}
    {--skip-media : Keep legacy image URLs instead of downloading them}')]
#[Description('Import content from the legacy instacertify.com API (read-only) into this backend')]
class ImportLegacySite extends Command
{
    private const SECTIONS = ['services', 'standards', 'posts', 'events', 'pages', 'rendered', 'faqs', 'testimonials', 'industries', 'settings'];

    /** Legacy pages whose content only exists in the live site's rendered HTML (hard-coded in the old frontend). */
    private const RENDERED_PAGES = ['about' => 'default', 'trust' => 'default', 'cookie-policy' => 'policy', 'data-protection' => 'policy', 'security' => 'policy'];

    /** Empty legacy CMS stubs for routes the new frontend builds itself from structured content. */
    private const GENERATED_PATHS = ['contact', 'faq', 'resources/standards', 'resources/update', 'sitemap', 'html-sitemap'];

    /** Legacy URLs with no API record (deleted pages, typo'd links in page content) and where they should go. */
    private const MANUAL_ALIASES = [
        'face-cream-testing' => 'services/testing/cosmetics-testing',
        'services/indian-registration-compliances/bis-certification/fmcs' => 'certification/india/bis-isi-mark-fmcs',
        'certification/india/cdsco-registration' => 'certification/india/cdsco-medical-device',
        'certification/india/fssai-registration' => 'services/indian-registration-compliances/business-licenses/fssai-basic-registration',
    ];

    /** Headings of call-to-action blocks in scraped pages; the new layout renders its own CTAs. */
    private const CTA_HEADING = '/^(ready to|still have questions|contact us directly|need compliance help|get free consultation|request a free callback|have questions)/i';

    private const POST_ENDPOINTS = ['blog' => 'posts', 'news' => 'news', 'audit' => 'audits', 'update' => 'updates'];

    private const SERVICE_TEMPLATES = ['service-v1', 'service-v2', 'service-grid', 'assurance'];

    private const PAGE_TEMPLATES = ['policy' => 'policy', 'default' => 'default', 'landing' => 'landing', 'parent-grid' => 'hub'];

    private const REGIONS = ['india' => 'india', 'asia' => 'asia', 'emea' => 'emea', 'americas' => 'americas', 'global' => 'global', 'global-certifications' => 'global'];

    private string $cacheDir;

    private MediaImporter $media;

    private HtmlCleaner $html;

    /** @var array<string, string> every known public path, keyed by its last segment, for fixing legacy links */
    private array $knownPaths = [];

    /** @var array<string, string> legacy path => new path, checked before */
    private array $pathAliases = [];

    /** @var array<string, string> legacy links rewritten during the import; stored as 301 redirects */
    private array $redirects = [];

    public function handle(): int
    {
        $this->cacheDir = storage_path('app/legacy');
        File::ensureDirectoryExists($this->cacheDir.'/pages');

        $site = rtrim(config('instacertify.legacy.site_url'), '/');
        $api = rtrim(config('instacertify.legacy.api_url'), '/');
        $apiOrigin = preg_replace('#/api/?$#', '', $api);

        $this->media = new MediaImporter($site, $apiOrigin, enabled: ! $this->option('skip-media'));
        $this->html = new HtmlCleaner(
            [parse_url($site, PHP_URL_HOST), preg_replace('/^www\./', '', parse_url($site, PHP_URL_HOST))],
            fn (string $src) => $this->media->url($src),
        );

        $only = array_filter(array_map('trim', explode(',', implode(',', $this->option('only')))));
        $sections = $only ?: self::SECTIONS;
        if ($unknown = array_diff($sections, self::SECTIONS)) {
            $this->error('Unknown section(s): '.implode(', ', $unknown));

            return self::FAILURE;
        }

        activity()->disableLogging();
        $this->rememberKnownPaths();

        foreach (self::SECTIONS as $section) {
            if (in_array($section, $sections, true)) {
                $this->components->task(Str::headline($section), fn () => $this->{'import'.Str::studly($section)}());
            }
        }

        $this->components->task('Redirects for renamed legacy URLs', fn () => $this->storeRedirects());
        $this->components->task('Canonical URLs pointing at missing pages', fn () => $this->pruneCanonicals());
        $this->call('discover:refresh');

        activity()->enableLogging();
        $this->newLine();
        $this->components->info('Legacy import finished. Review drafts in the admin before going live.');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- services

    private function importServices(): void
    {
        $legacy = collect($this->fetch('services'));
        $byTitle = $legacy->keyBy(fn ($s) => $this->norm($s['title']));
        $bySlug = $legacy->keyBy('slug');
        $pages = $this->pagesOf(self::SERVICE_TEMPLATES);
        $sort = (int) Service::max('sort');

        $this->media->prefetch($legacy->pluck('icon')->merge($pages->pluck('featured_image')));
        $this->media->prefetchHtml($pages->flatMap(fn ($p) => [$p['content'] ?? '', ...Arr::flatten($p['page_data'] ?? [])]));

        $matched = [];
        foreach ($pages as $page) {
            $svc = $byTitle[$this->norm($page['title'])] ?? $bySlug[basename($this->relPath($page['path']))] ?? null;
            if ($svc) {
                $matched[$svc['id']] = true;
                $this->pathAliases[$this->resolveMenuPath($svc['slug']) ?? 'services/'.$svc['slug']] = $this->relPath($page['path']);
            }
        }

        // Services that exist in the legacy catalogue and mega menu but never got a page of their own.
        foreach ($legacy->reject(fn ($s) => isset($matched[$s['id']])) as $svc) {
            if (($path = $this->resolveMenuPath($svc['slug'])) && ! $pages->contains(fn ($p) => $this->relPath($p['path']) === $path)) {
                $pages->push([...$svc, 'id' => $svc['id'], 'path' => $path, 'featured_image' => $svc['featured_image'] ?? null]);
            }
        }

        foreach ($pages as $page) {
            $path = $this->relPath($page['path']);
            $svc = $byTitle[$this->norm($page['title'])] ?? $bySlug[basename($path)] ?? null;

            $source = $page;
            if (empty($page['page_data']) && blank($page['content']) && $svc) {
                $source = [...$page, 'content' => $svc['content'] ?? null, 'page_data' => $svc['page_data'] ?? []];
            }

            [$kind, $region, $group] = $this->classify($path, $page['title'], $svc);
            $detail = $this->serviceDetail($source);
            $existing = Service::firstWhere('path', $path);
            $curated = File::exists(config('instacertify.content_path').'/services/'.basename($path).'.json');

            $summary = $existing?->summary
                ?: $this->text($svc['excerpt'] ?? null)
                ?: Str::limit($this->text($source['page_data']['hero_description'] ?? $source['page_data']['meta_description'] ?? $page['seo_meta_description'] ?? $page['content']), 220);

            $service = $existing ?? new Service(['path' => $path, 'sort' => ++$sort, 'is_featured' => false]);
            $service->fill([
                'legacy_id' => $svc['id'] ?? $page['id'],
                'slug' => basename($path),
                'title' => $this->tidyTitle($existing?->title ?: $page['title']),
                'kind' => $existing?->kind ?: $kind,
                'region' => $existing?->region ?: $region,
                'group' => $existing?->group ?: $group,
                'summary' => $summary ?: $page['title'],
                'icon' => $existing?->icon ?: $this->mediaPath($svc['icon'] ?? null),
                'image' => $existing?->image ?: $this->mediaPath($page['featured_image'] ?? $svc['featured_image'] ?? null),
                'detail' => $curated && $existing?->detail ? $existing->detail : $detail,
                'seo' => $existing?->seo ?: $this->seo($page, $path) ?? ($svc ? $this->seo($svc, $path) : null),
                'is_published' => ($page['status'] ?? 'published') === 'published',
            ]);
            $service->save();
            if (! $curated) {
                $this->keepLegacyTimestamp($service, $page);
            }
            $this->knownPaths[basename($path)] ??= $path;
        }

        Service::query()->each(function (Service $service) {
            $related = collect($service->detail['related'] ?? [])->map(fn ($p) => $this->resolvePath($p))->filter()
                ->reject(fn ($p) => $p === $service->path)->unique()->values()->all();
            if (($service->detail['related'] ?? null) !== null && $related !== $service->detail['related']) {
                $service->update(['detail' => [...$service->detail, 'related' => $related]]);
            }
        });
    }

    private function classify(string $path, string $title, ?array $svc): array
    {
        $seg = explode('/', $path);
        $category = Str::lower($svc['category'] ?? '');

        $kind = match (true) {
            $seg[0] === 'certification' => 'certification',
            Str::startsWith($path, 'services/testing/') => 'testing',
            Str::startsWith($path, 'services/indian-registration-compliances/') => 'registration',
            Str::startsWith($path, ['services/qualitiy/', 'services/quality/']) => 'quality',
            $category === 'certifications' => 'certification',
            $category === 'registrations' => 'registration',
            $category === 'quality' => 'quality',
            str_contains($category, 'testing') || str_contains($category, 'conformance') => 'testing',
            (bool) preg_match('/\btest(ing)?\b/i', $title) => 'testing',
            default => 'certification',
        };

        $region = match (true) {
            $seg[0] === 'certification' && count($seg) > 2 => self::REGIONS[$seg[1]] ?? null,
            filled($svc['region'] ?? null) => self::REGIONS[Str::lower($svc['region'])] ?? null,
            $kind === 'registration' => 'india',
            default => null,
        };

        $group = match (true) {
            $kind === 'registration' && count($seg) > 3 => $seg[2],
            $kind === 'testing' && filled($category) => Str::slug(str_replace('/', '-', $category)),
            default => null,
        };

        return [$kind, $region, array_key_exists((string) $group, Service::GROUPS) ? $group : null];
    }

    /** Maps both legacy page shapes (structured page_data or HTML sections) onto our service detail schema. */
    private function serviceDetail(array $page): array
    {
        $pd = $page['page_data'] ?? [];
        $sh = $pd['sections_html'] ?? [];
        $titles = $pd['section_titles'] ?? [];

        $sections = [];
        $faqs = $this->faqs($pd['faq_items'] ?? []);
        $timeline = $this->timeline($pd['timeline_items'] ?? []);

        foreach ($pd['sections'] ?? [] as $s) {
            $type = $s['type'] ?? 'content';
            if ($type === 'faq') {
                $faqs = [...$faqs, ...$this->faqs($s['faq'] ?? [])];
            } elseif ($type === 'timeline') {
                $timeline = [...$timeline, ...$this->timeline($s['timeline'] ?? [])];
            } elseif ($html = $this->html->clean($s['content'] ?? null)) {
                $sections[] = array_filter([
                    'label' => $this->text($s['ribbon_label'] ?? null) ?: null,
                    'heading' => $this->text($s['heading'] ?? null) ?: ($this->text($s['ribbon_label'] ?? null) ?: 'Details'),
                    'html' => $html,
                ]);
            }
        }

        foreach (['importance', 'benefits', 'who_avail'] as $key) {
            if ($html = $this->html->clean($pd["{$key}_content"] ?? null)) {
                $sections[] = ['heading' => $this->text($pd["{$key}_title"] ?? null) ?: Str::headline($key), 'html' => $html];
            }
        }

        $consequences = null;
        if ($html = $this->html->clean($sh['mandatory'] ?? null)) {
            $sections[] = ['label' => 'Compliance', 'heading' => $this->text($titles['mandatory'] ?? null) ?: 'Is it mandatory?', 'html' => $html];
        } elseif (filled($pd['mandatory_text'] ?? null) || filled($pd['why_points'] ?? null)) {
            $consequences = array_filter([
                'title' => $this->text($titles['mandatory'] ?? null) ?: 'Why it is mandatory',
                'text' => $this->text($pd['mandatory_intro'] ?? $pd['mandatory_text'] ?? null),
                'items' => collect($pd['why_points'] ?? [])->map(fn ($p) => $this->split($p))->values()->all(),
            ]);
        }

        [$validityIntro, $validity] = $this->bullets($pd['validity_text'] ?? null);
        $validity = array_values(array_filter([...array_map(fn ($v) => $this->text($v), $pd['validity_points'] ?? []), ...$validity]));

        $products = array_map(fn ($p) => $this->text(is_array($p) ? ($p['name'] ?? $p['title'] ?? implode(' — ', $p)) : $p), $pd['products'] ?? $pd['products_list'] ?? []);
        $splitProducts = array_map(fn ($p) => $this->split($p), $products);
        $twoColumns = $products && collect($splitProducts)->filter(fn ($p) => $p['text'] !== '')->count() >= count($products) * 0.6;

        $body = $this->html->clean($sh['what'] ?? null)
            ?? $this->html->clean($pd['what_content'] ?? null)
            ?? $this->html->clean($page['content'] ?? null)
            ?? $this->html->clean($pd['what_lead'] ?? null);

        return array_filter([
            'eyebrow' => $this->text($pd['eyebrow'] ?? null),
            'headline' => ($h = $this->text($pd['title'] ?? null)) && $h !== $page['title'] ? $h : null,
            'lead' => $this->text($pd['hero_description'] ?? null),
            'includes' => array_values(array_filter(array_map(fn ($b) => $this->text($b), $pd['hero_bullets'] ?? []))),
            'body' => $body,
            'sections' => $sections,
            'consequences' => $consequences,
            'documents' => filled($pd['docs_list'] ?? null) ? [[
                'title' => $this->text($pd['docs_title'] ?? $titles['docs'] ?? null) ?: 'Documents required',
                'items' => array_values(array_filter(array_map(fn ($d) => $this->text($d), $pd['docs_list']))),
            ]] : null,
            'table' => $products ? array_filter([
                'title' => $this->text($titles['products'] ?? null) ?: 'Products covered',
                'note' => $this->text($pd['products_intro'] ?? null),
                'columns' => $twoColumns ? ['Product group', 'Examples'] : ['Product'],
                'rows' => $twoColumns
                    ? array_map(fn ($p) => [$p['title'], $p['text']], $splitProducts)
                    : array_map(fn ($p) => [$p], $products),
            ]) : null,
            'steps' => collect($pd['process_steps'] ?? $pd['how_works_steps'] ?? [])
                ->map(fn ($s) => is_array($s) ? ['title' => $this->text($s['title'] ?? ''), 'text' => $this->text($s['description'] ?? $s['text'] ?? '')] : $this->split($s))
                ->filter(fn ($s) => $s['title'] !== '')->values()->all(),
            'timeline' => $timeline,
            'costs' => collect($pd['cost_factors'] ?? [])->map(fn ($c) => ['title' => $this->text($c['factor'] ?? ''), 'text' => $this->text($c['range'] ?? '')])
                ->filter(fn ($c) => $c['title'] !== '')->values()->all(),
            'validity' => $validity,
            'faqs' => $faqs,
            'related' => collect($pd['related_services'] ?? [])->map(fn ($r) => $this->relPath(is_array($r) ? ($r['url'] ?? '') : $r))->filter()->values()->all(),
            'headings' => array_filter([
                'body' => $this->text($pd['what_title'] ?? null),
                'documents' => $this->text($pd['docs_title'] ?? $titles['docs'] ?? null),
                'steps' => $this->text($pd['process_title'] ?? $titles['process'] ?? null),
                'timeline' => $this->text($pd['timeline_title'] ?? $titles['timeline'] ?? null),
                'costs' => $this->text($pd['cost_title'] ?? $titles['cost'] ?? null),
                'validity' => $this->text($pd['validity_title'] ?? $titles['validity'] ?? null),
                'faqs' => $this->text($pd['faq_title'] ?? null),
            ]),
            'intros' => array_filter([
                'documents' => $this->text($pd['docs_intro'] ?? null),
                'steps' => $this->text($pd['process_intro'] ?? null),
                'timeline' => $this->text($pd['timeline_intro'] ?? null),
                'costs' => $this->text($pd['cost_text'] ?? null),
                'validity' => $validityIntro,
            ]),
        ], fn ($v) => filled($v));
    }

    // --------------------------------------------------------------- standards

    private function importStandards(): void
    {
        $categories = collect($this->fetch('standard-categories'));
        $catPages = $this->pagesOf(['internal-standards'])->keyBy(fn ($p) => basename($p['path']));

        foreach ($categories->values() as $i => $c) {
            $page = $catPages[$c['slug']] ?? null;
            StandardCategory::updateOrCreate(['slug' => $c['slug']], [
                'legacy_id' => $c['id'],
                'title' => $c['name'] ?? $c['title'],
                'description' => $this->html->clean($c['description'] ?? null) ?? $this->html->clean($page['content'] ?? null),
                'image' => $this->mediaPath($c['image'] ?? null),
                'seo' => $page ? $this->seo($page, "resources/standards/{$c['slug']}") : $this->seo($c, "resources/standards/{$c['slug']}"),
                'sort' => $c['sort_order'] ?? $i,
            ]);
        }

        $ids = StandardCategory::pluck('id', 'slug');
        $legacyStandards = $categories->flatMap(fn ($c) => $c['standards'] ?? [])->keyBy('slug');
        $pages = $this->pagesOf(['sub-standards']);
        $this->media->prefetch($pages->pluck('featured_image'));
        $this->media->prefetchHtml($pages->pluck('content'));

        foreach ($pages as $i => $page) {
            $path = $this->relPath($page['path']);
            $catSlug = explode('/', $path)[2] ?? null;
            if (! isset($ids[$catSlug])) {
                continue;
            }

            $pd = $page['page_data'] ?? [];
            $legacy = $legacyStandards[basename($path)] ?? [];

            Standard::updateOrCreate(['path' => $path], [
                'legacy_id' => $legacy['id'] ?? $page['id'],
                'standard_category_id' => $ids[$catSlug],
                'title' => $page['title'],
                'slug' => basename($path),
                'description' => $this->html->clean($page['content'] ?? null) ?? $this->html->clean($legacy['description'] ?? null),
                'products_title' => $this->text($pd['product_list_title'] ?? null) ?: null,
                'products' => collect($pd['product_list'] ?? $legacy['product_list'] ?? [])
                    ->map(fn ($p) => ['is_number' => $this->text($p['is_number'] ?? ''), 'name' => $this->text($p['product_name'] ?? $p['name'] ?? '')])
                    ->filter(fn ($p) => $p['name'] !== '' || $p['is_number'] !== '')->values()->all(),
                'faqs' => $this->faqs($pd['faq_items'] ?? $legacy['faqs'] ?? []),
                'image' => $this->mediaPath($page['featured_image'] ?? $legacy['image'] ?? null),
                'status' => ($page['status'] ?? 'published') === 'published' ? 'published' : 'draft',
                'published_at' => $this->date($page['created_at'] ?? null),
                'sort' => $page['sort_order'] ?? $i,
                'seo' => $this->seo($page, $path),
            ]);
            $this->knownPaths[basename($path)] ??= $path;
        }
    }

    // ------------------------------------------------------------------- posts

    private function importPosts(): void
    {
        $only = array_map(fn ($path) => trim($path, '/'), $this->option('path'));

        foreach (self::POST_ENDPOINTS as $type => $endpoint) {
            $items = $this->fetch($endpoint, paginated: true);
            if ($only) {
                $items = array_values(array_filter($items, fn ($item) => in_array(Post::TYPES[$type][1].'/'.$item['slug'], $only, true)));
            }
            $this->media->prefetch(array_column($items, 'featured_image'));
            $this->media->prefetchHtml(array_column($items, 'content'));

            foreach ($items as $item) {
                $path = Post::TYPES[$type][1].'/'.$item['slug'];

                $post = Post::updateOrCreate(['type' => $type, 'slug' => $item['slug']], [
                    'legacy_id' => $item['id'],
                    'title' => $this->tidyTitle($this->text($item['title'])),
                    'excerpt' => $this->text($item['excerpt'] ?? null) ?: null,
                    'content' => $this->html->clean($item['content'] ?? null),
                    'image' => $this->mediaPath($item['featured_image'] ?? null),
                    'image_alt' => $this->text($item['title']),
                    'category_id' => $this->category($type, $item['category'] ?? null),
                    'author_id' => $this->author($item['author'] ?? null, $item['author_name'] ?? null),
                    'faqs' => ($item['show_faq'] ?? true) ? $this->faqs($item['faq_items'] ?? []) : [],
                    'status' => ($item['status'] ?? 'published') === 'published' ? 'published' : 'draft',
                    'published_at' => $this->date($item['published_at'] ?? $item['created_at'] ?? null),
                    'seo' => $this->seo($item, $path),
                ]);
                $this->keepLegacyTimestamp($post, $item);
            }
        }
    }

    private function category(string $type, ?array $category): ?int
    {
        if (empty($category['slug'])) {
            return null;
        }

        return Category::firstOrCreate(
            ['type' => $type, 'slug' => $category['slug']],
            ['legacy_id' => $category['id'] ?? null, 'name' => $category['name']],
        )->id;
    }

    private function author(?array $author, ?string $fallbackName): ?int
    {
        if (empty($author['slug']) && blank($fallbackName)) {
            return null;
        }

        if (empty($author['slug'])) {
            return Author::firstOrCreate(['slug' => Str::slug($fallbackName)], ['name' => $fallbackName])->id;
        }

        $expertise = $author['expertise_areas'] ?? null;

        return Author::updateOrCreate(['slug' => $author['slug']], [
            'legacy_id' => $author['id'] ?? null,
            'name' => $author['full_name'] ?? $author['name'] ?? Str::headline($author['slug']),
            'role' => $author['title'] ?? null,
            'company' => $author['company'] ?? null,
            'short_bio' => Str::limit($this->text($author['short_bio'] ?? null), 490, '') ?: null,
            'bio' => $this->text($author['full_bio'] ?? null) ?: null,
            'photo' => $this->mediaPath($author['photo'] ?? null),
            'experience_years' => $author['experience_years'] ?? null,
            'credentials' => $this->text($author['credentials'] ?? null) ?: null,
            'expertise' => is_array($expertise) ? $expertise : (filled($expertise) ? array_map('trim', explode(',', $expertise)) : null),
            'email' => $author['email'] ?? null,
            'links' => array_filter(Arr::only($author, ['linkedin', 'twitter', 'website'])) ?: null,
            'seo' => array_filter(['title' => $author['meta_title'] ?? null, 'description' => $author['meta_description'] ?? null]) ?: null,
            'is_published' => ($author['status'] ?? 'published') === 'published',
        ])->id;
    }

    // ------------------------------------------------------------------ events

    private function importEvents(): void
    {
        $items = $this->fetch('events', paginated: true);
        $this->media->prefetch(array_column($items, 'featured_image'));
        $this->media->prefetchHtml(array_column($items, 'content'));

        foreach ($items as $item) {
            $content = $this->html->clean($item['content'] ?? null);
            $excerpt = $this->text($item['excerpt'] ?? null);
            if (Str::endsWith($excerpt, ['...', '…']) || $excerpt === '') {
                $excerpt = Str::limit($this->text($content), 220);
            }
            $type = Str::lower($item['event_type'] ?? '');

            $event = Event::updateOrCreate(['slug' => $item['slug']], [
                'legacy_id' => $item['id'],
                'title' => $this->text($item['title']),
                'type' => array_key_exists($type, Event::TYPES) && $type !== 'seminar' ? $type : 'conference',
                'starts_at' => $this->date($item['start_date'] ?? null) ?? $this->date($item['created_at']),
                'ends_at' => $this->date($item['end_date'] ?? null),
                'location' => $this->text($item['location'] ?? null) ?: null,
                'url' => $item['event_url'] ?? null,
                'price' => $item['price'] ?? null,
                'excerpt' => $excerpt ?: null,
                'content' => $content,
                'image' => $this->mediaPath($item['featured_image'] ?? null),
                'image_alt' => $this->text($item['title']),
                'status' => ($item['status'] ?? 'published') === 'published' ? 'published' : 'draft',
                'published_at' => $this->date($item['created_at'] ?? null),
                'seo' => $this->seo($item, 'resources/events/'.$item['slug']),
            ]);
            $this->keepLegacyTimestamp($event, $item);
        }
    }

    // ------------------------------------------------------------------- pages

    private function importPages(): void
    {
        $settings = $this->fetch('site-settings');
        $pages = $this->pagesOf(array_keys(self::PAGE_TEMPLATES), $this->option('path'));
        $this->media->prefetch($pages->flatMap(fn ($p) => [$p['featured_image'] ?? null, ...Arr::flatten($p['page_data'] ?? [])])->filter(fn ($v) => is_string($v) && ! str_contains($v, ' ')));
        $this->media->prefetchHtml($pages->pluck('content'));

        foreach ($pages as $page) {
            $path = $this->relPath($page['path']);
            $template = self::PAGE_TEMPLATES[$page['template']];
            $content = $page['content'] ?? null;

            if (in_array($path, self::GENERATED_PATHS, true)) {
                Page::where('path', $path)->whereNotNull('legacy_id')->delete();

                continue;
            }

            if (blank($content) && $template === 'policy') {
                $content = $settings['page_'.str_replace('-', '_', basename($path))] ?? null;
                $content = is_array($content) ? ($content['content'] ?? null) : $content;
            }

            $data = match ($template) {
                'landing' => ['sections' => $page['page_data']['landing_sections'] ?? []],
                'hub' => ['sections' => $page['page_data']['parent_grid_sections'] ?? []],
                default => null,
            };

            // Empty legacy hubs are just listing pages, which the new frontend generates itself.
            if ($template === 'hub' && empty($data['sections']) && blank($content)) {
                Page::where('path', $path)->whereNotNull('legacy_id')->delete();

                continue;
            }

            $content = $this->html->clean($content);
            if (isset(self::RENDERED_PAGES[$path])) {
                $scraped = Page::where('path', $path)->value('content');
                $content = Str::length(strip_tags((string) $scraped)) > Str::length(strip_tags((string) $content)) ? $scraped : $content;
            }

            $record = Page::updateOrCreate(['path' => $path], [
                'legacy_id' => $page['id'],
                'template' => $template,
                'title' => $this->text($page['title']),
                'excerpt' => $this->text($page['excerpt'] ?? null) ?: null,
                'content' => $content,
                'data' => $data ? $this->rewriteMedia($data) : null,
                'image' => $this->mediaPath($page['featured_image'] ?? null),
                'status' => ($page['status'] ?? 'published') === 'published' ? 'published' : 'draft',
                'published_at' => $this->date($page['created_at'] ?? null),
                'sort' => $page['sort_order'] ?? 0,
                'seo' => $this->seo($page, $path),
            ]);
            $this->keepLegacyTimestamp($record, $page);
            $this->knownPaths[basename($path)] ??= $path;
        }
    }

    /** Swap legacy image URLs inside arbitrary page-builder JSON for migrated ones and make site links relative. */
    private function rewriteMedia(array $data): array
    {
        array_walk_recursive($data, function (&$value, $key) {
            if (! is_string($value) || $value === '') {
                return;
            }
            if (preg_match('/\.(png|jpe?g|webp|gif|svg)$/i', $value) && ! str_contains($value, ' ')) {
                $value = $this->media->url($value);
            } elseif (in_array($key, ['url', 'href', 'link', 'link_custom', 'cta_url', 'cta_link'], true)) {
                $path = $this->resolvePath($value);
                $value = Str::startsWith($path, ['http', 'mailto:', 'tel:', '#']) ? $path : '/'.$path;
            }
        });

        return $data;
    }

    // --------------------------------------------------------- rendered pages

    private function importRendered(): void
    {
        $site = rtrim(config('instacertify.legacy.site_url'), '/');
        File::ensureDirectoryExists($this->cacheDir.'/html');

        foreach (self::RENDERED_PAGES as $path => $template) {
            $file = "{$this->cacheDir}/html/{$path}.html";
            if ($this->option('refresh') || ! File::exists($file)) {
                File::put($file, Http::timeout(60)->retry(2, 1000)->get("{$site}/{$path}")->throw()->body());
            }

            $scraped = $this->scrapeMain(File::get($file));
            if (! $scraped) {
                $this->components->warn("No <main> content found in live page /{$path}");

                continue;
            }

            $this->media->prefetchHtml([$scraped['html']]);
            $content = $this->html->clean($scraped['html']);
            $page = Page::firstOrNew(['path' => $path]);
            // The live site serves these pages as indexable; robots rules on the old CMS record were never used.
            $seo = Arr::except($page->seo ?? [], ['robots']);

            // Keep whichever version is richer: an editor may have expanded the CMS copy since.
            if ($page->exists && Str::length(strip_tags((string) $page->content)) >= Str::length(strip_tags((string) $content))) {
                $page->update(['seo' => $seo]);

                continue;
            }

            $page->fill([
                'template' => $page->template ?? $template,
                'title' => $page->title ?: $scraped['title'],
                'content' => $content,
                'status' => $page->status ?? 'published',
                'published_at' => $page->published_at ?? now(),
                'seo' => $seo + array_filter(['title' => $scraped['seoTitle'], 'description' => $scraped['description']]),
            ])->save();
            $this->knownPaths[basename($path)] ??= $path;
        }
    }

    /** @return array{title: string, seoTitle: ?string, description: ?string, html: string}|null */
    private function scrapeMain(string $html): ?array
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        $xpath = new \DOMXPath($doc);

        $main = $xpath->query('//main')->item(0);
        if (! $main) {
            return null;
        }

        $title = trim($xpath->query('.//h1', $main)->item(0)?->textContent ?? '');
        $remove = [];
        foreach ($xpath->query(".//h1 | .//nav | .//form | .//button | .//aside | .//section[contains(., 'will be published soon')]", $main) as $node) {
            $remove[] = $node;
        }
        foreach ($xpath->query('.//h2 | .//h3', $main) as $heading) {
            if (preg_match(self::CTA_HEADING, trim($heading->textContent))) {
                $section = $xpath->query('ancestor::section[1]', $heading)->item(0);
                $remove[] = $section && $section !== $main ? $section : $heading;
            }
        }
        foreach ($remove as $node) {
            $node->parentNode?->removeChild($node);
        }

        $inner = '';
        foreach ($main->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }

        return [
            'title' => $title,
            'seoTitle' => trim($xpath->query('//head/title')->item(0)?->textContent ?? '') ?: null,
            'description' => trim($xpath->query('//head/meta[@name="description"]/@content')->item(0)?->nodeValue ?? '') ?: null,
            'html' => $inner,
        ];
    }

    // ---------------------------------------------------- faqs / misc content

    private function importFaqs(): void
    {
        foreach ($this->fetch('faqs') as $i => $faq) {
            $category = Str::lower($faq['category'] ?? 'general');

            Faq::updateOrCreate(['question' => $this->stripQuestionNumber($faq['question'])], [
                'answer' => $this->text($faq['answer']),
                'category' => array_key_exists($category, Faq::CATEGORIES) ? $category : 'general',
                'sort' => $faq['sort_order'] ?? $i,
                'is_published' => ($faq['status'] ?? 'published') === 'published',
            ]);
        }
    }

    private function importTestimonials(): void
    {
        foreach ($this->fetch('testimonials') as $i => $t) {
            $testimonial = Testimonial::firstOrNew(['name' => $t['author_name']]);
            if (! $testimonial->exists) {
                $testimonial->fill([
                    'context' => collect([$t['designation'] ?? null, $t['company'] ?? null])->filter()->implode(', ') ?: null,
                    'rating' => $t['rating'] ?? 5,
                    'quote' => $this->text($t['content']),
                    'sort' => 100 + $i,
                ]);
            }
            $testimonial->fill([
                'photo' => $testimonial->photo ?: $this->mediaPath($t['photo'] ?? null),
                'is_featured' => (bool) ($t['featured'] ?? false),
                'is_published' => ($t['status'] ?? 'published') === 'published',
            ])->save();
        }
    }

    private function importIndustries(): void
    {
        foreach ($this->fetch('industries') as $item) {
            $industry = Industry::firstWhere('slug', $item['slug']);
            if (! $industry) {
                continue;
            }

            $industry->update([
                'image' => $industry->image ?: $this->mediaPath($item['featured_image'] ?? null),
                'seo' => $industry->seo ?: $this->seo($item, $item['slug']),
            ]);
        }
    }

    private function importSettings(): void
    {
        $legacy = $this->fetch('site-settings');
        $decode = fn ($v) => is_string($v) && in_array($v[0] ?? '', ['[', '{'], true) ? json_decode($v, true) : $v;
        $setting = Setting::firstOrNew(['key' => 'site']);
        $site = $setting->value ?? [];

        $site['social'] = array_filter([
            ...array_filter($site['social'] ?? []),
            ...array_filter(Arr::only($legacy, ['facebook', 'twitter', 'linkedin', 'instagram', 'pinterest'])),
        ]);
        if (! Office::exists()) {
            collect($decode($legacy['contact_offices'] ?? []) ?: [])
                ->filter(fn ($o) => filled($o['address'] ?? null))
                ->values()
                ->each(fn ($o, $i) => Office::create([
                    'name' => $o['city'] ?? 'Office '.($i + 1),
                    'type' => $i === 0 ? 'head' : 'branch',
                    'street' => $o['address'],
                    'sort' => $i,
                ]));
        }
        $logos = collect($site['clientLogos'] ?? collect($decode($legacy['client_logos'] ?? []) ?: [])
            ->map(fn ($l) => ['image' => $l['src'] ?? null, 'name' => $l['alt'] ?? '']));
        $remote = fn ($l) => Str::startsWith((string) $l['image'], ['http', '/']);
        $this->media->prefetch($logos->filter($remote)->pluck('image'));
        $site['clientLogos'] = $logos
            ->map(fn ($l) => $remote($l) ? [...$l, 'image' => $this->media->path($l['image']) ?? $l['image']] : $l)
            ->filter(fn ($l) => $l['image'])->values()->all();
        $site['clientLogosEnabled'] ??= true;
        $site['rating'] = $site['rating'] ?? array_filter([
            'value' => $legacy['schema_rating_value'] ?? null,
            'count' => $legacy['schema_review_count'] ?? null,
        ]);

        $setting->value = $site;
        $setting->save();
    }

    // ----------------------------------------------------------------- helpers

    /** Fetch a legacy API listing, using (and filling) the local JSON cache. */
    private function fetch(string $endpoint, bool $paginated = false): array
    {
        $file = "{$this->cacheDir}/{$endpoint}.json";

        if (! $this->option('refresh') && File::exists($file)) {
            $cached = json_decode(File::get($file), true);
            $complete = ! $paginated || count($cached['data'] ?? []) >= ($cached['meta']['total'] ?? 0);
            if ($complete) {
                return $cached['data'] ?? [];
            }
        }

        $client = Http::baseUrl(config('instacertify.legacy.api_url'))->acceptJson()->timeout(60)->retry(3, 1000);

        if (! $paginated) {
            $data = $client->get($endpoint)->throw()->json('data') ?? [];
        } else {
            $data = [];
            $page = 1;
            do {
                $response = $client->get($endpoint, ['per_page' => 100, 'page' => $page])->throw();
                $data = [...$data, ...($response->json('data') ?? [])];
                $last = (int) $response->json('meta.last_page', 1);
            } while (++$page <= $last);
        }

        File::put($file, json_encode(['success' => true, 'data' => $data, 'meta' => ['total' => count($data)]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $data;
    }

    /** Full legacy page records (with page_data) for the given templates. */
    private function pagesOf(array $templates, array $paths = []): Collection
    {
        return collect($this->fetch('pages'))
            ->whereIn('template', $templates)
            ->when($paths, fn (Collection $pages) => $pages->filter(fn ($p) => in_array($this->relPath($p['path']), array_map(fn ($path) => trim($path, '/'), $paths), true)))
            ->map(fn ($p) => $this->pageDetail($p))
            ->filter()
            ->values();
    }

    private function pageDetail(array $page): ?array
    {
        $file = "{$this->cacheDir}/pages/{$page['id']}.json";

        if (! $this->option('refresh') && File::exists($file)) {
            return json_decode(File::get($file), true);
        }

        // Some legacy pages (e.g. /en71-toy-testing-india) are only found by their slug, not their public path.
        foreach (array_unique(array_filter([trim($page['path'], '/'), $page['slug'] ?? null])) as $lookup) {
            $response = Http::baseUrl(config('instacertify.legacy.api_url'))->acceptJson()->timeout(60)->retry(2, 1000, throw: false)
                ->get('pages/path/'.$lookup);
            if ($response->successful() && $response->json('data')) {
                break;
            }
        }

        if (! $response->successful() || ! $response->json('data')) {
            $this->components->warn("Page not available on legacy API: /{$page['path']}");
            File::put($file, 'null');

            return null;
        }

        File::put($file, json_encode($response->json('data'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $response->json('data');
    }

    /** Imports would otherwise stamp every record with today's date, misreporting freshness in sitemaps and "Updated" labels. */
    private function keepLegacyTimestamp(Model $model, array $legacy): void
    {
        if ($at = $this->date($legacy['updated_at'] ?? null)) {
            $model->forceFill(['updated_at' => $at])->saveQuietly();
        }
    }

    private function storeRedirects(): void
    {
        // Industry pages and the HTML sitemap are still served at their legacy root URLs, so never redirect those.
        $live = Service::pluck('path')->merge(Page::pluck('path'))->merge(Standard::pluck('path'))
            ->merge(LandingPage::pluck('path'))->merge(Industry::pluck('slug'))->push('sitemap')->flip();

        foreach ($this->redirects + $this->pathAliases as $from => $to) {
            if ($from !== $to && $from !== '' && ! Str::startsWith($from, 'http') && ! $live->has($from)) {
                Redirect::updateOrCreate(['from_path' => '/'.$from], ['to_path' => '/'.$to, 'status_code' => 301]);
            }
        }
    }

    /** Legacy SEO fields often canonicalise to URLs that never existed, which would de-index the real page. */
    private function pruneCanonicals(): void
    {
        $live = Service::pluck('path')
            ->merge(Page::pluck('path'))
            ->merge(Standard::pluck('path'))
            ->merge(Post::get(['type', 'slug'])->map(fn (Post $p) => Post::TYPES[$p->type][1].'/'.$p->slug))
            ->merge(Event::pluck('slug')->map(fn ($s) => "resources/events/{$s}"))
            ->merge(Industry::pluck('slug')->map(fn ($s) => "industries/{$s}"))
            ->flip();
        $site = rtrim(config('instacertify.legacy.site_url'), '/');

        foreach ([Service::class, Page::class, Standard::class, Post::class, Event::class] as $model) {
            $model::query()->whereNotNull('seo')->each(function (Model $record) use ($live, $site) {
                $canonical = $record->seo['canonical'] ?? null;
                if (! $canonical || (Str::startsWith($canonical, $site) && $live->has(trim(Str::after($canonical, $site), '/')))) {
                    return;
                }
                $record->timestamps = false;
                $record->forceFill(['seo' => Arr::except($record->seo, 'canonical') ?: null])->saveQuietly();
            });
        }
    }

    /** Path the legacy mega menu links to for a legacy service slug, if any. */
    private function resolveMenuPath(string $slug): ?string
    {
        static $bySlug = null;

        if ($bySlug === null) {
            $bySlug = [];
            $walk = function (array $items) use (&$walk, &$bySlug) {
                foreach ($items as $item) {
                    if ($url = $item['href'] ?? $item['url'] ?? null) {
                        $path = $this->relPath($url);
                        $bySlug[basename($path)] ??= $path;
                    }
                    $walk($item['items'] ?? $item['children'] ?? []);
                }
            };
            foreach ($this->fetch('mega-menu') as $items) {
                $walk(is_array($items) ? $items : []);
            }
        }

        return $bySlug[$slug] ?? null;
    }

    private function rememberKnownPaths(): void
    {
        foreach (Industry::pluck('slug') as $slug) {
            $this->pathAliases[$slug] = "industries/{$slug}";
        }
        $this->pathAliases += self::MANUAL_ALIASES;

        foreach ([Service::pluck('path'), Standard::pluck('path'), Page::pluck('path'), collect($this->fetch('pages'))->pluck('path')] as $paths) {
            foreach ($paths as $path) {
                $path = $this->relPath($path);
                $this->knownPaths[basename($path)] ??= $path;
            }
        }
    }

    /** Fix legacy links that point at an old slug (e.g. /certification/india/bis-crs-mark → …/bis-crs-mark-certification). */
    private function resolvePath(string $url): string
    {
        $path = $this->relPath($url);
        if ($path === '' || Str::startsWith($path, ['http', 'mailto:', 'tel:', '#'])) {
            return $path;
        }
        if (isset($this->pathAliases[$path])) {
            return $this->redirects[$path] = $this->pathAliases[$path];
        }
        if (in_array($path, $this->knownPaths, true)) {
            return $path;
        }

        $slug = basename($path);
        $prefix = dirname($path) === '.' ? '' : dirname($path).'/';
        $candidate = $this->knownPaths[$slug]
            ?? collect($this->knownPaths)->first(fn ($p) => Str::startsWith($p, $prefix.$slug.'-'));

        return $candidate ? $this->redirects[$path] = $candidate : $path;
    }

    private function relPath(?string $url): string
    {
        $url = trim((string) $url);
        if (preg_match('#^https?://#i', $url)) {
            $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
            if ($host !== preg_replace('/^www\./', '', (string) parse_url(config('instacertify.legacy.site_url'), PHP_URL_HOST))) {
                return $url;
            }
            $url = (string) parse_url($url, PHP_URL_PATH);
        }

        return trim($url, '/');
    }

    private function mediaPath(?string $src): ?string
    {
        if (! MediaImporter::looksLikeImageSource($src)) {
            return null;
        }

        return $this->media->path($src) ?? $this->media->absolute($src);
    }

    private function seo(array $r, string $ownPath): ?array
    {
        $title = $this->text($r['title'] ?? null);
        $canonical = filled($r['canonical_url'] ?? null) ? $this->relPath($r['canonical_url']) : null;
        $robots = Str::lower(str_replace(' ', '', (string) ($r['robots_meta'] ?? '')));
        $robots = ($r['noindex'] ?? false) ? 'noindex,follow' : (in_array($robots, ['noindex,follow', 'index,nofollow', 'noindex,nofollow'], true) ? $robots : null);
        $description = $this->text($r['seo_meta_description'] ?? null);

        return array_filter([
            'title' => $this->text($r['seo_meta_title'] ?? null),
            'description' => $description,
            'keyword' => $this->text($r['primary_keyword'] ?? null),
            'og_title' => ($t = $this->text($r['og_title'] ?? null)) && $t !== $this->text($r['seo_meta_title'] ?? null) && $t !== $title ? $t : null,
            'og_description' => ($d = $this->text($r['og_description'] ?? null)) && $d !== $description ? $d : null,
            'image' => filled($r['seo_meta_image'] ?? null) ? $this->media->url($r['seo_meta_image']) : null,
            'canonical' => $canonical && $canonical !== $ownPath ? (Str::startsWith($canonical, 'http') ? $canonical : rtrim(config('instacertify.legacy.site_url'), '/').'/'.$canonical) : null,
            'robots' => $robots,
            'reviewer' => $this->text($r['reviewer_name'] ?? null),
            'reviewer_credentials' => $this->text($r['reviewer_credentials'] ?? null),
            'reviewed_at' => $this->date($r['fact_check_date'] ?? null)?->toDateString(),
        ], fn ($v) => filled($v)) ?: null;
    }

    /** @return list<array{q: string, a: string}> */
    private function faqs(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($f) => [
                'q' => $this->stripQuestionNumber($f['question'] ?? $f['q'] ?? ''),
                'a' => $this->text($f['answer'] ?? $f['a'] ?? ''),
            ])
            ->filter(fn ($f) => $f['q'] !== '' && $f['a'] !== '')
            ->values()->all();
    }

    private function timeline(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($t) => ['phase' => $this->text($t['phase'] ?? ''), 'duration' => $this->text($t['duration'] ?? '')])
            ->filter(fn ($t) => $t['phase'] !== '')->values()->all();
    }

    /** "Title — explanation" / "Title: explanation" → [title, text]. */
    private function split(mixed $value): array
    {
        $value = $this->text(is_array($value) ? implode(' — ', $value) : $value);
        $parts = preg_split('/\s+[—–-]\s+|:\s+/u', $value, 2);

        return count($parts) === 2 && mb_strlen($parts[0]) <= 90
            ? ['title' => trim($parts[0]), 'text' => trim($parts[1])]
            : ['title' => $value, 'text' => ''];
    }

    /** Splits "Intro text\n\n- point\n- point" into [intro, points]. */
    private function bullets(?string $value): array
    {
        if (blank($value)) {
            return [null, []];
        }

        $intro = [];
        $points = [];
        foreach (preg_split('/\R/', strip_tags($value)) as $line) {
            $line = trim($line);
            if (preg_match('/^[-•*]\s*(.+)$/u', $line, $m)) {
                $points[] = $this->text($m[1]);
            } elseif ($line !== '') {
                $intro[] = $line;
            }
        }

        return [$this->text(implode(' ', $intro)) ?: null, $points];
    }

    private function stripQuestionNumber(string $question): string
    {
        return preg_replace('/^(Q\s*\d+|\d+)\s*[.:)\-]\s*/i', '', $this->text($question));
    }

    private function text(mixed $value): string
    {
        if (! is_string($value) || $value === '') {
            return '';
        }

        $value = preg_replace('/<br\s*\/?>|<\/p>|<\/li>/i', ' ', $value);

        return Str::squish(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Legacy titles generated from slugs read "Emi Emc Standards Iec 61000 4 3"; restore acronym casing. */
    private function tidyTitle(string $title): string
    {
        $title = preg_replace_callback('/\b(Iec|Ul|Emi|Emc|Led|Bis|Itu|Tv|Iso|Sae|Std|Mil|Ieee|Astm|Meity|Qco|Crs)\b/', fn ($m) => $m[1] === 'Meity' ? 'MeitY' : strtoupper($m[1]), $title);

        return preg_replace_callback('/\b(Is|En|Ts|Gb|Bs)\b(?=[\s-]*\d)/', fn ($m) => strtoupper($m[1]), $title);
    }

    private function norm(string $title): string
    {
        return Str::slug(preg_replace('/\b(certificate|certification|mark|registration|services?|approval|india)\b/i', '', $title));
    }

    private function date(?string $value): ?Carbon
    {
        try {
            return filled($value) ? Carbon::parse($value) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
