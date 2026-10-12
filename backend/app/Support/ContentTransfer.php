<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies the website's content between installations (e.g. a local admin to the production server) as JSON lines.
 * Accounts, enquiries, CVs, subscribers, logs and the encrypted email settings never leave the source.
 */
class ContentTransfer
{
    public const FORMAT = 'instacertify-content';

    public const TABLES = [
        'authors', 'categories', 'events', 'event_scheme', 'faqs', 'industries', 'industry_categories', 'landing_pages',
        'menu_items', 'offices', 'pages', 'posts', 'post_scheme', 'redirects', 'schemes', 'services', 'settings',
        'standard_categories', 'standards', 'testimonials', 'vacancies',
    ];

    /** Encrypted with the source's APP_KEY, so unreadable anywhere else; set it again under Settings → Email. */
    private const KEPT_SETTINGS = ['mail'];

    private const BATCH = 200;

    /** @return array<string, int> rows written per table */
    public static function export(string $file): array
    {
        $out = fopen($file, 'w') ?: throw new RuntimeException("Cannot write {$file}");
        $counts = [];

        try {
            self::write($out, ['format' => self::FORMAT, 'version' => 1, 'exportedAt' => now()->toIso8601String(), 'storageUrl' => self::storageUrl()]);

            foreach (self::tables() as $table) {
                $counts[$table] = 0;
                $query = DB::table($table);
                if ($table === 'settings') {
                    $query->whereNotIn('key', self::KEPT_SETTINGS);
                }

                foreach ($query->cursor() as $row) {
                    self::write($out, ['t' => $table, 'r' => (array) $row]);
                    $counts[$table]++;
                }
            }
        } finally {
            fclose($out);
        }

        return $counts;
    }

    /** Replaces this installation's content with the file's. @return array<string, int> rows imported per table */
    public static function import(string $file): array
    {
        $in = fopen($file, 'r') ?: throw new RuntimeException("Cannot read {$file}");

        try {
            $meta = json_decode((string) fgets($in), true);
            if (($meta['format'] ?? null) !== self::FORMAT || ($meta['version'] ?? null) !== 1) {
                throw new RuntimeException('This is not a content file written by content:export.');
            }

            $urls = self::urlRewrites((string) ($meta['storageUrl'] ?? ''), self::storageUrl());
            $tables = self::tables();
            $columns = collect($tables)->mapWithKeys(fn (string $t) => [
                $t => collect(Schema::getColumns($t))->mapWithKeys(fn (array $c) => [$c['name'] => $c['type_name']])->all(),
            ])->all();

            $counts = self::inTransactionWithoutForeignKeys(function () use ($in, $tables, $columns, $urls) {
                foreach ($tables as $table) {
                    $query = DB::table($table);
                    if ($table === 'settings') {
                        $query->whereNotIn('key', self::KEPT_SETTINGS);
                    }
                    $query->delete();
                }

                $counts = array_fill_keys($tables, 0);
                $batch = [];
                $flush = function () use (&$batch, &$counts) {
                    foreach ($batch as $table => $rows) {
                        DB::table($table)->insert($rows);
                        $counts[$table] += count($rows);
                    }
                    $batch = [];
                };

                while (($line = fgets($in)) !== false) {
                    if (trim($line) === '') {
                        continue;
                    }
                    ['t' => $table, 'r' => $row] = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
                    if (! isset($columns[$table]) || ($table === 'settings' && in_array($row['key'], self::KEPT_SETTINGS, true))) {
                        continue;
                    }

                    $batch[$table][] = self::prepare($row, $columns[$table], $urls);
                    if (count($batch[$table]) >= self::BATCH) {
                        $flush();
                    }
                }
                $flush();

                self::resetSequences($tables, $columns);

                return $counts;
            });
        } finally {
            fclose($in);
        }

        Cache::flush();

        return $counts;
    }

    /** @param  resource  $out */
    private static function write($out, array $line): void
    {
        fwrite($out, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    }

    /** @return list<string> */
    private static function tables(): array
    {
        return array_values(array_filter(self::TABLES, fn (string $t) => Schema::hasTable($t)));
    }

    private static function storageUrl(): string
    {
        return Storage::disk(config('instacertify.media_disk'))->url('');
    }

    /** Image links inside content point at the source's address; JSON columns hold them with escaped slashes. */
    private static function urlRewrites(string $from, string $to): array
    {
        if ($from === '' || $from === $to) {
            return [];
        }

        $escape = fn (string $url) => str_replace('/', '\/', $url);

        return [$from => $to, $escape($from) => $escape($to)];
    }

    /** @param  array<string, string>  $types  column => database type of the destination table */
    private static function prepare(array $row, array $types, array $urls): array
    {
        $row = array_intersect_key($row, $types);

        foreach ($row as $column => $value) {
            if (is_string($value) && $urls) {
                $row[$column] = strtr($value, $urls);
            } elseif ($value !== null && in_array($types[$column], ['bool', 'boolean'], true)) {
                $row[$column] = (bool) $value;
            }
        }

        return $row;
    }

    /** Rows arrive in table order, not dependency order. */
    private static function inTransactionWithoutForeignKeys(callable $callback): mixed
    {
        $postgres = DB::getDriverName() === 'pgsql';
        $postgres ? DB::statement("SET session_replication_role = 'replica'") : Schema::disableForeignKeyConstraints();

        try {
            return DB::transaction(function () use ($postgres, $callback) {
                if (! $postgres) {
                    // SQLite ignores "foreign_keys = OFF" inside an open transaction (as in tests).
                    DB::statement('PRAGMA defer_foreign_keys = ON');
                }

                return $callback();
            });
        } finally {
            $postgres ? DB::statement("SET session_replication_role = 'origin'") : Schema::enableForeignKeyConstraints();
        }
    }

    private static function resetSequences(array $tables, array $columns): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($tables as $table) {
            if (isset($columns[$table]['id'])) {
                DB::statement(
                    "SELECT setval(pg_get_serial_sequence(?, 'id'), COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1, false)",
                    [$table],
                );
            }
        }
    }
}
