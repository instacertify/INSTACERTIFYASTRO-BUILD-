<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Links pasted in from search results or ChatGPT answers: a Google search instead of the real page,
     * and the "?utm_source=chatgpt.com" tag ChatGPT adds to every link it cites.
     */
    private const LINKS = [
        'https://www.google.com/search?q=https://www.mca.gov.in' => 'https://www.mca.gov.in/',
        'https://www.google.com/search?q=%23' => '/contact',
    ];

    /** The organiser's site no longer loads (expired certificate), so the event page drops its website button. */
    private const DEAD_EVENT_URLS = ['https://en.cisile.com.cn/'];

    public function up(): void
    {
        DB::table('posts')
            ->where(fn ($q) => $q->where('content', 'like', '%utm_source=chatgpt.com%')->orWhere('content', 'like', '%google.com/search?q=%'))
            ->get(['id', 'content'])
            ->each(function (object $post): void {
                $content = preg_replace_callback('#<a\b([^>]*?)\bhref="([^"]+)"([^>]*)>#i', function (array $m): string {
                    $href = html_entity_decode($m[2]);
                    $target = self::LINKS[$href] ?? preg_replace('#([?&])utm_source=chatgpt\.com(&|$)#i', '$1', $href);
                    $target = rtrim($target, '?&');
                    if ($target === $href) {
                        return $m[0];
                    }
                    $attrs = $m[1].$m[3];
                    if (str_starts_with($target, '/')) {
                        $attrs = preg_replace('#\s+(?:target|rel)="[^"]*"#i', '', $attrs);
                    }

                    return '<a href="'.htmlspecialchars($target).'"'.rtrim($attrs).'>';
                }, $post->content);

                if ($content !== $post->content) {
                    DB::table('posts')->where('id', $post->id)->update(['content' => $content]);
                }
            });

        DB::table('events')->whereIn('url', self::DEAD_EVENT_URLS)->update(['url' => null]);
    }

    public function down(): void {}
};
