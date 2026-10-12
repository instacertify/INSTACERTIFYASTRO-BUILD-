<?php

namespace App\Console\Commands;

use App\Support\Standards\LibraryImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('standards:import
    {--refresh-content : Regenerate titles, copy and FAQs of library pages (editorial pages are never rewritten)}
    {--dry-run : Report what would change without saving}')]
#[Description('Apply the BIS / CRS / BEE compliance library in database/data/standards to the standards pages')]
class ImportStandardsLibrary extends Command
{
    public function handle(): int
    {
        $importer = LibraryImporter::inTransaction(
            base_path('database/data/standards'),
            (bool) $this->option('refresh-content'),
            (bool) $this->option('dry-run'),
        );

        $this->table(['Result', 'Count'], collect($importer->stats)->map(fn ($count, $stat) => [$stat, $count])->values()->all());
        foreach ($importer->warnings as $warning) {
            $this->components->warn($warning);
        }
        if ($this->option('dry-run')) {
            $this->components->info('Dry run: nothing was saved.');
        } else {
            activity()->log('Imported the standards library: '.collect($importer->stats)->map(fn ($c, $s) => "{$c} {$s}")->implode(', '));
            $this->components->info('Standards library imported. Rebuild the website to publish the changes.');
        }

        return self::SUCCESS;
    }
}
