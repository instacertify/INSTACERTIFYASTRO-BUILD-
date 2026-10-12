<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'type', 'status', 'name', 'email', 'phone', 'company', 'country', 'manufacturer_type', 'requirement',
    'service_path', 'product', 'timeline', 'message', 'source_page', 'referrer',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid', 'consent', 'marketing_opt_in', 'consent_version', 'consented_at',
    'ip', 'user_agent', 'assigned_to', 'notes', 'follow_up_at', 'value', 'lost_reason', 'synced_at', 'emailed_at', 'anonymised_at',
])]
#[Hidden(['ip', 'user_agent', 'notes'])]
class Lead extends Model
{
    use Audited;

    public const TYPES = [
        'quote' => 'Quote request',
        'callback' => 'Callback',
        'consultation' => 'Free consultation',
        'newsletter' => 'Newsletter',
    ];

    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'qualified' => 'Qualified',
        'proposal' => 'Proposal sent',
        'won' => 'Won',
        'lost' => 'Lost',
    ];

    public const OPEN_STATUSES = ['new', 'contacted', 'qualified', 'proposal'];

    /** "What do you need?" on the contact form. */
    public const REQUIREMENTS = [
        'testing' => 'Testing',
        'certification' => 'Certification',
        'inspection' => 'Inspection',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'marketing_opt_in' => 'boolean',
            'consented_at' => 'datetime',
            'anonymised_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'value' => 'decimal:2',
            'synced_at' => 'datetime',
            'emailed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            $lead->uuid ??= (string) Str::uuid();
        });
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereNotNull('follow_up_at')->where('follow_up_at', '<', now());
    }

    /** Website enquiries (they carry a consent record) the team has not been emailed about after $minutes. */
    public function scopeNotEmailed(Builder $query, int $minutes = 30): Builder
    {
        return $query->whereNull('emailed_at')->whereNotNull('consented_at')
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->where('created_at', '>=', now()->subDays(14));
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_path', 'path');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
