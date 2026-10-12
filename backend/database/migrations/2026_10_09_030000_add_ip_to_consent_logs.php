<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consent_logs', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->after('language');
        });
    }

    public function down(): void
    {
        Schema::table('consent_logs', function (Blueprint $table) {
            $table->dropColumn('ip');
        });
    }
};
