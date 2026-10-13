<?php

use App\Models\Post;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Standard;
use App\Support\Standards\IsNumber;
use App\Support\Standards\LibraryImporter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * BIS circulars of 31 July 2026: the revised 2026 editions of IS 10322 (Part 5) for LED luminaires and IS 16102 (Part 1)
 * for LED lamps run alongside the old editions until 2 Feb 2027. BIS guidance Reg/2026/G-01 (13 Aug 2026) sorts wearable
 * glasses into CRS categories and names the Extended Reality sub-categories.
 * Also folds the duplicate Extended Reality page from the IS/IEC 62368-1 migration into the existing one.
 */
return new class extends Migration
{
    private const BACKUP = 'led-lighting-backup-2026-10.json';

    /** IS key → [current edition, revised edition, IEC basis of the revision]. */
    private const EDITIONS = [
        '16102-p1' => ['IS 16102 (Part 1):2012', 'IS 16102 (Part 1):2026', null],
        '10322-p5-s1' => ['IS 10322 (Part 5/Sec 1):2012', 'IS 10322 (Part 5/Sec 1):2026', 'IEC 60598-2-1:2020'],
        '10322-p5-s2' => ['IS 10322 (Part 5/Sec 2):2012', 'IS 10322 (Part 5/Sec 2):2026', 'IEC 60598-2-2:2023'],
        '10322-p5-s3' => ['IS 10322 (Part 5/Sec 3):2012', 'IS 10322 (Part 5/Sec 3):2026', 'IEC 60598-2-3:2002 + Amd 1:2011'],
        '10322-p5-s5' => ['IS 10322 (Part 5/Sec 5):2013', 'IS 10322 (Part 5/Sec 5):2026', 'IEC 60598-2-5:2015'],
        '10322-p5-s6' => ['IS 10322 (Part 5/Sec 6):2013', 'IS 10322 (Part 5/Sec 6):2026', 'IEC 60598-2-8:2013'],
        '10322-p5-s7' => ['IS 10322 (Part 5/Sec 7):2017', 'IS 10322 (Part 5/Sec 7):2026', 'IEC 60598-2-20:2022'],
        '10322-p5-s8' => ['IS 10322 (Part 5/Sec 8):2013', 'IS 10322 (Part 5/Sec 8):2026', 'IEC 60598-2-22:2022'],
    ];

    /** CRS page slug → the edition key it is about. The pages cite wrong sections in places, so the page decides. */
    private const PAGES = [
        'crs-mark-led-luminaries' => '10322-p5-s1',
        'crs-mark-recessed-led-luminaries' => '10322-p5-s2',
        'crs-mark-led-luminaries-road-street-lighting' => '10322-p5-s3',
        'crs-mark-led-flood-lights' => '10322-p5-s5',
        'crs-mark-led-hand-lamps' => '10322-p5-s6',
        'crs-mark-led-lighting-chains' => '10322-p5-s7',
        'crs-mark-lighting-chain-rope-lights' => '10322-p5-s7',
        'crs-mark-led-luminaries-emergency-lighting' => '10322-p5-s8',
        'crs-mark-for-self-ballasted-led-lamps-for-general-lighting-services' => '16102-p1',
    ];

    private const CITATION = [
        '10322' => '/\bIS\s*10322\s*\(\s*Part\s*5(?:\s*\/\s*Sec(?:tion)?\s*\d+)?\s*\)(?:\s*:?\s*Section\s*\d+)?(?:\s*:\s*\d{4})?/i',
        '16102' => '/\bIS\s*16102\s*\(\s*Part\s*1\s*\)(?:\s*:\s*\d{4})?/i',
    ];

    private const GLASSES_FAQS = [
        'crs-mark-wireless-headphones-earphones' => ['q' => 'Do audio glasses need CRS registration?', 'a' => 'Yes. Under BIS guidance of 13 August 2026, audio glasses (glasses with wireless audio, calls and music, with or without an AI voice assistant, and no camera) are registered under Wireless Earphones and Headphones, sub-category Audio Glasses. Glasses with a camera go under Digital Camera or Video Camera, and glasses with a display under Extended Reality Products.'],
        'crs-mark-digital-camera' => ['q' => 'Do smart glasses with a camera need CRS registration?', 'a' => 'Yes. Under BIS guidance of 13 August 2026, smart glasses whose camera takes photos only are registered under Digital Camera, sub-category Smart Glasses. If the camera also records video, they go under Video Camera; if the glasses show a display through the lenses, they go under Extended Reality Products as AR or MR glasses.'],
        'crs-mark-video-camera' => ['q' => 'Do smart glasses with a camera need CRS registration?', 'a' => 'Yes. Under BIS guidance of 13 August 2026, smart glasses whose camera takes both photos and videos are registered under Video Camera, sub-category Smart Glasses. Glasses that take photos only go under Digital Camera; glasses with a display visible through the lenses go under Extended Reality Products as AR or MR glasses.'],
        'crs-mark-extended-reality-products-arvrmr' => ['q' => 'How are AR, VR and MR devices registered?', 'a' => 'Under BIS guidance of 13 August 2026, Extended Reality Products have five product names: AR Glasses, Other AR Devices, VR Headset, MR Glasses and Other MR Devices, and each product name needs its own licence. AR and MR glasses have a display visible through the lenses; smart glasses without a display go under Digital Camera, Video Camera or Wireless Earphones and Headphones instead. All are tested to IS/IEC 62368-1:2023, from ₹35,000. Testing under IS 13252 (Part 1) or IS 616 was accepted only until 1 May 2026.'],
    ];

    private const DUPLICATE = 'crs-mark-extended-reality-products-ar-vr-mr';

    private const ORIGINAL = 'crs-mark-extended-reality-products-arvrmr';

    /** Service path → edition key of the standard it is about. */
    private const SERVICES = [
        'new-standards-for-led-is-10322-part-5-section-1-2012' => '10322-p5-s1',
        'new-standards-for-led-is-10322-part-5-section-2-2012' => '10322-p5-s2',
        'new-standards-for-led-is-10322-part-5-section-3-2012' => '10322-p5-s3',
        'new-standards-for-led-is-10322-part-5-section-5-2013' => '10322-p5-s5',
        'new-standards-for-led-is-10322-part-5-section-8-2013' => '10322-p5-s8',
        'new-standards-for-led-is-16102-part-1-2012' => '16102-p1',
        'is-16102' => '16102-p1',
    ];

    private const LUMINAIRE_TESTING = 'services/testing/electrical-electronics/luminaire-testing';

    private const CRS_SERVICE = 'certification/india/bis-crs-mark-certification';

    private const CRS_SERVICE_MAP = [
        'IS 16102 (Part 1) / IEC 62560' => 'IS 16102 (Part 1):2026 / IEC 62560',
        'IS 10322 (Part 5 / Sec 1 & 2)' => 'IS 10322 (Part 5/Sec 1 & 2):2026',
    ];

    private const NEWS_10322 = '/news-item/bis-extends-implementation-date-revised-is-10322-luminaire-february-2027';

    private const NEWS_16102 = '/news-item/bis-extends-implementation-date-is-16102-part-1-led-february-2027';

    private const POSTS_10322 = [
        'bis-announces-major-revisions-for-led-luminaires-is-10322', 'led-revisions-an-action-plan-for-importers-and-manufacturers',
        'is-your-emergency-light-legal-to-sell-the-truth-about-is-10322-part-5-sec-8', 'led-light-street-light-solar-street-light-compliance',
    ];

    private const POSTS_16102 = ['is-16102-part-1-a-complete-guide-for-led-lamp'];

    private const POST_CITATIONS = [
        'how-to-get-bis-certificate-for-import-in-india' => [
            'IS 16102 (Part 1):2012</li>' => 'IS 16102 (Part 1):2026</li>',
            'IS 13252 (Part 1):2010</li>' => 'IS/IEC 62368-1:2023</li>',
        ],
    ];

    public function up(): void
    {
        $backup = $this->backup();

        DB::transaction(function () {
            $this->foldDuplicate();
            foreach (Standard::whereIn('slug', array_keys(self::PAGES))->where('scheme', 'crs')->get() as $standard) {
                $this->lightingPage($standard, self::PAGES[$standard->slug]);
            }
            if (Standard::where('scheme', 'crs')->exists()) {
                (new LibraryImporter(database_path('data/standards')))->refreshCrsPages();
            }
            foreach (Standard::whereIn('slug', array_keys(self::GLASSES_FAQS))->get() as $standard) {
                $faq = self::GLASSES_FAQS[$standard->slug];
                if (! in_array($faq['q'], array_column($standard->faqs ?? [], 'q'), true)) {
                    $standard->faqs = [...$standard->faqs ?? [], $faq];
                    $standard->save();
                }
            }

            foreach (Service::whereIn('path', array_keys(self::SERVICES))->get() as $service) {
                $this->serviceNote($service, self::SERVICES[$service->path]);
            }
            if ($service = Service::firstWhere('path', self::LUMINAIRE_TESTING)) {
                $this->addServiceFaq($service, ['q' => 'Which edition of IS 10322 should I test to?', 'a' => 'BIS has revised the Part 5 sections for fixed, recessed, road and street, flood, hand, chain and emergency LED luminaires as 2026 editions. The older editions can be used until 2 February 2027; after that only the 2026 editions are accepted, so we recommend testing new models to the 2026 versions.']);
            }
            if ($service = Service::firstWhere('path', self::CRS_SERVICE)) {
                $service->detail = $this->replace($service->detail, self::CRS_SERVICE_MAP);
                $service->isDirty() && $service->save();
            }

            foreach (Post::whereIn('slug', [...self::POSTS_10322, ...self::POSTS_16102])->get() as $post) {
                [$standard, $link] = in_array($post->slug, self::POSTS_16102, true)
                    ? ['BIS has revised this standard as IS 16102 (Part 1):2026 and, on 31 July 2026, extended its implementation date to <strong>2 February 2027</strong>. Until then you can test to either IS 16102 (Part 1):2012 or the 2026 version', self::NEWS_16102]
                    : ['On 31 July 2026 BIS extended the last date of concurrent running for the revised IS 10322 (Part 5) standards for LED luminaires to <strong>2 February 2027</strong>. Until then you can test to either the old or the 2026 edition', self::NEWS_10322];
                if (! str_contains((string) $post->content, $link)) {
                    $post->content = "<p><strong>Update:</strong> {$standard}; after it only the 2026 version is accepted. See the <a href=\"{$link}\">extension notice</a>.</p>".$post->content;
                    $post->save();
                }
            }
            foreach (self::POST_CITATIONS as $slug => $map) {
                if ($post = Post::firstWhere('slug', $slug)) {
                    $post->content = $this->replace($post->content, $map);
                    $post->isDirty() && $post->save();
                }
            }
        });

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
        if ($duplicate = $backup['duplicate'] ?? null) {
            Redirect::where('from_path', '/'.$duplicate['path'])->delete();
            DB::table('standards')->insert($duplicate);
        }
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
        foreach ($backup['post'] ?? [] as $slug => $original) {
            if ($post = Post::firstWhere('slug', $slug)) {
                $this->restore($post, $original);
            }
        }

        $disk->delete(self::BACKUP);
    }

    /** The IS/IEC 62368-1 migration added a second Extended Reality page; its URL now leads to the one that was there first. */
    private function foldDuplicate(): void
    {
        $duplicate = Standard::where('slug', self::DUPLICATE)->where('source', 'library')->first();
        $original = Standard::firstWhere('slug', self::ORIGINAL);
        if (! $duplicate || ! $original) {
            return;
        }
        Redirect::updateOrCreate(['from_path' => '/'.$duplicate->path], ['to_path' => '/'.$original->path, 'status_code' => 301, 'is_active' => true]);
        $duplicate->delete();
    }

    private function lightingPage(Standard $standard, string $key): void
    {
        [$current, $revised, $iec] = self::EDITIONS[$key];
        $rows = [];
        foreach ($standard->products ?? [] as $row) {
            $rowKey = (string) IsNumber::key($row['is_number'] ?? null);
            if (str_starts_with($rowKey, '10322-p5') || $rowKey === '16102-p1') {
                $row = array_merge($row, ['is_number' => $revised, 'replaces' => $current]);
                $rows[$key] = isset($rows[$key]) ? $rows[$key] + $row : $row;
            } else {
                $rows[] = $row;
            }
        }
        if ($key === '10322-p5-s1' && ! in_array('Fancy Lights', array_column($rows, 'name'), true)) {
            $rows[] = ['is_number' => $revised, 'name' => 'Fancy Lights', 'replaces' => $current];
        }
        $standard->products = array_values($rows);

        $pattern = self::CITATION[str_starts_with($key, '10322') ? '10322' : '16102'];
        $cite = fn (mixed $value) => $this->map($value, fn (string $text) => preg_replace($pattern, $revised, $text));
        $standard->description = $cite($standard->description);
        $standard->seo = $cite($standard->seo);
        $faqs = $cite($standard->faqs) ?? [];
        if (! str_contains(json_encode($faqs), '2027')) {
            $basis = $iec ? ", based on {$iec}," : '';
            $faqs[] = ['q' => "Can I still test to {$current}?", 'a' => "Yes, until 2 February 2027. BIS has revised the standard as {$revised}{$basis} and extended the concurrent running of the old and new versions to that date (circular of 31 July 2026). After it, only the 2026 version is accepted, so we recommend testing new models to {$revised}."];
        }
        $standard->faqs = $faqs;
        $standard->save();
    }

    private function serviceNote(Service $service, string $key): void
    {
        [$current, $revised, $iec] = self::EDITIONS[$key];
        $detail = $service->detail ?? [];
        if (str_contains((string) ($detail['body'] ?? ''), $revised)) {
            return;
        }
        $basis = $iec ? ", based on {$iec}," : '';
        $fancy = $key === '10322-p5-s1' ? ' Fancy lights are registered under the same category as a separate product name.' : '';
        $link = str_starts_with($key, '16102') ? self::NEWS_16102 : self::NEWS_10322;
        $detail['body'] = ($detail['body'] ?? '')."<p><strong>Revised standard:</strong> BIS has published <strong>{$revised}</strong>{$basis} to replace {$current}. Both versions can be used until <strong>2 February 2027</strong>, after which only the 2026 version is accepted, so we recommend testing new models to {$revised}.{$fancy} See the <a href=\"{$link}\">BIS extension notice</a>.</p>";
        $service->detail = $detail;
        $service->save();
    }

    /** @param  array{q: string, a: string}  $faq */
    private function addServiceFaq(Service $service, array $faq): void
    {
        if (! in_array($faq['q'], array_column($service->detail['faqs'] ?? [], 'q'), true)) {
            $service->detail = array_merge($service->detail ?? [], ['faqs' => [...$service->detail['faqs'] ?? [], $faq]]);
            $service->save();
        }
    }

    private function map(mixed $value, Closure $convert): mixed
    {
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
        return $this->map($value, fn (string $text) => str_replace(array_keys($map), array_values($map), $text));
    }

    /** @return array<string, mixed> */
    private function backup(): array
    {
        $backup = ['standard' => [], 'service' => [], 'post' => [], 'duplicate' => null];
        $slugs = [...array_keys(self::PAGES), ...array_keys(self::GLASSES_FAQS)];
        foreach (Standard::where('scheme', 'crs')->orWhereIn('slug', $slugs)->get() as $standard) {
            $backup['standard'][$standard->id] = [
                'description' => $standard->description, 'products' => $standard->products, 'faqs' => $standard->faqs,
                'seo' => $standard->seo, 'updated_at' => $standard->updated_at?->toDateTimeString(),
            ];
        }
        if ($duplicate = Standard::where('slug', self::DUPLICATE)->where('source', 'library')->first()) {
            $backup['duplicate'] = $duplicate->getAttributes();
            unset($backup['standard'][$duplicate->id]);
        }
        foreach (Service::whereIn('path', [...array_keys(self::SERVICES), self::LUMINAIRE_TESTING, self::CRS_SERVICE])->get() as $service) {
            $backup['service'][$service->path] = ['detail' => $service->detail, 'updated_at' => $service->updated_at?->toDateTimeString()];
        }
        foreach (Post::whereIn('slug', [...self::POSTS_10322, ...self::POSTS_16102, ...array_keys(self::POST_CITATIONS)])->get() as $post) {
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
