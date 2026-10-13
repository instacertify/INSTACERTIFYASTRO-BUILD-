<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use App\Models\Concerns\HasSeo;
use App\Models\Concerns\Publishable;
use App\Models\Concerns\StripsTrackingParams;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** A focused page for paid search campaigns: one offer, one form, no site navigation. */
#[Fillable(['title', 'path', 'status', 'published_at', 'indexable', 'service_path', 'content', 'seo'])]
class LandingPage extends Model
{
    use Audited, HasSeo, Publishable, StripsTrackingParams;

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'indexable' => 'boolean',
        ];
    }

    public function url(): string
    {
        return '/'.$this->path;
    }

    /** Another page, service or landing page already owns this URL. */
    public static function pathTaken(string $path, ?int $ignoreId = null): bool
    {
        $path = trim($path, '/');

        return Page::where('path', $path)->exists()
            || Service::where('path', $path)->exists()
            || static::where('path', $path)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists();
    }
}
