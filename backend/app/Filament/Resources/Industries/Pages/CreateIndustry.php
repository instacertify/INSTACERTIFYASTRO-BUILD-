<?php

namespace App\Filament\Resources\Industries\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Industries\IndustryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIndustry extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = IndustryResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }
}
