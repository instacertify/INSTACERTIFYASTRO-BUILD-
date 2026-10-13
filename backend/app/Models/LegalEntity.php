<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'market', 'country', 'registration_label', 'registration_number',
    'address', 'phone', 'email', 'is_primary', 'show_on_website', 'sort',
])]
class LegalEntity extends Model
{
    use Audited;

    public const COUNTRIES = [
        'IN' => 'India', 'US' => 'United States', 'GB' => 'United Kingdom', 'AE' => 'United Arab Emirates',
        'SG' => 'Singapore', 'DE' => 'Germany', 'CA' => 'Canada', 'AU' => 'Australia',
    ];

    /** Usual registration number per country, suggested in the admin. */
    public const REGISTRATION_LABELS = ['IN' => 'CIN', 'US' => 'EIN', 'GB' => 'Company No.', 'AE' => 'Trade Licence No.', 'SG' => 'UEN'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'show_on_website' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(function (LegalEntity $entity) {
            if ($entity->is_primary && ($entity->wasRecentlyCreated || $entity->wasChanged('is_primary'))) {
                static::whereKeyNot($entity->getKey())->where('is_primary', true)->get()->each->update(['is_primary' => false]);
            }
        });
    }

    /** Every entity for the website; the primary one signs the legal notices, the shown ones appear in the footer. */
    public static function forSite(): array
    {
        return static::orderByDesc('is_primary')->orderBy('sort')->orderBy('id')->get()
            ->map(fn (LegalEntity $entity) => [
                'name' => $entity->name,
                'market' => $entity->market,
                'country' => $entity->country,
                'registrationLabel' => $entity->registration_label,
                'registrationNumber' => $entity->registration_number,
                'address' => $entity->address,
                'phone' => $entity->phone,
                'email' => $entity->email,
                'primary' => $entity->is_primary,
                'showOnWebsite' => $entity->show_on_website,
            ])
            ->all();
    }
}
