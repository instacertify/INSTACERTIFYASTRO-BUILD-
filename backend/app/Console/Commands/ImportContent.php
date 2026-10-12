<?php

namespace App\Console\Commands;

use App\Support\ContentTransfer;
use App\Support\SitePublisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:import
    {file=storage/app/content-transfer.jsonl : File written by content:export (relative to the backend folder)}
    {--force : Do not ask for confirmation}')]
#[Description('Replace the website content with a content:export file; admin accounts, enquiries and email settings are kept')]
class ImportContent extends Command
{
    public function handle(): int
    {
        $file = base_path($this->argument('file'));
        if (! is_file($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This replaces all pages, posts, services, standards, menus and site settings. Continue?')) {
            return self::FAILURE;
        }

        $counts = ContentTransfer::import($file);

        $this->table(['Table', 'Rows'], collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        SitePublisher::queue();
        $this->components->info(SitePublisher::configured()
            ? 'Content imported. The website is republished in about a minute.'
            : 'Content imported. Rebuild the website to publish it.');

        return self::SUCCESS;
    }
}
