<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Hides "How we stop applications getting stuck" on a home page saved from the admin; it can be switched back on there. */
    public function up(): void
    {
        $this->setVisible(true, false);
    }

    public function down(): void
    {
        $this->setVisible(false, true);
    }

    private function setVisible(bool $from, bool $to): void
    {
        $setting = Setting::where('key', 'home')->first();
        if (! is_array($setting?->value['whyUs'] ?? null) || ($setting->value['whyUs']['visible'] ?? true) !== $from) {
            return;
        }

        $value = $setting->value;
        $value['whyUs']['visible'] = $to;
        $setting->value = $value;
        $setting->save();
    }
};
