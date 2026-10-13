<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    private const SLUG = 'bis-crs-exemption-highly-specialized-equipment-hse';

    /** Created only when the slug is free, so later edits in the admin are never overwritten. */
    public function up(): void
    {
        if (Post::where('type', 'blog')->where('slug', self::SLUG)->exists()) {
            return;
        }
        $post = require database_path('data/blog-posts/'.self::SLUG.'.php');
        $author = Author::firstWhere('slug', $post['author']);
        if (! $author) {
            return;
        }

        Post::create([
            'type' => 'blog',
            'slug' => self::SLUG,
            'title' => $post['title'],
            'excerpt' => $post['seo']['description'],
            'content' => trim($post['content']),
            'faqs' => $post['faqs'],
            'seo' => $post['seo'],
            'category_id' => Category::where('type', 'blog')->where('slug', $post['category'])->value('id'),
            'author_id' => $author->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function down(): void
    {
        Post::where('type', 'blog')->where('slug', self::SLUG)->delete();
    }
};
