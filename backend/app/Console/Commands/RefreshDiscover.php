<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('discover:refresh')]
#[Description('Re-measure featured images and re-run the Google Discover checklist for every article and event')]
class RefreshDiscover extends Command
{
    public function handle(): int
    {
        foreach ([Post::class => 'Articles', Event::class => 'Events'] as $model => $label) {
            $issues = [];
            $ready = 0;

            $model::query()->lazyById()->each(function (Post|Event $record) use (&$issues, &$ready) {
                $record->timestamps = false;
                $record->refreshDiscover(remeasure: true);
                $record->saveQuietly();
                $ready += (int) $record->discover_ready;
                foreach ($record->discover_issues ?? [] as $issue) {
                    $issues[$issue] = ($issues[$issue] ?? 0) + 1;
                }
            });

            $total = $model::count();
            $this->info("{$label}: {$ready} of {$total} meet all required Discover checks.");
            arsort($issues);
            $this->table(['Needs work', 'Count'], collect($issues)->map(fn ($count, $issue) => [$issue, $count])->values()->all());
        }

        return self::SUCCESS;
    }
}
