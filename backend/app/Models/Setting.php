<?php

namespace App\Models;

use App\Support\AuditTrail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(function (Setting $s) {
            Cache::forget("setting:{$s->key}");
            AuditTrail::settingSaved($s);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting:{$key}", fn () => static::find($key)?->value) ?? $default;
    }
}
