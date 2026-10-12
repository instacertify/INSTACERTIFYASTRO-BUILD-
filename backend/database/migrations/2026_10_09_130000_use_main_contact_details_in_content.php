<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Imported posts gave a personal email and a placeholder WhatsApp number; point them at the main contact details. */
return new class extends Migration
{
    private const REPLACEMENTS = [
        'anita@instacertify.in' => 'contact@instacertify.com',
        '+91-98765-43210' => '+91 99991 18039',
        '+91-9876543210' => '+91 99991 18039',
    ];

    public function up(): void
    {
        foreach (['posts' => ['content', 'excerpt', 'title'], 'services' => ['detail', 'summary', 'content'], 'pages' => ['content'], 'events' => ['content', 'excerpt']] as $table => $columns) {
            $columns = array_filter($columns, fn ($column) => Schema::hasColumn($table, $column));
            foreach ($columns as $column) {
                DB::table($table)
                    ->where(fn ($q) => $q->where($column, 'like', '%anita@instacertify.in%')->orWhere($column, 'like', '%98765%'))
                    ->get(['id', $column])
                    ->each(function ($row) use ($table, $column) {
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
