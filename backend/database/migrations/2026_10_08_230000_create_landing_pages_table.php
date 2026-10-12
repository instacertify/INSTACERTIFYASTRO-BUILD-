<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('path')->unique();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->boolean('indexable')->default(false);
            $table->string('service_path')->nullable();
            $table->json('content')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('utm_term', 150)->nullable()->after('utm_campaign');
            $table->string('gclid', 255)->nullable()->after('utm_term');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['utm_term', 'gclid']);
        });
        Schema::dropIfExists('landing_pages');
    }
};
