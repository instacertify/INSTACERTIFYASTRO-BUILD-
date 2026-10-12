<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/** Proof of cookie consent (GDPR Art. 7(1), DPDP Act s. 6(10)). Append-only; deleted with its IP after the retention period. */
#[Fillable(['consent_id', 'action', 'choices', 'policy_version', 'page', 'language', 'ip', 'ip_hash', 'user_agent'])]
#[Hidden(['ip', 'ip_hash', 'user_agent'])]
class ConsentLog extends Model
{
    public const UPDATED_AT = null;

    public const ACTIONS = [
        'accept_all' => 'Accepted all',
        'reject_all' => 'Rejected non-essential',
        'custom' => 'Chose categories',
        'withdraw' => 'Withdrew consent',
    ];

    public const CATEGORIES = ['preferences', 'analytics', 'marketing'];

    protected function casts(): array
    {
        return ['choices' => 'array', 'created_at' => 'datetime'];
    }
}
