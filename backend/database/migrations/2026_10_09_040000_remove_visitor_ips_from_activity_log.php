<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('activity_log')
            ->whereNotNull('subject_type')
            ->where(fn ($q) => $q->where('properties', 'like', '%"ip"%')->orWhere('properties', 'like', '%"user_agent"%'))
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $properties = json_decode($row->properties, true) ?? [];
                    Arr::forget($properties, ['attributes.ip', 'attributes.user_agent', 'old.ip', 'old.user_agent']);
                    DB::table('activity_log')->where('id', $row->id)->update(['properties' => json_encode($properties)]);
                }
            });
    }

    public function down(): void
    {
        //
    }
};
