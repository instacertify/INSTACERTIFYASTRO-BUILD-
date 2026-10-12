<?php

namespace App\Filament\Resources\LandingPages\Pages;

use App\Filament\Resources\LandingPages\LandingPageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingPages extends ListRecords
{
    protected static string $resource = LandingPageResource::class;

    protected ?string $subheading = 'Focused pages for Google Ads: one offer, one form, no menu. Duplicate an existing page to start a new campaign.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
