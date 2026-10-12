<?php

namespace App\Filament\Resources\IndustryCategories\Pages;

use App\Filament\Resources\IndustryCategories\IndustryCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIndustryCategories extends ManageRecords
{
    protected static string $resource = IndustryCategoryResource::class;

    protected static ?string $title = 'Industry subcategories';

    protected ?string $subheading = 'Each resource section — blogs, news, audits, updates, events and standards — is split by these industries. Every section still lists only its own items.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
