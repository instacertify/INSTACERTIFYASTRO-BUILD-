<?php

use App\Models;
use App\Support\TrackingParams;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Facebook "share" links bounce through a tracking redirect before reaching the page. */
    private const FACEBOOK = ['https://www.facebook.com/share/1R82zWjbvB/' => 'https://www.facebook.com/people/Instacertify-Labs-Private-Limited/61564980525561/'];

    public function up(): void
    {
        $models = [Models\Post::class, Models\Event::class, Models\Page::class, Models\Standard::class, Models\Service::class,
            Models\Industry::class, Models\LandingPage::class, Models\Faq::class, Models\Setting::class];

        foreach ($models as $class) {
            $class::query()->each(function ($record): void {
                foreach (array_keys($record->getAttributes()) as $key) {
                    $value = $record->getAttribute($key);
                    if ((is_string($value) || is_array($value)) && ($clean = TrackingParams::clean($value)) !== $value) {
                        $record->setAttribute($key, $clean);
                    }
                }
                if ($record instanceof Models\Setting && $record->key === 'site' && is_array($record->value['social'] ?? null)) {
                    $social = array_map(
                        fn ($url) => is_string($url) ? (self::FACEBOOK[$url] ?? TrackingParams::profileUrl($url)) : $url,
                        $record->value['social'],
                    );
                    if ($social !== $record->value['social']) {
                        $record->value = [...$record->value, 'social' => $social];
                    }
                }
                if ($record->isDirty()) {
                    $record->timestamps = false;
                    $record->save();
                }
            });
        }
    }

    public function down(): void {}
};
