<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Support\MapEmbed;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'type', 'street', 'city', 'region', 'postal_code', 'country', 'landmark',
    'phone', 'email', 'contact_person', 'hours', 'map', 'is_published', 'sort',
])]
class Office extends Model
{
    use Audited;

    public const TYPES = [
        'head' => 'Head office',
        'branch' => 'Branch office',
        'lab' => 'Testing lab',
        'liaison' => 'Liaison / sales office',
    ];

    public const TYPE_COLORS = ['head' => 'warning', 'branch' => 'info', 'lab' => 'success', 'liaison' => 'gray'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (Office $office) {
            if ($office->type === 'head' && ($office->wasRecentlyCreated || $office->wasChanged('type'))) {
                static::whereKeyNot($office->getKey())->where('type', 'head')->get()->each->update(['type' => 'branch']);
            }
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /** One line as printed on the website, e.g. "A-34, Sector 63 A, Noida, Uttar Pradesh 201301". */
    public function fullAddress(): string
    {
        $street = trim(preg_replace('/\s+/', ' ', $this->street));
        $tail = collect([$this->city, trim("{$this->region} {$this->postal_code}")])
            ->filter()
            ->reject(fn (string $part) => str_contains(mb_strtolower($street), mb_strtolower($part)))
            ->implode(', ');

        return rtrim($street, ', ').($tail !== '' ? ", {$tail}" : '');
    }

    public function mapEmbed(): string
    {
        return MapEmbed::src($this->map) ?? MapEmbed::fromAddress($this->fullAddress());
    }

    public function mapLink(): string
    {
        return MapEmbed::directions($this->fullAddress());
    }
}
