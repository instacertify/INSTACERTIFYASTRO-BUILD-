<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait Audited
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            // Visitor IPs have their own retention period; the audit log must not keep a copy.
            ->logExcept(['ip', 'user_agent', 'password', 'remember_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
