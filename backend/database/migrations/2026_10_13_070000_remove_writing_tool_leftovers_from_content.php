<?php

use App\Models\Event;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lines left over from writing tools and templates that tell readers and search engines the text was generated:
 * articles describing themselves as "SEO-optimized" or by word count, an unfilled "[Insert Jurisdiction]" in the terms,
 * descriptions copied from another page, and two near-copies of other posts (one titled "SEO-Friendly Guide").
 */
return new class extends Migration
{
    private const APOSTROPHE = '(?:’|&rsquo;|&\#0?39;|\')';

    /** Near-copy => the post it repeats. The copy's address keeps answering with that post (canonical to it). */
    private const DUPLICATES = [
        'msds-certificate-sds-safety-data-sheet-complete-seo-friendly-guide-for-manufacturers-exporters-shippers' => 'msds-certificate-sds-safety-data-sheet-complete-guide-for-manufacturers-exporters-shippers',
        'bis-certification-for-toys-in-india-complete-guide-for-stem-wooden-learning-toys-cost-msme-e-commerce' => 'bis-certification-for-toys-in-india',
    ];

    private const COPIED_DESCRIPTION = [
        'news' => [
            'is-16102-part-1-a-complete-guide-for-led-lamp' => 'IS 16102 (Part 1) explained: scope, safety requirements and test parameters for LED lamps, and what they mean for BIS certification in India.',
        ],
        'events' => [
            'medicall-2025' => 'Medicall 2025 at Pragati Maidan, New Delhi: a leading medical equipment trade show for device makers, hospitals, distributors and suppliers.',
        ],
    ];

    public function up(): void
    {
        $a = self::APOSTROPHE;
        $postFixes = [
            '#This comprehensive, SEO-optimized blog[^.]*?spans over [\d,]+ words and serves as your ultimate guide to BIS CRS\.#u' => 'This guide covers BIS CRS end to end.',
            '#This detailed guide, updated for October 2025 words and covers#u' => 'This guide, updated for October 2025, covers',
            '#This SEO-optimized guide explains#u' => 'This guide explains',
            '#This\s+(?:<strong>)?[\d,]+\+?\s*-?word guide(?:</strong>)?#iu' => 'This guide',
            "#<p>\s*Let{$a}s dive (?:in|deeper)[.!]?\s*</p>#iu" => '',
            "#\s*Let{$a}s dive (?:in|deeper)[.!]#iu" => '',
            '#(?:<em>)?\[Insert phone \| email \| enquiry form link\](?:</em>)?#u' => 'Phone: <a href="tel:+919999118039">+91 99991 18039</a> | Email: <a href="mailto:contact@instacertify.com">contact@instacertify.com</a> | <a href="/contact">Enquiry form</a>',
        ];
        $this->rewrite('posts', ['%SEO-optimized%', '%word guide%', '%October 2025 words%', '%dive in%', '%dive deeper%', '%[Insert phone%'], $postFixes);
        $this->rewrite('pages', ['%[Insert Jurisdiction]%'], [
            '#the laws of \[Insert Jurisdiction\]#u' => 'the laws of India',
            '#the courts of \[Insert Jurisdiction\]#u' => 'the courts at Delhi',
            '#\[Insert Jurisdiction\]#u' => 'India',
        ]);

        foreach (self::DUPLICATES as $copy => $original) {
            $post = Post::ofType('blog')->firstWhere('slug', $copy);
            $kept = Post::ofType('blog')->published()->firstWhere('slug', $original);
            if (! $post || ! $kept) {
                continue;
            }
            $post->status = 'draft';
            $post->save();
            Redirect::updateOrCreate(['from_path' => $post->url()], ['to_path' => $kept->url(), 'status_code' => Redirect::SAME_PAGE, 'is_active' => true]);
        }

        foreach (self::COPIED_DESCRIPTION['news'] as $slug => $description) {
            $this->describe(Post::ofType('news')->firstWhere('slug', $slug), $description);
        }
        foreach (self::COPIED_DESCRIPTION['events'] as $slug => $description) {
            $this->describe(Event::firstWhere('slug', $slug), $description);
        }
    }

    public function down(): void
    {
        foreach (self::DUPLICATES as $copy => $original) {
            $post = Post::ofType('blog')->firstWhere('slug', $copy);
            if (! $post) {
                continue;
            }
            Redirect::where('from_path', $post->url())->where('status_code', Redirect::SAME_PAGE)->delete();
            $post->status = 'published';
            $post->save();
        }
    }

    /** @param  array<string, string>  $fixes  regex => replacement */
    private function rewrite(string $table, array $likes, array $fixes): void
    {
        DB::table($table)
            ->where(fn ($q) => collect($likes)->each(fn ($like) => $q->orWhere('content', 'like', $like)))
            ->get(['id', 'content'])
            ->each(function (object $row) use ($table, $fixes): void {
                $content = preg_replace(array_keys($fixes), array_values($fixes), $row->content);
                if ($content !== null && $content !== $row->content) {
                    DB::table($table)->where('id', $row->id)->update(['content' => $content]);
                }
            });
    }

    private function describe(Post|Event|null $record, string $description): void
    {
        if ($record) {
            $record->seo = [...$record->seo ?? [], 'description' => $description];
            $record->save();
        }
    }
};
