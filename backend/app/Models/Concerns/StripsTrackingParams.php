<?php

namespace App\Models\Concerns;

use App\Support\TrackingParams;

/** Text and JSON fields are saved without click-tracking parameters on their links (see TrackingParams). */
trait StripsTrackingParams
{
    protected static function bootStripsTrackingParams(): void
    {
        static::saving(function (self $model): void {
            foreach (array_keys($model->getDirty()) as $key) {
                $value = $model->getAttribute($key);
                if (is_string($value) || is_array($value)) {
                    $clean = TrackingParams::clean($value);
                    if ($clean !== $value) {
                        $model->setAttribute($key, $clean);
                    }
                }
            }
        });
    }
}
