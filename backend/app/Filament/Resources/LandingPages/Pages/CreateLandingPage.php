<?php

namespace App\Filament\Resources\LandingPages\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\LandingPages\LandingPageResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLandingPage extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = LandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }
}
