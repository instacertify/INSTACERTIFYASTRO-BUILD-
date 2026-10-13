<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SLUGS = ['lmpc-certificate-consultant-vadodara-gujarat', 'lmpc-guide-foreign-manufacturers-exporting-packaged-goods-india'];

    /** Created only when the slug is free, so later edits in the admin are never overwritten. */
    public function up(): void
    {
        foreach (self::SLUGS as $i => $slug) {
            if (Post::where('type', 'blog')->where('slug', $slug)->exists()) {
                continue;
            }
            $post = require database_path("data/blog-posts/{$slug}.php");
            $author = Author::firstWhere('slug', $post['author']);
            if (! $author) {
                continue;
            }

            Post::create([
                'type' => 'blog',
                'slug' => $slug,
                'title' => $post['title'],
                'excerpt' => $post['seo']['description'],
                'content' => trim($post['content']),
                'faqs' => $post['faqs'],
                'seo' => $post['seo'],
                'category_id' => Category::where('type', 'blog')->where('slug', $post['category'])->value('id'),
                'author_id' => $author->id,
                'status' => 'published',
                'published_at' => now()->subMinutes($i),
            ]);
        }
    }

    public function down(): void
    {
        Post::where('type', 'blog')->whereIn('slug', self::SLUGS)->delete();
    }
};
