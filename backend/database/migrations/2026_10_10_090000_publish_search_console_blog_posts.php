<?php

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Guides planned from Search Console queries (October 2026). A post is only created when its
     * slug is free, so edits made later in the admin are never overwritten.
     */
    public function up(): void
    {
        $authors = Author::pluck('id', 'slug');
        if (! $authors->has(['pankaj-kumar', 'compliance-desk'])) {
            return;
        }
        $categories = Category::where('type', 'blog')->pluck('id', 'slug');
        $files = glob(database_path('data/blog-posts/*.php'));
        sort($files);

        foreach ($files as $i => $file) {
            $slug = basename($file, '.php');
            if (Post::where('type', 'blog')->where('slug', $slug)->exists()) {
                continue;
            }
            $post = require $file;
            Post::create([
                'type' => 'blog',
                'slug' => $slug,
                'title' => $post['title'],
                'excerpt' => $post['seo']['description'],
                'content' => trim($post['content']),
                'faqs' => $post['faqs'],
                'seo' => $post['seo'],
                'category_id' => $categories[$post['category']] ?? null,
                'author_id' => $authors[$post['author']] ?? null,
                'status' => 'published',
                'published_at' => now()->subMinutes($i),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
