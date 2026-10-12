<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * instacertify.in is a parked domain without a working certificate, so these links are dead.
     */
    private const MAP = [
        '' => '/',
        'contact' => '/contact',
        'services/epr-battery-waste' => '/services/indian-registration-compliances/environment-compliance/epr-for-battery-waste',
        'news/cpcbs-latest-directive-epr-compliance-required-for-plastic-producers' => '/news-item/cpcbs-latest-directive-epr-compliance-required-for-plastic-producers',
        'nutritional-label-testing' => '/resources/blogs/nutritional-label-testing',
        'fssai-registration' => '/services/indian-registration-compliances/business-licenses/fssai-basic-registration',
        'bis-crs-registration' => '/certification/india/bis-crs-mark-certification',
        'fcc-certification' => '/certification/americas/fcc-certification',
        'stqc-certification' => '/certification/india/stqc-certification',
        'how-to-get-peso-certificate' => '/resources/blogs/how-to-get-peso-certificate',
        'peso-cng-certification' => '/certification/india/peso-certification',
        'services/rohs-testing' => '/services/testing/rohs-testing',
        'nsic-msme' => '/services/indian-registration-compliances/business-licenses/msme-udyam-registration',
        'nsic-registration' => '/services/indian-registration-compliances/business-licenses/msme-udyam-registration',
    ];

    /**
     * Organisers moved these event pages; the old addresses return 404.
     */
    private const EVENT_URLS = [
        'https://www.chemtech-online.com/chemtech-2026/' => 'https://chemtech-online.com/chemtech-world-expo-2026/',
        'https://www.productronicachina.com.cn/en/productronica-china-2026' => 'https://www.productronicachina.com.cn/en/',
    ];

    public function up(): void
    {
        DB::table('posts')->where('content', 'like', '%://instacertify.in%')->get(['id', 'content'])
            ->each(function (object $post): void {
                $content = preg_replace_callback(
                    '#<a\b([^>]*?)\bhref="https?://(?:www\.)?instacertify\.in/?([^"?\#]*?)/?(?:\?[^"]*)?"([^>]*)>#i',
                    function (array $m): string {
                        $target = self::MAP[strtolower($m[2])] ?? null;
                        if ($target === null) {
                            return $m[0];
                        }
                        $attrs = preg_replace('#\s+(?:target|rel)="[^"]*"#i', '', $m[1].$m[3]);

                        return '<a href="'.$target.'"'.rtrim($attrs).'>';
                    },
                    $post->content,
                );

                if ($content !== $post->content) {
                    DB::table('posts')->where('id', $post->id)->update(['content' => $content]);
                }
            });

        foreach (self::EVENT_URLS as $old => $new) {
            DB::table('events')->where('url', $old)->update(['url' => $new]);
        }
    }

    public function down(): void {}
};
