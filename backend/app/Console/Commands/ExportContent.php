<?php

namespace App\Console\Commands;

use App\Support\ContentTransfer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:export {file=storage/app/content-transfer.jsonl : Where to write the content (relative to the backend folder)}')]
#[Description('Write the website content (pages, posts, services, standards, menus, settings…) to a file for content:import on another server')]
class ExportContent extends Command
{
    public function handle(): int
    {
        $file = base_path($this->argument('file'));
        $counts = ContentTransfer::export($file);

        $this->table(['Table', 'Rows'], collect($counts)->map(fn (int $rows, string $table) => [$table, $rows])->values()->all());
        $this->components->info("Content written to {$file}. Uploaded images are in storage/app/public; copy that folder too.");

        return self::SUCCESS;
    }
}
