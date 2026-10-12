<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\ChartWidget;

class LeadSources extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Where leads come from (90 days)';

    protected ?string $maxHeight = '260px';

    public static function canView(): bool
    {
        return auth()->user()?->canManageLeads() ?? false;
    }

    protected function getData(): array
    {
        $counts = Lead::where('created_at', '>=', now()->subDays(90))
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), 'direct / organic') as source, COUNT(*) as total")
            ->groupBy('source')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'source');

        return [
            'datasets' => [[
                'label' => 'Leads',
                'data' => $counts->values()->all(),
                'backgroundColor' => '#225175',
            ]],
            'labels' => $counts->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
