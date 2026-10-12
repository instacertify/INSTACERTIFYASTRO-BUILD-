<?php

use App\Support\IndustryClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Legacy news categories that already name an industry; used when the title doesn't. */
    private const NEWS_CATEGORIES = [
        'electronics-electrical' => 'Electrical & Electronics',
        'chemicals' => 'Chemicals',
        'metals-alloys' => 'Non-Ferrous Metals',
        'plastics-polymers' => 'PVC & Plastic Products',
        'food-and-beverages' => 'Food, Dairy & Beverages',
        'toys' => 'Toys',
        'glass' => 'Glass & Ceramics',
    ];

    public function up(): void
    {
        Schema::create('industry_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('industry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (['posts', 'events', 'standards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('industry_category_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        $names = collect(IndustryClassifier::INDUSTRIES)->keys()
            ->merge(DB::table('standards')->whereNotNull('sector')->where('sector', '!=', '')->distinct()->pluck('sector'))
            ->map(fn ($name) => trim($name))->unique(fn ($name) => Str::slug($name))->sort()->values();
        $industries = DB::table('industries')->pluck('id', 'slug');

        foreach ($names as $i => $name) {
            DB::table('industry_categories')->insert([
                'name' => $name,
                'slug' => Str::slug($name),
                'industry_id' => $industries[IndustryClassifier::INDUSTRY_PAGES[$name] ?? ''] ?? null,
                'sort' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $ids = DB::table('industry_categories')->pluck('id', 'name');

        foreach ($ids as $name => $id) {
            DB::table('standards')->where('sector', $name)->update(['industry_category_id' => $id]);
        }

        $newsCategories = DB::table('categories')->where('type', 'news')->pluck('slug', 'id');
        foreach (DB::table('posts')->get(['id', 'type', 'title', 'excerpt', 'category_id']) as $post) {
            $name = IndustryClassifier::classify($post->title, $post->excerpt)
                ?? ($post->type === 'news' ? self::NEWS_CATEGORIES[$newsCategories[$post->category_id] ?? ''] ?? null : null);
            if ($name && isset($ids[$name])) {
                DB::table('posts')->where('id', $post->id)->update(['industry_category_id' => $ids[$name]]);
            }
        }

        foreach (DB::table('events')->get(['id', 'title', 'excerpt']) as $event) {
            $name = IndustryClassifier::classify($event->title, $event->excerpt);
            if ($name && isset($ids[$name])) {
                DB::table('events')->where('id', $event->id)->update(['industry_category_id' => $ids[$name]]);
            }
        }
    }

    public function down(): void
    {
        foreach (['posts', 'events', 'standards'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('industry_category_id');
            });
        }

        Schema::dropIfExists('industry_categories');
    }
};
