<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('type', 20)->default('branch');
            $table->text('street');
            $table->string('city', 80)->nullable();
            $table->string('region', 80)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('country', 2)->default('IN');
            $table->string('landmark')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('contact_person', 80)->nullable();
            $table->string('hours', 120)->nullable();
            $table->text('map')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        $setting = DB::table('settings')->where('key', 'site')->first();
        $site = $setting ? json_decode($setting->value, true) : null;
        if (! is_array($site) || ! array_key_exists('offices', $site)) {
            return;
        }

        foreach (array_values($site['offices'] ?? []) as $i => $office) {
            if (blank($office['address'] ?? null)) {
                continue;
            }
            DB::table('offices')->insert([
                'name' => $office['city'] ?: 'Office '.($i + 1),
                'type' => $i === 0 ? 'head' : 'branch',
                'street' => $office['address'],
                'phone' => $office['phone'] ?? null,
                'map' => $office['map'] ?? null,
                'sort' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        unset($site['offices']);
        DB::table('settings')->where('key', 'site')->update(['value' => json_encode($site)]);
        Cache::forget('setting:site');
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
