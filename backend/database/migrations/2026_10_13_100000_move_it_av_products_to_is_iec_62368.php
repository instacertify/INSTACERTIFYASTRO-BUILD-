<?php

use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Standard;
use App\Support\Standards\IsNumber;
use App\Support\Standards\LibraryImporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The CRS amendment to S.O. 1248(E) supersedes IS 13252 (Part 1):2010 and IS 616:2017 with IS/IEC 62368-1:2023 and adds
 * extended reality products (S.No. 65). Old and new standards run concurrently until 1 Nov 2028 (1 May 2026 for S.No. 65).
 * Moves every product row, page, service and the posts that presented the old standards as current to the new standard.
 */
return new class extends Migration
{
    private const BACKUP = 'is-iec-62368-backup-2026-10.json';

    private const NEW = 'IS/IEC 62368-1:2023';

    /** IS key of each superseded standard → how it is cited. */
    private const OLD = ['13252-p1' => 'IS 13252 (Part 1):2010', '616' => 'IS 616:2017'];

    private const PATTERNS = [
        '/\bIS[\s:]*13252(?:\s*\(\s*Part\s*1\s*\)|\s*-\s*1(?!\d)|\s*:\s*Part\s*1)?(?:\s*:\s*20\d\d)?(?:\s*\/\s*IEC\s*60950-1(?:\s*:\s*\d{4})?)?/i',
        '/\bIS[\s:]*616(?!\d)(?:\s*:\s*20\d\d)?(?:\s*\/\s*IEC\s*60065(?:\s*:\s*\d{4})?)?/i',
    ];

    private const NOTICE = '<p><strong>Update:</strong> IS 13252 (Part 1):2010 and IS 616:2017 have been superseded by <a href="/is-iec-62368-1">IS/IEC 62368-1:2023</a> for products under the BIS Compulsory Registration Scheme. You can still register under the old standard until 1 November 2028, after which it is withdrawn. We recommend testing new models to IS/IEC 62368-1:2023; testing starts from ₹35,000.</p>';

    private const POSTS = [
        'is-13252-compliance-for-power-adaptors-under-bis-crs', 'why-the-bis-crs-mark-is-non-negotiable-for-laptops-notebooks-and-tablets-in-india',
        'selling-webcams-in-india-why-the-bis-crs-mark-is-non-negotiable', 'crs-mark-for-printers-and-plotters-guide-with-latest-updates',
        'what-is-cdsco-certification-everything-startups-and-manufacturers-need-to-know', 'how-to-get-stqc-certification', 'how-to-get-bis-crs',
        'bis-crs-registration-online', 'how-to-get-bis-certificate-for-import-in-india', 'bis-extends-implementation-date-is-18112-january-2027',
        'bis-registration-mandatory-standalone-hdds', 'which-safety-mark-mandatory-india-by-product',
        'new-bis-certification-mandate-for-digital-television-receivers-what-manufacturers-need-to-know',
    ];

    /** Services that only cite the old standards in passing. */
    private const GENERIC_SERVICES = [
        'certification/india/stqc-certification',
        'power-consumption-testing-bee-schedule-14-laptop-notebook-computers',
        'power-consumption-testing-bee-schedule-26-uhd-tv-led-display',
    ];

    private const SERVICES = [
        'certification/india/bis-crs-mark-certification' => [
            'IS 13252 (Part 1) / IS 616 / IS 302 (Part 1)' => 'IS/IEC 62368-1:2023 / IS 302 (Part 1)',
            'IS 13252 (Part 1) / IEC 62368-1' => self::NEW,
            'IS 616 / IEC 62368-1' => self::NEW,
            'IS 13252 (Part 1) / IS 616' => self::NEW,
            'e.g. IS 13252 (Part 1) for IT equipment.' => 'e.g. IS/IEC 62368-1:2023 for IT and audio-video equipment.',
            'IS 13252 (Part 1)' => self::NEW,
            'MeitY updates the notified list regularly.' => 'MeitY updates the notified list regularly. IS/IEC 62368-1:2023 replaces IS 13252 (Part 1) and IS 616; the old standards can still be used until 1 November 2028.',
            'Varies by product type, complexity and number of models or series. The largest variable cost.' => 'Varies by product type, complexity and number of models or series. The largest variable cost. Testing to IS/IEC 62368-1:2023 starts from ₹35,000.',
        ],
        'is-13252-part-1' => [
            'serves as the gold standard for ensuring consumer electronics are resilient against electrical and thermal hazards.</p>' => 'has long been the safety standard for IT equipment in India. It has now been superseded by <strong>IS/IEC 62368-1:2023</strong>: both standards can be used until 1 November 2028, after which IS 13252 (Part 1) is withdrawn.</p>',
            'While the industry is gradually pivoting toward the hazard-based <strong>IS 62368-1</strong>, IS 13252 remains the foundational pillar for ITE safety certification in India under the MeitY CRS program.</p>' => 'It has now been superseded by the hazard-based <strong>IS/IEC 62368-1:2023</strong>. Under the amended CRS order, IT equipment can still be registered under IS 13252 (Part 1): 2010 until <strong>1 November 2028</strong>, after which it is withdrawn, so we recommend testing new models to <a href="/is-iec-62368-1">IS/IEC 62368-1:2023</a>.</p>',
            '<strong>Testing Fees:</strong> Varies based on the complexity of the product and number of models.' => '<strong>Testing Fees:</strong> Varies based on the complexity of the product and number of models. Testing to IS/IEC 62368-1:2023 starts from ₹35,000.',
            'Expert insights on IS 13252 (Part 1) BIS registration. Learn about testing, costs, and documentation for IT equipment safety in India.' => 'IS 13252 (Part 1) BIS registration for IT equipment and its move to IS/IEC 62368-1:2023: both can be used until 1 November 2028. Testing, costs and documents.',
        ],
        'safety-testing-is-13252-1-2010-it-equipments' => [
            'is the backbone of the <strong>Compulsory Registration Scheme (CRS)</strong> for Information Technology Equipment in India.' => 'has long been the backbone of the <strong>Compulsory Registration Scheme (CRS)</strong> for Information Technology Equipment in India. It has now been superseded by <strong>IS/IEC 62368-1:2023</strong>; both standards can be used until 1 November 2028.',
            'While the global community is shifting toward the hazard-based IS 16750 (IEC 62368-1) approach, IS 13252-1 remains the current enforced standard for the majority of IT peripherals under the BIS CRS.' => 'It has now been superseded by the hazard-based <strong>IS/IEC 62368-1:2023</strong>. Under the amended CRS order, IS 13252 (Part 1): 2010 can still be used until <strong>1 November 2028</strong>, after which it is withdrawn, so we recommend testing new models to <a href="/is-iec-62368-1">IS/IEC 62368-1:2023</a>.',
            '<strong>Laboratory Testing Fee:</strong> Varies based on product complexity and the number of models in a series.' => '<strong>Laboratory Testing Fee:</strong> Varies based on product complexity and the number of models in a series. Testing to IS/IEC 62368-1:2023 starts from ₹35,000.',
            'BIS is moving IT and audio-video products from IS 13252 (Part 1) and IS 616 to <strong>IS/IEC 62368-1:2023</strong>, the hazard-based standard used worldwide. Some products already have earlier deadlines. For most IT and AV products, the old and new standards run together until <strong>1 November 2028</strong>, after which registrations must be under IS/IEC 62368-1.' => 'IS 13252 (Part 1):2010 and IS 616:2017 have been superseded by <strong>IS/IEC 62368-1:2023</strong>, the hazard-based standard used worldwide, for every product under the BIS Compulsory Registration Scheme that used them. You can choose either the old or the new standard until <strong>1 November 2028</strong>; after that the old standards are withdrawn. Extended reality devices (AR, VR, MR) had to move by 1 May 2026. We recommend the new standard for new models; testing starts from ₹35,000.',
            'Yes, gradually. BIS is moving IT and audio-video products to IS/IEC 62368-1:2023; for most products, the old and new standards run together until 1 November 2028.' => 'Yes. IS/IEC 62368-1:2023 has superseded IS 13252 (Part 1):2010. Both can be used until 1 November 2028, after which IS 13252 is withdrawn, so new models are best tested to IS/IEC 62368-1:2023.',
            'IS 13252 (Part 1) is the BIS safety standard for IT equipment, identical to IEC 60950-1.' => 'IS 13252 (Part 1) is the BIS safety standard for IT equipment, identical to IEC 60950-1, now superseded by IS/IEC 62368-1:2023 (both can be used until 1 November 2028).',
        ],
        'safety-testing-is-616-2017-audio-video-similar-electronic-devices' => [
            'mandates rigorous safety benchmarks for audio and video apparatus. Our compliance strategy focuses on:</p>' => 'has set the safety benchmarks for audio and video apparatus in India. It has now been superseded by <strong>IS/IEC 62368-1:2023</strong>; both can be used until 1 November 2028, after which IS 616 is withdrawn. Our compliance strategy focuses on:</p>',
            'advanced lithium-ion battery safety.</p>' => 'advanced lithium-ion battery safety.</p><p>Under the amended CRS order, IS 616:2017 has been superseded by <strong>IS/IEC 62368-1:2023</strong>. You can still register under IS 616:2017 until <strong>1 November 2028</strong>, after which it is withdrawn, so we recommend testing new models to <a href="/is-iec-62368-1">IS/IEC 62368-1:2023</a>. Extended reality devices (AR, VR, MR) had to move by 1 May 2026.</p>',
            '<strong>Testing Charges:</strong> Dependent on the number of models and technical features.' => '<strong>Testing Charges:</strong> Dependent on the number of models and technical features. Testing to IS/IEC 62368-1:2023 starts from ₹35,000.',
            'both domestic and foreign manufacturers must obtain IS 616 certification before selling regulated electronic goods in the Indian market.' => 'both domestic and foreign manufacturers must register regulated audio-video goods with BIS before selling them in India. Until 1 November 2028 the registration can be under IS 616:2017 or under IS/IEC 62368-1:2023, which replaces it; we recommend the new standard.',
            'Expert IS 616:2017 testing and registration services for seamless Indian market entry.' => 'IS 616:2017 testing and the move to IS/IEC 62368-1:2023 (both valid until 1 Nov 2028).',
        ],
        'is-iec-62368-1' => [
            'Merges legacy standards (IS 616 and IS 13252) into one.' => 'Replaces IS 616:2017 and IS 13252 (Part 1):2010 for CRS products; the old standards can be used until 1 November 2028.',
            'aiming for long-term compliance under the <strong>BIS Compulsory Registration Scheme (CRS)</strong>.</p>' => 'aiming for long-term compliance under the <strong>BIS Compulsory Registration Scheme (CRS)</strong>.</p><p>Under the amended Electronics and Information Technology Goods (Requirements for Compulsory Registration) Order, IS/IEC 62368-1:2023 replaces IS 13252 (Part 1):2010 and IS 616:2017 for every product that used them, and extended reality products (AR, VR, MR) were added at S.No. 65. The old standards can still be used until <strong>1 November 2028</strong> (1 May 2026 for extended reality products); after that they are withdrawn.</p>',
            '<strong>Testing Fees:</strong> Charged by the lab based on the complexity and number of models (Series guidelines apply).' => '<strong>Testing Fees:</strong> Start from ₹35,000, depending on the complexity and number of models (Series guidelines apply).',
            'Manufacturers can currently choose either standard, but full migration becomes mandatory by November 2028. We recommend early adoption to avoid supply chain disruptions during the final transition phase.' => 'No. Until 1 November 2028 you can register under either IS 13252 (Part 1):2010 / IS 616:2017 or IS/IEC 62368-1:2023; after that only IS/IEC 62368-1:2023 is accepted. Extended reality devices had to switch by 1 May 2026. We recommend testing new models to IS/IEC 62368-1:2023 now, so you do not have to retest later.',
        ],
    ];

    private const SERVICE_SUMMARIES = [
        'safety-testing-is-13252-1-2010-it-equipments' => 'IS 13252-1:2010 safety testing for IT equipment under BIS CRS. The standard has been superseded by IS/IEC 62368-1:2023; both can be used until 1 November 2028.',
    ];

    private const SERVICE_FAQS = [
        'certification/india/bis-crs-mark-certification' => ['q' => 'Which standard applies to IT and audio-video products now?', 'a' => 'IS/IEC 62368-1:2023. It has replaced IS 13252 (Part 1):2010 and IS 616:2017 for every CRS product that used them, and also covers extended reality (AR, VR, MR) devices. You can still register under the old standard until 1 November 2028 (1 May 2026 for extended reality devices); after that it is withdrawn. We recommend testing new models to IS/IEC 62368-1:2023 so you do not need to retest later. Testing starts from ₹35,000.'],
        'is-13252-part-1' => ['q' => 'Can I still register under IS 13252 (Part 1)?', 'a' => 'Yes, until 1 November 2028. IS/IEC 62368-1:2023 has superseded IS 13252 (Part 1):2010, and until that date you can choose either standard; after it IS 13252 is withdrawn. We recommend testing new models to IS/IEC 62368-1:2023. Testing starts from ₹35,000.'],
        'safety-testing-is-616-2017-audio-video-similar-electronic-devices' => ['q' => 'Should I test to IS 616 or IS/IEC 62368-1:2023?', 'a' => 'Either is accepted until 1 November 2028, after which IS 616:2017 is withdrawn. We recommend IS/IEC 62368-1:2023 for new models, so the registration will not need a fresh test report later. Testing starts from ₹35,000.'],
        'is-iec-62368-1' => ['q' => 'How much does IS/IEC 62368-1 testing cost?', 'a' => 'Testing to IS/IEC 62368-1:2023 starts from ₹35,000. The final price depends on the product, its power rating and how many models you add to the series.'],
    ];

    private const PAGE = 'services/testing/electronics-electricals-testing';

    public function up(): void
    {
        $backup = $this->backup();
        $before = Standard::pluck('id')->all();

        DB::transaction(function () {
            foreach (Standard::all() as $standard) {
                $this->standard($standard);
            }
            if (Standard::where('scheme', 'crs')->exists()) {
                (new LibraryImporter(database_path('data/standards')))->refreshCrsPages();
            }

            foreach (array_unique([...array_keys(self::SERVICES), ...self::GENERIC_SERVICES]) as $path) {
                if ($service = Service::firstWhere('path', $path)) {
                    $this->service($service);
                }
            }
            if ($page = Page::firstWhere('path', self::PAGE)) {
                $page->data = $this->tiles($page->data);
                $page->isDirty() && $page->save();
            }
            foreach (Post::whereIn('slug', self::POSTS)->get() as $post) {
                if (! str_contains((string) $post->content, self::NEW)) {
                    $post->content = self::NOTICE.$post->content;
                    $post->save();
                }
            }
        });

        $backup['created'] = Standard::whereNotIn('id', $before)->pluck('id')->all();
        $disk = Storage::disk('local');
        // Fresh test databases have none of these pages; an empty file would block the real backup later.
        if (! $disk->exists(self::BACKUP) && ($backup['standard'] || $backup['service'] || $backup['post'])) {
            $disk->put(self::BACKUP, json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    public function down(): void
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::BACKUP)) {
            return;
        }

        $backup = json_decode($disk->get(self::BACKUP), true);
        Standard::whereIn('id', $backup['created'] ?? [])->delete();
        foreach ($backup['standard'] ?? [] as $id => $original) {
            if ($standard = Standard::find($id)) {
                $this->restore($standard, $original);
            }
        }
        foreach ($backup['service'] ?? [] as $path => $original) {
            if ($service = Service::firstWhere('path', $path)) {
                $this->restore($service, $original);
            }
        }
        foreach ($backup['page'] ?? [] as $path => $original) {
            if ($page = Page::firstWhere('path', $path)) {
                $this->restore($page, $original);
            }
        }
        foreach ($backup['post'] ?? [] as $slug => $original) {
            if ($post = Post::firstWhere('slug', $slug)) {
                $this->restore($post, $original);
            }
        }

        $disk->delete(self::BACKUP);
    }

    private function standard(Standard $standard): void
    {
        $converted = [];
        $products = array_map(function (array $row) use (&$converted) {
            $replaced = [];
            $parts = collect(explode(' / ', (string) ($row['is_number'] ?? '')))->map(function (string $part) use (&$replaced) {
                $old = self::OLD[IsNumber::key($part)] ?? null;
                $old && $replaced[] = $old;

                return $old ? self::NEW : trim($part);
            })->unique();
            if (! $replaced) {
                return $row;
            }
            $converted[] = $replaces = $row['replaces'] ?? implode(' / ', array_unique($replaced));

            return array_merge($row, ['is_number' => $parts->implode(' / '), 'replaces' => $replaces]);
        }, $standard->products ?? []);

        $text = array_map(fn (string $field) => $this->modernise($standard->{$field}), ['description', 'faqs', 'seo', 'facts']);
        if (! $converted && $text === array_map(fn (string $field) => $standard->{$field}, ['description', 'faqs', 'seo', 'facts'])) {
            return;
        }

        [$standard->description, $faqs, $standard->seo, $standard->facts] = $text;
        $standard->products = $this->dedupe($products);
        if ($converted && ! str_contains(json_encode($faqs ?? []), '2028')) {
            $replaces = $converted[0];
            $faqs = [...$faqs ?? [], [
                'q' => "Can I still register this product under {$replaces}?",
                'a' => "Yes, until 1 November 2028. IS/IEC 62368-1:2023 has replaced {$replaces}, and until that date you can choose either standard; after it the old standard is withdrawn. We recommend testing to IS/IEC 62368-1:2023 now, so you will not need a new test report later. Testing starts from ₹35,000.",
            ]];
        }
        $standard->faqs = $faqs;
        $standard->save();
    }

    /** Collapses rows that name the same product under the same standard, which the old standards often listed several times. */
    private function dedupe(array $rows): array
    {
        $kept = [];
        foreach ($rows as $row) {
            $id = IsNumber::key($row['is_number'] ?? null).'|'.Str::lower(trim((string) ($row['name'] ?? '')));
            $kept[$id] = isset($kept[$id]) ? $kept[$id] + $row : $row;
        }

        return array_values($kept);
    }

    private function service(Service $service): void
    {
        $path = $service->path;
        if (in_array($path, self::GENERIC_SERVICES, true)) {
            $service->detail = $this->modernise($service->detail);
        }
        if ($map = self::SERVICES[$path] ?? null) {
            $service->detail = $this->replace($service->detail, $map);
            $service->seo = $this->replace($service->seo, $map);
            $service->summary = $this->replace($service->summary, $map);
        }
        if ($summary = self::SERVICE_SUMMARIES[$path] ?? null) {
            $service->summary = $summary;
        }
        if (($faq = self::SERVICE_FAQS[$path] ?? null) && ! in_array($faq['q'], array_column($service->detail['faqs'] ?? [], 'q'), true)) {
            $service->detail = array_merge($service->detail ?? [], ['faqs' => [...$service->detail['faqs'] ?? [], $faq]]);
        }
        $service->isDirty() && $service->save();
    }

    /** Leads the electrical safety tiles with the new standard and marks the old one as being phased out. */
    private function tiles(?array $data): ?array
    {
        foreach ($data['sections'] ?? [] as $i => $section) {
            $items = collect($section['items'] ?? []);
            $old = $items->search(fn ($item) => ($item['link_custom'] ?? null) === '/is-13252-part-1');
            $new = $items->search(fn ($item) => ($item['link_custom'] ?? null) === '/is-iec-62368-1');
            if ($old === false || $new === false) {
                continue;
            }
            $items[$new] = ['title' => self::NEW, 'content' => 'Safety standard for audio, video, IT and communication equipment. Replaces IS 13252 and IS 616; testing from ₹35,000.'] + $items[$new];
            $items[$old] = ['content' => 'Older IT equipment safety standard, replaced by IS/IEC 62368-1:2023 and usable until 1 November 2028.'] + $items[$old];
            [$first, $second] = [$items[$new], $items[$old]];
            $items[min($old, $new)] = $first;
            $items[max($old, $new)] = $second;
            $data['sections'][$i]['items'] = $items->all();
        }

        return $data;
    }

    private function modernise(mixed $value): mixed
    {
        $convert = fn (string $text) => preg_replace(
            '~IS/IEC 62368-1:2023(?:\s*(?:/|,|or|and)\s*(?:IS/IEC 62368-1:2023|(?:IS/)?IEC\s*62368-1(?:\s*:\s*\d{4})?)(?![\d-]))+~',
            self::NEW,
            preg_replace(self::PATTERNS, self::NEW, $text),
        );
        if (is_string($value)) {
            return $convert($value);
        }
        if (is_array($value)) {
            array_walk_recursive($value, function (&$item) use ($convert) {
                if (is_string($item)) {
                    $item = $convert($item);
                }
            });
        }

        return $value;
    }

    /** @param  array<string, string>  $map */
    private function replace(mixed $value, array $map): mixed
    {
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

    /** @return array<string, array<int|string, array<string, mixed>>> */
    private function backup(): array
    {
        $backup = ['standard' => [], 'service' => [], 'page' => [], 'post' => []];
        $affected = fn (Standard $s) => $s->scheme === 'crs'
            || preg_match('/13252|\b616\b/', json_encode([$s->description, $s->products, $s->faqs, $s->seo, $s->facts]));
        foreach (Standard::all()->filter($affected) as $standard) {
            $backup['standard'][$standard->id] = [
                'description' => $standard->description, 'products' => $standard->products, 'faqs' => $standard->faqs,
                'seo' => $standard->seo, 'facts' => $standard->facts, 'updated_at' => $standard->updated_at?->toDateTimeString(),
            ];
        }
        foreach (Service::whereIn('path', [...array_keys(self::SERVICES), ...self::GENERIC_SERVICES])->get() as $service) {
            $backup['service'][$service->path] = [
                'summary' => $service->summary, 'detail' => $service->detail, 'seo' => $service->seo, 'updated_at' => $service->updated_at?->toDateTimeString(),
            ];
        }
        if ($page = Page::firstWhere('path', self::PAGE)) {
            $backup['page'][$page->path] = ['data' => $page->data, 'updated_at' => $page->updated_at?->toDateTimeString()];
        }
        foreach (Post::whereIn('slug', self::POSTS)->get() as $post) {
            $backup['post'][$post->slug] = ['content' => $post->content, 'updated_at' => $post->updated_at?->toDateTimeString()];
        }

        return $backup;
    }

    /** @param  array<string, mixed>  $original */
    private function restore(Model $model, array $original): void
    {
        $model->forceFill($original);
        $model->timestamps = false;
        $model->save();
    }
};
