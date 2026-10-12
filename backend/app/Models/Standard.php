<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use App\Support\Standards\IsNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'legacy_id', 'source', 'standard_category_id', 'title', 'slug', 'path', 'scheme', 'regulation_status', 'mandatory_from',
    'mandatory_since', 'sector', 'industry_category_id', 'description', 'products_title', 'products', 'facts', 'faqs', 'image', 'image_alt', 'status',
    'published_at', 'sort', 'seo',
])]
class Standard extends Model
{
    use Audited, HasSeo, Publishable;

    public const SCHEMES = [
        'isi' => 'BIS ISI mark (Scheme-I)',
        'crs' => 'BIS CRS registration (Scheme-II)',
        'bee' => 'BEE star label',
        'iso' => 'ISO management system',
    ];

    /** Ordered strongest first: a page covering several products takes the strongest status among them. */
    public const REGULATION = [
        'mandatory' => 'Mandatory',
        'upcoming' => 'Mandatory soon',
        'deferred' => 'Coming – date not set',
        'cdsco' => 'Medical device (CDSCO)',
        'voluntary' => 'Voluntary',
        'rescinded' => 'No longer mandatory',
    ];

    public const REGULATION_COLORS = [
        'mandatory' => 'danger', 'upcoming' => 'warning', 'deferred' => 'warning',
        'cdsco' => 'info', 'voluntary' => 'gray', 'rescinded' => 'gray',
    ];

    protected function casts(): array
    {
        return ['products' => 'array', 'facts' => 'array', 'faqs' => 'array', 'mandatory_from' => 'date'];
    }

    protected static function booted(): void
    {
        static::saving(function (Standard $standard) {
            if ($standard->isDirty(['slug', 'standard_category_id']) && $standard->category) {
                $standard->path = trim($standard->category->url(), '/').'/'.$standard->slug;
            }
            if ($standard->isDirty('products')) {
                $standard->deriveFromProducts();
            }
            // The sector text (shown on the website) and the industry subcategory always name the same industry.
            if ($standard->isDirty('sector')) {
                $standard->industry_category_id = IndustryCategory::idFor($standard->sector);
            } elseif ($standard->isDirty('industry_category_id')) {
                $standard->sector = IndustryCategory::find($standard->industry_category_id)?->name;
            }
        });
    }

    /** An "upcoming" QCO whose enforcement date has passed is simply mandatory. */
    public static function inForce(?string $status, ?string $from): ?string
    {
        return $status === 'upcoming' && $from && $from <= now()->toDateString() ? 'mandatory' : $status;
    }

    public function currentStatus(): ?string
    {
        return self::inForce($this->regulation_status, $this->mandatory_from?->toDateString());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StandardCategory::class, 'standard_category_id');
    }

    public function industryCategory(): BelongsTo
    {
        return $this->belongsTo(IndustryCategory::class);
    }

    public function url(): string
    {
        return '/'.$this->path;
    }

    /** Other published standards that cite any of the same Indian Standards (e.g. the BEE label and ISI mark for ACs). */
    public function scopeSharingStandardsWith(Builder $query, Standard $standard): Builder
    {
        $keys = array_filter(explode(' ', (string) $standard->is_keys));

        return $query->whereKeyNot($standard->getKey())
            ->where(fn (Builder $q) => collect($keys)->each(fn ($key) => $q->orWhere('is_keys', 'like', "% {$key} %")))
            ->when($keys === [], fn (Builder $q) => $q->whereRaw('0 = 1'));
    }

    /** Page-level status, dates and search keys follow the product rows, so admins only edit the rows. */
    public function deriveFromProducts(): void
    {
        $rows = collect($this->products ?? []);
        $keys = $rows->map(fn ($row) => IsNumber::key($row['is_number'] ?? null))->filter()->unique();
        $this->is_keys = $keys->isEmpty() ? null : ' '.$keys->implode(' ').' ';

        $rank = array_flip(array_keys(self::REGULATION));
        $rated = $rows->filter(fn ($row) => isset($rank[$row['status'] ?? '']));
        if ($rated->isEmpty()) {
            return;
        }

        $status = $rated->sortBy(fn ($row) => $rank[$row['status']])->first()['status'];
        $lead = $rated->where('status', $status)
            ->sortBy(fn ($row) => [blank($row['mandatory_from'] ?? null), $row['mandatory_from'] ?? '', blank($row['since'] ?? null)])
            ->first();

        $this->regulation_status = $status;
        $this->mandatory_from = filled($lead['mandatory_from'] ?? null) ? $lead['mandatory_from'] : null;
        $this->mandatory_since = $lead['since'] ?? ($this->mandatory_from ? Carbon::parse($this->mandatory_from)->format('j M Y') : null);
        $this->sector = $rated->pluck('sector')->filter()->countBy()->sortDesc()->keys()->first() ?? $this->sector;
    }
}
