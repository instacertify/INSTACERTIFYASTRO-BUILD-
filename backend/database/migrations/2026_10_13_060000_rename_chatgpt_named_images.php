<?php

use App\Support\Images;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Images uploaded straight from ChatGPT keep its file name ("chatgpt-image-aug-24-2026-….webp") in their public address. */
    private const TABLES = ['posts', 'events', 'pages', 'services', 'standards', 'landing_pages', 'industries', 'faqs', 'settings'];

    public function up(): void
    {
        $renames = [];
        foreach (DB::table('posts')->where('content', 'like', '%chatgpt-image%')->get(['title', 'content']) as $post) {
            preg_match_all('#<img\b[^>]*>#i', $post->content, $tags);
            foreach ($tags[0] as $tag) {
                if (! preg_match('#\bsrc="[^"]*?/storage/([^"]*chatgpt-image[^"/]*)"#i', $tag, $src)) {
                    continue;
                }
                $old = $src[1];
                if (isset($renames[$old])) {
                    continue;
                }
                $alt = preg_match('#\balt="([^"]*)"#i', $tag, $a) ? html_entity_decode($a[1]) : '';
                $new = $this->newPath($old, $alt, $post->title, $renames);
                if ($new !== null) {
                    $renames[$old] = $new;
                }
            }
        }

        $basenames = collect($renames)->mapWithKeys(fn ($new, $old) => [basename($old) => basename($new)])->all();
        if ($basenames === []) {
            return;
        }

        foreach (self::TABLES as $table) {
            $columns = collect(Schema::getColumns($table))
                ->filter(fn ($c) => in_array($c['type_name'], ['text', 'varchar', 'json', 'jsonb', 'longtext', 'mediumtext'], true))
                ->pluck('name')->all();
            $key = $table === 'settings' ? 'key' : 'id';
            DB::table($table)
                ->where(fn ($q) => collect($columns)->each(fn ($c) => $q->orWhere($c, 'like', '%chatgpt-image%')))
                ->get()
                ->each(function (object $row) use ($table, $columns, $key, $basenames): void {
                    $changes = [];
                    foreach ($columns as $column) {
                        if (is_string($row->{$column}) && ($value = strtr($row->{$column}, $basenames)) !== $row->{$column}) {
                            $changes[$column] = $value;
                        }
                    }
                    if ($changes !== []) {
                        DB::table($table)->where($key, $row->{$key})->update($changes);
                        if ($table === 'settings') {
                            Cache::forget("setting:{$row->key}");
                        }
                    }
                });
        }

        $map = storage_path('app/legacy/media-map.json');
        if (File::exists($map)) {
            $entries = json_decode(File::get($map), true) ?: [];
            File::put($map, json_encode(array_map(fn ($path) => is_string($path) ? ($renames[$path] ?? $path) : $path, $entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /** Renames the file on the media disk; null when it is missing, so its links are left alone. */
    private function newPath(string $old, string $alt, string $title, array $taken): ?string
    {
        $disk = Images::disk();
        $words = Str::of($alt)->squish();
        // Short captions such as "Latest Update" say little on their own; the post title gives the topic.
        $name = Str::slug($words->wordCount() >= 4 ? $words : Str::words($title, 8, '').' '.$words);
        $name = Str::limit($name, 80, '');
        $name = rtrim($name ?: 'image', '-');

        $dir = dirname($old);
        $extension = pathinfo($old, PATHINFO_EXTENSION);
        $new = "{$dir}/{$name}.{$extension}";
        for ($i = 2; in_array($new, $taken, true) || ($disk->exists($new) && $new !== $old); $i++) {
            $new = "{$dir}/{$name}-{$i}.{$extension}";
        }

        if (! $disk->exists($old)) {
            return null;
        }
        $disk->move($old, $new);

        return $new;
    }

    public function down(): void {}
};
