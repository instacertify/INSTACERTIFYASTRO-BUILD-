<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->canManageLeads() ?? false;
    }

    protected function getStats(): array
    {
        $today = Lead::whereDate('created_at', today())->count();
        $week = Lead::where('created_at', '>=', now()->subDays(7))->count();
        $new = Lead::where('status', 'new')->count();
        $won = Lead::where('status', 'won')->where('updated_at', '>=', now()->subDays(30))->count();
        $total30 = Lead::where('created_at', '>=', now()->subDays(30))->count();

        return [
            Stat::make('Leads today', $today),
            Stat::make('Last 7 days', $week),
            Stat::make('Awaiting first contact', $new)->color($new > 0 ? 'warning' : 'success'),
            Stat::make('Won (30 days)', $won)
                ->description($total30 ? round($won / $total30 * 100).'% conversion' : 'No leads yet'),
        ];
    }
}
