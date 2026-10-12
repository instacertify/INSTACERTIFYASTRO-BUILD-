<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The old fixed icon list, renamed to the Google icons the picker offers. */
    private const RENAMED = ['cpu' => 'memory', 'puzzle' => 'extension', 'sparkles' => 'spa', 'utensils' => 'restaurant'];

    public function up(): void
    {
        Schema::table('industries', function (Blueprint $table) {
            $table->string('icon_image')->nullable()->after('icon');
        });

        foreach (self::RENAMED as $old => $new) {
            DB::table('industries')->where('icon', $old)->update(['icon' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('industries')->where('icon', $new)->update(['icon' => $old]);
        }

        Schema::table('industries', function (Blueprint $table) {
            $table->dropColumn('icon_image');
        });
    }
};
