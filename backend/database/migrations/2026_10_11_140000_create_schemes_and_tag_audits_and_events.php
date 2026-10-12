<?php

use App\Models\Event;
use App\Models\LandingPage;
use App\Models\Post;
use App\Models\Scheme;
use App\Support\SchemeClassifier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Landing page => the certification tags its "Photos of our work" row shows. Pages that already set some keep theirs. */
    private const LANDING_SCHEMES = [
        'bis-certification' => ['bis-isi', 'bis-crs'],
        'epr' => ['epr'],
        'g-mark' => ['g-mark'],
        'imei' => ['imei'],
        'ingress-protection' => ['testing'],
        'emc-testing' => ['testing'],
        'food' => ['testing'],
        'nutritional' => ['testing'],
        'iso' => ['iso'],
        'lmpc' => ['lmpc'],
        'legal-metrology' => ['lmpc'],
        'msds' => ['msds'],
        'sds' => ['msds'],
        'saber' => ['saber'],
        'soncap' => ['soncap'],
    ];

    public function up(): void
    {
        Schema::create('schemes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
        Schema::create('post_scheme', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->primary(['post_id', 'scheme_id']);
        });
        Schema::create('event_scheme', function (Blueprint $table) {
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained()->cascadeOnDelete();
            $table->primary(['event_id', 'scheme_id']);
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->string('work_type', 20)->nullable()->after('type');
        });

        $sort = 0;
        foreach (SchemeClassifier::SCHEMES as $slug => $name) {
            Scheme::create(['name' => $name, 'slug' => $slug, 'sort' => ++$sort]);
        }

        Post::where('type', 'audit')->each(function (Post $post) {
            $post->schemes()->sync(Scheme::suggest($post->title, $post->excerpt ?: $post->content));
            $post->work_type = SchemeClassifier::workType($post->title, $post->excerpt ?: $post->content);
            $post->saveQuietly();
        });
        Event::with('industryCategory')->each(function (Event $event) {
            $event->schemes()->sync(Scheme::suggest($event->title, $event->excerpt, $event->industryCategory?->name));
        });

        LandingPage::each(function (LandingPage $page) {
            $content = $page->content ?? [];
            if (filled($content['work']['schemes'] ?? null)) {
                return;
            }
            $key = $page->path.' '.$page->service_path;
            $slugs = collect(self::LANDING_SCHEMES)->filter(fn ($s, string $needle) => str_contains($key, $needle))->flatten()->unique()->values()->all();
            if ($slugs) {
                $content['work'] = [...($content['work'] ?? []), 'schemes' => $slugs];
                $page->update(['content' => $content]);
            }
        });
    }

    public function down(): void
    {
        LandingPage::each(function (LandingPage $page) {
            $content = $page->content ?? [];
            if (isset($content['work']['schemes']) || isset($content['work']['types'])) {
                unset($content['work']['schemes'], $content['work']['types']);
                $page->update(['content' => $content]);
            }
        });
        Schema::table('posts', fn (Blueprint $table) => $table->dropColumn('work_type'));
        Schema::dropIfExists('event_scheme');
        Schema::dropIfExists('post_scheme');
        Schema::dropIfExists('schemes');
    }
};
