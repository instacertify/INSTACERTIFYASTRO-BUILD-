<?php

namespace App\Http\Resources;

use App\Models\Author;
use App\Support\Images;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Author */
class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'url' => "/author/{$this->slug}",
            'role' => $this->role,
            'company' => $this->company,
            'shortBio' => $this->short_bio,
            'bio' => $this->bio,
            'photo' => Images::url($this->photo),
            'experienceYears' => $this->experience_years,
            'credentials' => $this->credentials,
            'expertise' => $this->expertise ?? [],
            'links' => array_filter($this->links ?? []),
        ];
    }
}
