<?php

namespace App\Filament\Resources\Standards\Pages;

use App\Filament\Concerns\HasIndustryTabs;
use App\Filament\Resources\Standards\StandardResource;
use App\Models\Standard;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStandards extends ListRecords
{
    use HasIndustryTabs;

    protected static string $resource = StandardResource::class;

    protected function industryTabsQuery(): Builder
    {
        return Standard::query();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
