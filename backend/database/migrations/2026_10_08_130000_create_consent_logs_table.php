<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('consent_id')->index();
            $table->string('action', 20)->index();
            $table->json('choices');
            $table->string('policy_version', 20)->index();
            $table->string('page')->nullable();
            $table->string('language', 8)->default('en');
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('marketing_opt_in')->default(false)->after('consent');
            $table->string('consent_version', 20)->nullable()->after('marketing_opt_in');
            $table->timestamp('consented_at')->nullable()->after('consent_version');
            $table->timestamp('anonymised_at')->nullable()->after('synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['marketing_opt_in', 'consent_version', 'consented_at', 'anonymised_at']);
        });
    }
};
