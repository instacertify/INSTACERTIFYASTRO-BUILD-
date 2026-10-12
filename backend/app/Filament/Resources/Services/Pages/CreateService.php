<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ServiceForm::tableRowsForWebsite($data);
    }
}
