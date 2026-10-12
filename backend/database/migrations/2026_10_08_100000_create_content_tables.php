<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('path')->unique();
            $table->string('slug')->index();
            $table->string('title');
            $table->string('kind', 32)->index();
            $table->string('region', 32)->nullable()->index();
            $table->string('group', 64)->nullable()->index();
            $table->string('summary', 500)->default('');
            $table->string('icon')->nullable();
            $table->string('image')->nullable();
            $table->json('detail')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_published')->default(true)->index();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('industries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('icon', 32)->nullable();
            $table->string('image')->nullable();
            $table->string('summary', 500);
            $table->text('intro')->nullable();
            $table->json('highlights')->nullable();
            $table->json('service_paths')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('context')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('rating')->default(5);
            $table->text('quote');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('industries');
        Schema::dropIfExists('services');
    }
};
