<?php

use App\Models\LandingPage;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Pages whose "About the service" section was already filled in by an editor are left alone. */
    public function up(): void
    {
        foreach ($this->intros() as $path => $intro) {
            $page = LandingPage::where('path', $path)->first();
            if (! $page || filled($page->content['intro']['text'] ?? null)) {
                continue;
            }

            $page->update(['content' => [...($page->content ?? []), 'intro' => $intro]]);
        }
    }

    public function down(): void
    {
        foreach ($this->intros() as $path => $intro) {
            $page = LandingPage::where('path', $path)->first();
            if ($page && ($page->content['intro']['text'] ?? null) === $intro['text']) {
                $content = $page->content;
                unset($content['intro']);
                $page->update(['content' => $content]);
            }
        }
    }

    /** @return array<string, array{heading: string, text: string, points: list<string>}> */
    private function intros(): array
    {
        return require database_path('data/landing-page-intros.php');
    }
};
