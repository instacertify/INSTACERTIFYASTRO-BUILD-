<?php

namespace App\Models\Concerns;

use App\Support\Discover;
use App\Support\Images;

trait TracksDiscoverReadiness
{
    public function initializeTracksDiscoverReadiness(): void
    {
        $this->mergeCasts(['discover_ready' => 'boolean', 'discover_issues' => 'array']);
    }

    public static function bootTracksDiscoverReadiness(): void
    {
        static::saving(fn (self $record) => $record->refreshDiscover());
    }

    public function refreshDiscover(bool $remeasure = false): void
    {
        if ($remeasure || $this->isDirty('image') || ($this->image && ! $this->image_width)) {
            [$this->image_width, $this->image_height] = Images::dimensions($this->image) ?? [null, null];
        }

        $checks = Discover::checks($this);
        $this->discover_ready = Discover::ready($checks);
        $this->discover_issues = collect($checks)->reject(fn (array $c) => $c['ok'])->pluck('label')->values()->all() ?: null;
    }
}
