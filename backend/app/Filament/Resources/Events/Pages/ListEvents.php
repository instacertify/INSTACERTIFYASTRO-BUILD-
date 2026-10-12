<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\HasIndustryTabs;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEvents extends ListRecords
{
    use HasIndustryTabs;

    protected static string $resource = EventResource::class;

    protected ?string $heading = 'Trade fairs & events';

    protected function industryTabsQuery(): Builder
    {
        return Event::query();
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
