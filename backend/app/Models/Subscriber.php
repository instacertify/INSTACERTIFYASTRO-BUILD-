<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['email', 'status', 'source_page', 'ip', 'confirmed_at', 'unsubscribed_at'])]
#[Hidden(['ip'])]
class Subscriber extends Model
{
    use Audited;

    public const STATUSES = [
        'pending' => 'Awaiting confirmation',
        'active' => 'Active',
        'unsubscribed' => 'Unsubscribed',
    ];

    protected function casts(): array
    {
        return ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    /** Only staff changes are audited: public sign-ups would leave emails in the log after the subscriber is deleted. */
    protected static function booted(): void
    {
        $staffOnly = function (Subscriber $subscriber) {
            if (! auth()->check()) {
                $subscriber->disableLogging();
            }
        };

        static::saving($staffOnly);
        static::deleting($staffOnly);
    }
}
