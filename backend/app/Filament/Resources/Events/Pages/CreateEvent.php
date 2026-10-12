<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }
}
