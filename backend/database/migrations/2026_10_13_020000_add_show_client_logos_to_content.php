<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['pages', 'services', 'industries', 'posts', 'events', 'standards'];

    /** Per page: show the "Trusted by" client logo strip after the content. Off until an editor ticks it. */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('show_client_logos')->default(false));
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('show_client_logos'));
        }
    }
};
