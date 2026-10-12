<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role')->nullable();
            $table->string('company')->nullable();
            $table->string('short_bio', 500)->nullable();
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->text('credentials')->nullable();
            $table->json('expertise')->nullable();
            $table->string('email')->nullable();
            $table->json('links')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('type', 32)->index();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('type', 32)->index();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('reading_time')->nullable();
            $table->json('faqs')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->json('seo')->nullable();
            $table->timestamps();
            $table->unique(['type', 'slug']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('type', 32)->default('conference');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('url')->nullable();
            $table->string('price')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('image')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->timestamp('published_at')->nullable();
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('standard_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->json('seo')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('standards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->foreignId('standard_category_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('path')->unique();
            $table->longText('description')->nullable();
            $table->string('products_title')->nullable();
            $table->json('products')->nullable();
            $table->json('faqs')->nullable();
            $table->string('image')->nullable();
            $table->string('status', 16)->default('published')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('path')->unique();
            $table->string('template', 32)->default('default')->index();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->json('data')->nullable();
            $table->string('image')->nullable();
            $table->string('status', 16)->default('published')->index();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->json('seo')->nullable();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->string('category', 32)->default('general')->index();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_id')->nullable()->index();
            $table->string('menu', 32)->index();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('title');
            $table->string('url')->nullable();
            $table->string('tagline')->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('status', 16)->default('pending')->index();
            $table->string('source_page')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['redirects', 'subscribers', 'menu_items', 'faqs', 'pages', 'standards', 'standard_categories', 'events', 'posts', 'categories', 'authors'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
