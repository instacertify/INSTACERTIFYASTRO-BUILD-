<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'vacancy_id', 'status', 'name', 'email', 'phone', 'city', 'linkedin_url', 'experience_years',
    'current_ctc', 'expected_ctc', 'notice_period', 'cover_letter', 'cv_path', 'cv_name', 'cv_size',
    'rating', 'notes', 'source_page', 'utm_source', 'consent', 'consent_version', 'consented_at',
    'ip', 'user_agent', 'anonymised_at',
])]
#[Hidden(['ip', 'user_agent', 'notes', 'cv_path'])]
class JobApplication extends Model
{
    use LogsActivity;

    public const STATUSES = [
        'new' => 'New',
        'screening' => 'Screening',
        'shortlisted' => 'Shortlisted',
        'interview' => 'Interview',
        'offer' => 'Offer made',
        'hired' => 'Hired',
        'rejected' => 'Not selected',
    ];

    public const STATUS_COLORS = [
        'new' => 'info',
        'screening' => 'gray',
        'shortlisted' => 'primary',
        'interview' => 'warning',
        'offer' => 'warning',
        'hired' => 'success',
        'rejected' => 'danger',
    ];

    public const NOTICE_PERIODS = [
        'immediate' => 'Immediate',
        '15-days' => '15 days',
        '30-days' => '30 days',
        '60-days' => '60 days',
        '90-days' => '90 days or more',
    ];

    public static function cvDisk(): string
    {
        return config('instacertify.careers.cv_disk');
    }

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'consented_at' => 'datetime',
            'anonymised_at' => 'datetime',
            'experience_years' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (JobApplication $application) => $application->uuid ??= (string) Str::uuid());
        static::deleted(fn (JobApplication $application) => $application->deleteCv());
    }

    /** Only the pipeline is audited; candidate details stay out of the activity log. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'rating', 'vacancy_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(Vacancy::class);
    }

    /** Applications this user may see: everything for HR/admin, otherwise only their hiring-team vacancies. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->canManageCareers()
            ? $query
            : $query->whereHas('vacancy.hiringTeam', fn (Builder $q) => $q->whereKey($user->getKey()));
    }

    public function deleteCv(): void
    {
        if ($this->cv_path) {
            Storage::disk(self::cvDisk())->delete($this->cv_path);
        }
    }
}
