<?php

use App\Models\Post;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;

return new class extends Migration
{
    /**
     * Spreads the guides published on 10 Oct 2026 across the previous 20 days. The seed is fixed
     * so every environment gets the same dates, and posts re-dated in the admin are left alone.
     */
    public function up(): void
    {
        $slugs = collect(glob(database_path('data/blog-posts/*.php')))->map(fn (string $file) => basename($file, '.php'));
        $posts = Post::where('type', 'blog')->whereIn('slug', $slugs)
            ->where('published_at', '>=', '2026-10-10 00:00:00')
            ->orderBy('slug')->get(['id', 'slug']);
        if ($posts->isEmpty()) {
            return;
        }

        mt_srand(20261010);
        $days = collect(range(0, 19))->flatMap(fn (int $d) => [$d, $d])->all();
        for ($i = count($days) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$days[$i], $days[$j]] = [$days[$j], $days[$i]];
        }

        foreach ($posts->values() as $i => $post) {
            $date = Carbon::parse('2026-09-20 00:00:00')->addDays($days[$i])
                ->setTime(mt_rand(9, 19), mt_rand(0, 59), mt_rand(0, 59));
            Post::whereKey($post->id)->update(['published_at' => $date, 'updated_at' => $date]);
        }
        mt_srand();
    }

    public function down(): void
    {
        //
    }
};
