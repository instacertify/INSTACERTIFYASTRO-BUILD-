<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('synced_at');
        });

        // Earlier enquiries were already handled; only new ones are tracked.
        DB::table('leads')->update(['emailed_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
