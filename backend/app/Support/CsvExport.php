<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * @param  array<string, string>  $columns  attribute (dot notation for relations) => heading
     */
    public static function download(string $name, Builder $query, array $columns): StreamedResponse
    {
        Gate::authorize('viewAny', $query->getModel());
        AuditTrail::exported($name, (clone $query)->count());

        return response()->streamDownload(function () use ($query, $columns) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values($columns));

            $query->lazy(500)->each(function ($record) use ($out, $columns) {
                fputcsv($out, array_map(function (string $attribute) use ($record) {
                    $value = data_get($record, $attribute);

                    return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i') : (string) $value;
                }, array_keys($columns)));
            });

            fclose($out);
        }, $name.'-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
