<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['posts', 'events'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->unsignedSmallInteger('image_width')->nullable()->after('image_alt');
                $table->unsignedSmallInteger('image_height')->nullable()->after('image_width');
                $table->boolean('discover_ready')->default(false)->index()->after('seo');
                $table->json('discover_issues')->nullable()->after('discover_ready');
            });
        }
    }

    public function down(): void
    {
        foreach (['posts', 'events'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn(['image_width', 'image_height', 'discover_ready', 'discover_issues']);
            });
        }
    }
};
