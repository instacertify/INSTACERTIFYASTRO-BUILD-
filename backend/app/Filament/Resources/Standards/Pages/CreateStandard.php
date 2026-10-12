<?php

namespace App\Filament\Resources\Standards\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Standards\StandardResource;
use Filament\Resources\Pages\CreateRecord;

class CreateStandard extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = StandardResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }
}
