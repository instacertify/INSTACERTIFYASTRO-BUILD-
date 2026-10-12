<?php

namespace App\Http\Resources;

use App\Models\Vacancy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vacancy */
class VacancyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'url' => $this->url(),
            'title' => $this->title,
            'department' => $this->department,
            'employmentType' => $this->employment_type,
            'employmentTypeLabel' => Vacancy::EMPLOYMENT_TYPES[$this->employment_type] ?? $this->employment_type,
            'workplace' => $this->workplace,
            'workplaceLabel' => Vacancy::WORKPLACES[$this->workplace] ?? $this->workplace,
            'city' => $this->city,
            'region' => $this->region,
            'country' => $this->country,
            'location' => $this->location(),
            'experience' => $this->experienceLabel(),
            'experienceMin' => $this->experience_min,
            'experienceMax' => $this->experience_max,
            'salary' => $this->salary_visible && ($this->salary_min || $this->salary_max)
                ? ['min' => $this->salary_min, 'max' => $this->salary_max, 'currency' => 'INR', 'unit' => 'YEAR']
                : null,
            'openings' => $this->openings,
            'summary' => $this->summary,
            'description' => $this->description,
            'skills' => array_values(array_filter($this->skills ?? [])),
            'postedAt' => $this->published_at?->toIso8601String(),
            'closesAt' => $this->closes_at?->toDateString(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
