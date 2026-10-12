<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Imported copy named other consultancies ("Aleph India"); the site only speaks for Instacertify. */
return new class extends Migration
{
    private const REPLACEMENTS = [
        'Contact Aleph India today' => 'Contact Instacertify today',
        ', or specialized agencies like Aleph India)' => ')',
        'or specialized agencies like Aleph India' => '',
        'Aleph India' => 'Instacertify',
    ];

    public function up(): void
    {
        foreach (['posts' => ['content', 'excerpt', 'title'], 'services' => ['detail', 'summary', 'content'], 'pages' => ['content'], 'events' => ['content', 'excerpt']] as $table => $columns) {
            $columns = array_filter($columns, fn ($column) => Schema::hasColumn($table, $column));
            foreach ($columns as $column) {
                DB::table($table)->where($column, 'like', '%Aleph%')->get(['id', $column])->each(function ($row) use ($table, $column) {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => str_replace(array_keys(self::REPLACEMENTS), array_values(self::REPLACEMENTS), $row->$column),
                    ]);
                });
            }
        }
    }

    public function down(): void
    {
        // Content clean-up; nothing to restore.
    }
};
