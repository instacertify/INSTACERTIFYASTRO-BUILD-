<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePage extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return PageResource::aboutBlocksForSave($data);
    }
}
