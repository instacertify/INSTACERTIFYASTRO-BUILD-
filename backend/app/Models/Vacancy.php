<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'slug', 'department', 'employment_type', 'workplace', 'city', 'region', 'country',
    'experience_min', 'experience_max', 'salary_min', 'salary_max', 'salary_visible', 'openings',
    'summary', 'description', 'skills', 'status', 'published_at', 'closes_at', 'sort',
])]
class Vacancy extends Model
{
    use Audited;

    public const STATUSES = [
        'draft' => 'Draft',
        'open' => 'Open — accepting applications',
        'closed' => 'Closed',
    ];

    /** schema.org JobPosting employmentType values, so Google for Jobs understands them. */
    public const EMPLOYMENT_TYPES = [
        'FULL_TIME' => 'Full-time',
        'PART_TIME' => 'Part-time',
        'CONTRACTOR' => 'Contract',
        'TEMPORARY' => 'Temporary',
        'INTERN' => 'Internship',
    ];

    public const WORKPLACES = [
        'onsite' => 'On-site',
        'hybrid' => 'Hybrid',
        'remote' => 'Remote',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'salary_visible' => 'boolean',
            'published_at' => 'datetime',
            'closes_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Vacancy $vacancy) {
            if ($vacancy->status === 'open' && ! $vacancy->published_at) {
                $vacancy->published_at = now();
            }
        });
    }

    /** Live on the website and accepting applications. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn (Builder $q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('closes_at')->orWhere('closes_at', '>=', today()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'open'
            && ! $this->published_at?->isFuture()
            && ! $this->closes_at?->isBefore(today());
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /** People outside HR who may review this vacancy's applications (e.g. the hiring manager). */
    public function hiringTeam(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vacancy_user');
    }

    public function location(): string
    {
        $place = collect([$this->city, $this->region])->filter()->implode(', ');

        return match ($this->workplace) {
            'remote' => $place ? "Remote (India) · {$place}" : 'Remote (India)',
            'hybrid' => $place ? "{$place} · Hybrid" : 'Hybrid',
            default => $place ?: 'India',
        };
    }

    public function experienceLabel(): ?string
    {
        [$min, $max] = [$this->experience_min, $this->experience_max];

        return match (true) {
            $min === null && $max === null => null,
            $min === 0 && ! $max => 'Freshers welcome',
            $max === null => "{$min}+ years",
            $min === null => "Up to {$max} years",
            $min === $max => "{$min} years",
            default => "{$min}–{$max} years",
        };
    }

    public function url(): string
    {
        return '/careers/'.$this->slug;
    }
}
