<?php

namespace App\Support\Standards;

use App\Models\Standard;
use App\Models\StandardCategory;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Applies the compliance library (database/data/standards/*.json, built by scripts/standards-from-xlsx.py) to the site.
 * Existing editorial pages keep their URL, title, copy and FAQs; only their product rows gain status, fee and testing data.
 * Pages for products we had not written about are created with source "library".
 */
class LibraryImporter
{
    public const CATEGORIES = [
        'mandatory-bis-standard' => ['group' => 'mandatory', 'sort' => 0],
        'mandatory-crs-standards' => ['group' => 'mandatory', 'sort' => 1],
        'voluntary-bis-standards' => ['group' => 'voluntary', 'sort' => 2, 'title' => 'Voluntary BIS Standards',
            'description' => 'For these products, the ISI mark is optional today. Some medical devices controlled by CDSCO are also listed here. A BIS licence can still help you win government tenders and big buyers.'],
        'bee-mandatory-star-labelling' => ['group' => 'bee', 'sort' => 3, 'title' => 'BEE Mandatory Star Labelling',
            'description' => 'These appliances must have a star label from the Bureau of Energy Efficiency (BEE) before they can be sold in India.'],
        'bee-voluntary-star-labelling' => ['group' => 'bee', 'sort' => 4, 'title' => 'BEE Voluntary Star Labelling',
            'description' => 'For these products, the BEE star label is optional. Brands use it to show buyers that a product saves energy.'],
        'iso-management-system' => ['group' => 'management', 'sort' => 5],
    ];

    /** CRS sheet serial number → slugs of the pages we already publish for that product. */
    public const CRS_PAGES = [
        1 => ['mobile-phones'], 2 => ['laptop-notebook-tablets'], 3 => ['laptop-notebook-tablets'], 5 => ['wireless-keyboards'],
        6 => ['point-sale-terminals'], 7 => ['printers-plotters'], 8 => ['scanners'], 9 => ['optical-disc-players'], 10 => ['set-top-box'],
        11 => ['automatic-data-processing-machine'], 12 => ['microwave-ovens'],
        13 => ['plasma-lcd-led-television', 'plasma-lcd-led-television-screen-size-32'], 14 => ['amplifiers-input-power-200w'],
        15 => ['electronic-musical-systems-input-power-200w'], 16 => ['electronic-clocks-mains-powers'], 17 => ['electronics-video-games'],
        18 => ['visual-display-units', 'visuals-display-units-video-monitors'], 19 => ['cctv-cameras-cctv-recorders'],
        20 => ['power-adaptors-it-equipments'], 21 => ['power-adaptors-audio-video'], 22 => ['ups-invertors'],
        23 => ['dc-ac-supplied-electronic-control-gear'], 24 => ['sealed-secondary-cells-batteries'],
        25 => ['power-banks-use-portable-applications'], 26 => ['ballasted-led-lamps'], 27 => ['led-luminaries'],
        28 => ['led-luminaries-road-street-lighting'], 29 => ['led-flood-lights'], 30 => ['led-hand-lamps'],
        31 => ['led-lighting-chains', 'lighting-chain-rope-lights'], 32 => ['led-luminaries-emergency-lighting'],
        33 => ['recessed-led-luminaries'], 34 => ['smart-watches'], 35 => ['smart-speakers'], 36 => ['dimmers-led-products'],
        37 => ['bluetooth-speakers'], 38 => ['usb-external-hard-disk-drive'], 40 => ['television-plasma-lcd-led-tvs'], 41 => ['keyboards'],
        42 => ['usb-barcode-readers'], 46 => ['television-sets'], 47 => ['electronics-video-games'], 48 => ['telephone-answering-machines'],
        49 => ['copying-machines-duplicators'], 50 => ['smart-card-readers'], 51 => ['mail-processing-machines-postage-machines'],
        52 => ['passport-reader'], 53 => ['cash-registers'], 54 => ['automatic-teller-cash-dispensing-machines'], 55 => ['induction-stove'],
        56 => ['rice-cooker'], 57 => ['wireless-microphone'], 58 => ['digital-camera'], 59 => ['video-camera'], 60 => ['webcam'],
        61 => ['usb-external-solid-state-storage-devices-256gb-capacity'], 62 => ['standalone-switch-mode-power-supplies'],
        63 => ['standalone-led-modules-general-lighting'], 65 => ['power-adaptors-audio-video'], 66 => ['ups-inverters-rating'],
        76 => ['cotton-bales'],
    ];

    /** Sheet rows that repeat a product already covered by another row. */
    public const CRS_SKIP = [43, 44, 45];

    public const BEE_SLUGS = [
        'Room Air Conditioner (Fixed' => 'room-air-conditioner-fixed-speed', 'Room AC (Inverter' => 'inverter-air-conditioner',
        'Colour TV' => 'colour-television', 'Electric Storage Water Heater' => 'electric-storage-water-heater',
        'LED Lamp' => 'led-lamp', 'Distribution Transformer' => 'distribution-transformer', 'Chiller' => 'chiller',
        'Office Equipment' => 'office-equipment-printer-copier', 'Computer (Notebook' => 'laptop-notebook-computer',
        'Solid State Inverter' => 'solid-state-inverter-home-ups', 'Diesel Generator Set' => 'diesel-generator-set',
        'Light Commercial Air' => 'light-commercial-air-conditioner', 'High Energy Li-ion' => 'li-ion-battery-energy-storage',
        'Tyres' => 'tyres', 'Induction Hob' => 'induction-hob-cooktop', 'EV Charger' => 'ev-charger-evse', 'Ballast' => 'ballast',
        'Agricultural Pump Set' => 'agricultural-pump-set', 'Ceiling Fan' => 'ceiling-fan',
    ];

    /** @var array<string, int> */
    public array $stats = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, StandardCategory> */
    private array $categories = [];

    private Collection $isi;

    private Collection $crsRows;

    private array $bee = [];

    private array $labs = [];

    private array $upcoming = [];

    public function __construct(private string $dir, private bool $refreshContent = false) {}

    public function run(): void
    {
        $this->load();
        $this->syncCategories();
        $this->crs();
        $this->isiPages();
        $this->bee();
        $this->fillDefaults();
    }

    private function load(): void
    {
        $read = fn (string $file) => json_decode(file_get_contents("{$this->dir}/{$file}.json"), true, flags: JSON_THROW_ON_ERROR);

        $rank = array_flip(array_keys(Standard::REGULATION));
        $this->isi = collect($read('isi'))
            ->map(fn ($row) => $row + ['key' => IsNumber::key($row['is'])])
            ->filter(fn ($row) => $row['key'] && filled($row['title']))
            ->sortBy(fn ($row) => $rank[LibraryData::status($row['status'])[0]])
            ->unique('key')
            ->keyBy('key');

        foreach ($read('labs') as $is => $entries) {
            if ($key = IsNumber::key($is)) {
                $this->labs[$key] = [...$this->labs[$key] ?? [], ...$entries];
            }
        }
        foreach ($read('upcoming') as $row) {
            if ($key = IsNumber::key($row['is'])) {
                $this->upcoming[$key] = $row;
            }
        }
        $this->bee = $read('bee');
        $this->crsRows = collect($read('crs'))->keyBy('sno');
    }

    private function syncCategories(): void
    {
        foreach (self::CATEGORIES as $slug => $def) {
            $category = StandardCategory::firstOrNew(['slug' => $slug]);
            if (! $category->exists || ($this->refreshContent && isset($def['description']))) {
                $category->exists || $this->count('categories created');
                $category->fill(Arr::only($def, ['title', 'description']) + ['is_published' => $category->is_published ?? true]);
                $category->seo = ['title' => $def['title'].' in India', 'description' => $def['description']];
            }
            $category->fill(Arr::only($def, ['group', 'sort']))->save();
            $this->categories[$slug] = $category;
        }
    }

    /** Status, dates, fees and lab prices for one ISI sheet row, in the shape stored on a product row. */
    private function isiFields(array $row): array
    {
        [$status] = LibraryData::status($row['status']);
        [$from, $since] = $status === 'voluntary' ? [null, null] : LibraryData::since($row['order']);
        if (in_array($status, ['upcoming', 'deferred'], true) && isset($this->upcoming[$row['key']])) {
            [$from, $since] = LibraryData::since($this->upcoming[$row['key']]['date']);
            $status = 'upcoming';
        }
        $status = Standard::inForce($status, $from);

        return array_filter([
            'status' => $status,
            'since' => $since,
            'mandatory_from' => $from,
            'order' => LibraryData::order($row['order'], $status),
            'hsn' => $row['hsn8'] ?: $row['hsn4'],
            'sector' => $row['broad'],
            'fee_large' => $row['fees']['large'] ?? null,
            'fee_medium' => $row['fees']['medium'] ?? null,
            'fee_small' => $row['fees']['small'] ?? null,
            'fee_micro' => $row['fees']['micro'] ?? null,
            'unit_fee' => LibraryData::unitFee($row['unit'], $row['slabs'] ?? []),
        ], fn ($v) => $v !== null && $v !== '') + (LibraryData::testing($this->labs[$row['key']] ?? []) ?? []);
    }

    private function crsFields(array $row): array
    {
        [$status] = LibraryData::status($row['status']);
        [$from, $since] = LibraryData::since($row['order']);
        $status = Standard::inForce($status, $from);
        if ($status === 'rescinded' && preg_match_all('/\b\d{1,2}-\d{1,2}-\d{4}\b/', $row['order'], $m) > 1) {
            $since = 'Rescinded '.LibraryData::since(end($m[0]))[1];
        }
        $key = IsNumber::key($row['is']);

        return array_filter([
            'status' => $status,
            'since' => $since,
            'mandatory_from' => $from,
            'order' => LibraryData::order($row['order'], $status),
            'hsn' => $row['hsn8'] ?: $row['hsn4'],
            'sector' => $row['broad'],
            'fee_large' => $row['fees']['large'] ?? null,
            'fee_small' => $row['fees']['small'] ?? null,
            'fee_micro' => $row['fees']['micro'] ?? null,
            'note' => $row['remarks'] ?: null,
        ], fn ($v) => $v !== null && $v !== '') + (($key ? LibraryData::testing($this->labs[$key] ?? []) : null) ?? []);
    }

    /** Keeps the row's own IS number and name (and anything an editor added), replacing the library fields. */
    private static function enrich(array $row, array $fields): array
    {
        return array_merge(Arr::except($row, ['status', 'since', 'mandatory_from', 'order', 'hsn', 'sector', 'fee_large', 'fee_medium',
            'fee_small', 'fee_micro', 'unit_fee', 'test_min', 'test_max', 'labs', 'lab_names', 'note']), $fields);
    }

    private function crs(): void
    {
        $category = $this->categories['mandatory-crs-standards'];
        $bySlug = [];
        foreach (self::CRS_PAGES as $sno => $slugs) {
            foreach ($slugs as $slug) {
                $bySlug[$slug][] = $sno;
            }
        }

        foreach ($bySlug as $slug => $snos) {
            $page = Standard::where('standard_category_id', $category->id)->where('slug', "crs-mark-{$slug}")->first();
            if (! $page) {
                $this->warnings[] = "CRS page crs-mark-{$slug} not found";

                continue;
            }
            $sheet = collect($snos)->map(fn ($sno) => $this->crsRows[$sno] ?? null)->filter()->values();
            $sheetByKey = $sheet->keyBy(fn ($row) => IsNumber::key($row['is']));
            $products = collect($page->products ?? [])->map(function ($row) use ($sheet, $sheetByKey) {
                $source = $sheetByKey[IsNumber::key($row['is_number'] ?? null)] ?? $sheet->first();

                return self::enrich($row, $this->crsFields($source));
            });
            $have = $products->map(fn ($row) => IsNumber::key($row['is_number'] ?? null))->filter()->all();
            foreach ($sheetByKey as $key => $row) {
                if ($key && ! in_array($key, $have, true)) {
                    $products->push(['is_number' => $row['is'], 'name' => $row['product']] + $this->crsFields($row));
                    $have[] = $key;
                }
            }
            $page->products = $products->all();
            $page->scheme = 'crs';
            $this->save($page, 'crs pages enriched');
        }

        $mapped = collect(self::CRS_PAGES)->keys()->merge(self::CRS_SKIP)->all();
        foreach ($this->crsRows->except($mapped) as $row) {
            $name = trim($row['product']);
            $slug = 'crs-mark-'.Str::slug(LibraryContent::shorten($name, 60));
            $page = Standard::firstOrNew(['standard_category_id' => $category->id, 'slug' => $slug]);
            if ($page->exists && $page->source !== 'library') {
                $this->warnings[] = "CRS row {$row['sno']} collides with editorial page {$slug}";

                continue;
            }
            $fields = $this->crsFields($row);
            $page->products = [['is_number' => $row['is'], 'name' => $name] + $fields];
            if (! $page->exists || $this->refreshContent) {
                $page->fill(LibraryContent::crs($name, IsNumber::format($row['is']) ?? $row['is'], $fields));
            }
            $this->saveLibrary($page, 'crs');
        }
    }

    private function isiPages(): void
    {
        $covered = [];
        $mandatory = $this->categories['mandatory-bis-standard'];

        foreach (Standard::where('standard_category_id', $mandatory->id)->where(fn ($q) => $q->whereNull('source')->orWhere('source', '!=', 'library'))->get() as $page) {
            $matched = false;
            $page->products = collect($page->products ?? [])->map(function ($row) use (&$covered, &$matched) {
                $key = IsNumber::key($row['is_number'] ?? null);
                if (! $key || ! $this->isi->has($key)) {
                    return $row;
                }
                $covered[$key] = $matched = true;
                $fields = $this->isiFields($this->isi[$key]);
                // The sheet marks anything it found no QCO for as voluntary; that is too weak to contradict a page we publish as mandatory.
                if (in_array($fields['status'], ['voluntary', 'cdsco'], true)) {
                    $fields = Arr::except($fields, ['status', 'since', 'mandatory_from', 'order']);
                }

                return self::enrich($row, $fields);
            })->all();
            $page->scheme = 'isi';
            $this->save($page, $matched ? 'isi pages enriched' : 'isi pages without a library match');
        }

        $library = Standard::where('source', 'library')->where('scheme', 'isi')->get()
            ->keyBy(fn ($page) => trim((string) $page->is_keys));

        foreach ($this->isi->except(array_keys($covered)) as $key => $row) {
            $this->isiLibraryPage($library[$key] ?? new Standard, $key, $row, $this->refreshContent);
        }
    }

    /**
     * Rebuilds the generated pages for these IS keys from the sheet, copy included, and leaves every other page alone.
     *
     * @param  list<string>  $keys
     */
    public function refreshIsiPages(array $keys): void
    {
        $library = Standard::where('source', 'library')->where('scheme', 'isi')->get()->toBase()
            ->keyBy(fn ($page) => trim((string) $page->is_keys))
            ->only($keys);
        if ($library->isEmpty()) {
            return;
        }

        $this->load();
        $this->categories = StandardCategory::whereIn('slug', array_keys(self::CATEGORIES))->get()->keyBy('slug')->all();
        foreach ($keys as $key) {
            if (isset($library[$key], $this->isi[$key])) {
                $this->isiLibraryPage($library[$key], $key, $this->isi[$key], true);
            }
        }
    }

    private function isiLibraryPage(Standard $page, string $key, array $row, bool $refresh): void
    {
        $fields = $this->isiFields($row);
        $name = LibraryData::productName($row['title']);
        $is = IsNumber::format($row['is']) ?? $row['is'];

        if (! $page->exists) {
            $voluntary = in_array($fields['status'], ['voluntary', 'cdsco', 'rescinded'], true);
            $page->standard_category_id = $this->categories[$voluntary ? 'voluntary-bis-standards' : 'mandatory-bis-standard']->id;
            $page->slug = $this->uniqueSlug($page->standard_category_id, LibraryContent::isiSlug($name, $key));
        } elseif ($this->categoryMismatch($page, $fields['status'])) {
            $this->warnings[] = "{$page->path} is now {$fields['status']}; kept at its URL";
        }

        $page->products = [['is_number' => $is, 'name' => $name] + $fields];
        if (! $page->exists || $refresh) {
            $page->fill(LibraryContent::isi($name, $is, $fields + array_filter(['note' => $row['note'] ?? null])));
        }
        $this->saveLibrary($page, 'isi');
    }

    private function bee(): void
    {
        $fees = collect($this->bee['fees'])->reject(fn ($r) => str_starts_with($r['head'], 'Label Fee'))->values()->all();

        foreach (['mandatory' => true, 'voluntary' => false] as $list => $mandatory) {
            $category = $this->categories["bee-{$list}-star-labelling"];
            foreach ($this->bee[$list] as $p) {
                $slug = 'bee-star-label-'.(collect(self::BEE_SLUGS)->first(fn ($s, $prefix) => str_starts_with($p['product'], $prefix))
                    ?? Str::slug(LibraryContent::shorten(preg_replace('/\s*[(,;].*$/', '', $p['product']), 50)));
                $page = Standard::firstOrNew(['standard_category_id' => $category->id, 'slug' => $slug]);

                [$from, $since] = LibraryData::since($p['regime']);
                [$min, $max, $per] = LibraryData::priceRange($p['testingPrice']);
                $labs = array_values(array_filter(array_map('trim', explode(',', $p['labs']))));
                $row = array_filter([
                    'status' => $mandatory ? 'mandatory' : 'voluntary',
                    'since' => $since,
                    'mandatory_from' => $from,
                    'sector' => 'Energy efficiency (BEE)',
                    'test_min' => $min,
                    'test_max' => $max,
                    'labs' => count($labs) ?: null,
                    'lab_names' => implode('; ', array_slice($labs, 0, 5)) ?: null,
                ], fn ($v) => $v !== null && $v !== '');

                preg_match_all('/\bIS(?:\/IEC)?\s*\d+(?:\s*\((?:Part|Pt)\s*[\d&]+\))?(?:-\d+(?:-\d+)?)?/i', $p['testStandard'], $m);
                $standards = $m[0] ?: [$p['testStandard']];
                $page->products = array_map(fn ($is) => ['is_number' => $is, 'name' => $p['product']] + $row, array_values(array_unique($standards)));
                $page->facts = array_values(array_filter([
                    ['label' => 'Status', 'value' => $p['regime']],
                    ['label' => 'Test standard', 'value' => $p['testStandard']],
                    ['label' => 'Star rating table', 'value' => LibraryData::order($p['ratingTable'], 'mandatory')],
                    ['label' => 'Testing charges', 'value' => $min ? LibraryContent::range($min, $max).($per ? " {$per}" : '') : $p['testingPrice']],
                    ['label' => 'Labs', 'value' => $p['labs']],
                    ['label' => 'Label fee', 'value' => LibraryContent::beeLabelFee($p['labelFee'])],
                    ['label' => 'Registration fees', 'value' => LibraryContent::rs($p['registrationFee'])],
                ], fn ($fact) => filled($fact['value'])));

                if (! $page->exists || $this->refreshContent) {
                    $page->fill(LibraryContent::bee($p, $mandatory, $fees));
                }
                $this->saveLibrary($page, 'bee');
            }
        }
    }

    /** Editorial pages the library does not cover still get a status that matches the section they sit in. */
    private function fillDefaults(): void
    {
        foreach (['mandatory-bis-standard' => ['isi', 'mandatory'], 'mandatory-crs-standards' => ['crs', 'mandatory'], 'iso-management-system' => ['iso', null]] as $slug => [$scheme, $status]) {
            Standard::where('standard_category_id', $this->categories[$slug]->id)->whereNull('scheme')->update(['scheme' => $scheme]);
            if ($status) {
                $this->stats['status defaulted'] = ($this->stats['status defaulted'] ?? 0)
                    + Standard::where('standard_category_id', $this->categories[$slug]->id)->whereNull('regulation_status')->update(['regulation_status' => $status]);
            }
        }
    }

    private function categoryMismatch(Standard $page, string $status): bool
    {
        $voluntary = in_array($status, ['voluntary', 'cdsco', 'rescinded'], true);

        return $page->standard_category_id !== ($this->categories[$voluntary ? 'voluntary-bis-standards' : 'mandatory-bis-standard'] ?? null)?->id;
    }

    private function uniqueSlug(int $categoryId, string $slug): string
    {
        $candidate = $slug;
        for ($i = 2; Standard::where('standard_category_id', $categoryId)->where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$slug}-{$i}";
        }

        return $candidate;
    }

    private function saveLibrary(Standard $page, string $scheme): void
    {
        $created = ! $page->exists;
        $page->scheme = $scheme;
        $page->source = 'library';
        if ($created) {
            $page->status = 'published';
            $page->published_at = now();
        }
        $this->save($page, "{$scheme} pages ".($created ? 'created' : 'updated'));
    }

    private function save(Standard $page, string $stat): void
    {
        self::keepImages($page);
        if ($page->isDirty()) {
            $page->save();
        }
        $this->count($stat);
    }

    /** Product photos are uploaded in the admin and are not in the sheets, so rebuilt rows take them back by IS number. */
    private static function keepImages(Standard $page): void
    {
        $images = collect($page->getOriginal('products') ?? [])
            ->filter(fn (array $row) => filled($row['image'] ?? null))
            ->mapWithKeys(fn (array $row) => [(string) IsNumber::key($row['is_number'] ?? null) => $row['image']]);
        if ($images->isEmpty()) {
            return;
        }

        $page->products = collect($page->products ?? [])
            ->map(fn (array $row) => filled($row['image'] ?? null) || ! isset($images[(string) IsNumber::key($row['is_number'] ?? null)])
                ? $row
                : $row + ['image' => $images[(string) IsNumber::key($row['is_number'] ?? null)]])
            ->all();
    }

    private function count(string $stat): void
    {
        $this->stats[$stat] = ($this->stats[$stat] ?? 0) + 1;
    }

    public static function inTransaction(string $dir, bool $refreshContent, bool $dryRun): self
    {
        $importer = new self($dir, $refreshContent);
        DB::beginTransaction();
        try {
            activity()->withoutLogs(fn () => $importer->run());
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $importer;
    }
}
