<?php

namespace App\Filament\Concerns;

use App\Models\IndustryCategory;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * "All · Steel (12) · Chemicals (8) · … · Other industries · No industry" tabs for one section's list page.
 * The busiest industries get their own tab; the industry filter reaches the rest one by one.
 */
trait HasIndustryTabs
{
    protected int $industryTabLimit = 8;

    abstract protected function industryTabsQuery(): Builder;

    public function getTabs(): array
    {
        $counts = $this->industryTabsQuery()->toBase()
            ->selectRaw('industry_category_id, count(*) as aggregate')
            ->groupBy('industry_category_id')
            ->pluck('aggregate', 'industry_category_id')
            ->map(fn ($count) => (int) $count);

        $tabs = ['all' => Tab::make('All')->badge($counts->sum())];

        $ranked = $counts->except([''])->sortDesc();
        $shown = $ranked->take($this->industryTabLimit);
        $names = IndustryCategory::whereKey($shown->keys()->all())->pluck('name', 'id');
        foreach ($shown as $id => $count) {
            $tabs["industry-{$id}"] = Tab::make($names[$id] ?? 'Industry')->badge($count)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('industry_category_id', $id));
        }

        if ($ranked->count() > $shown->count()) {
            $tabs['other'] = Tab::make('Other industries')->badge($ranked->skip($this->industryTabLimit)->sum())
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('industry_category_id')
                    ->whereNotIn('industry_category_id', $shown->keys()->all()));
        }

        if ($none = $counts[''] ?? 0) {
            $tabs['none'] = Tab::make('No industry')->badge($none)->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('industry_category_id'));
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
