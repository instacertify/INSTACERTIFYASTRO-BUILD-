<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Services\Schemas\ServiceForm;
use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = ServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ServiceForm::tableRowsForEditor($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ServiceForm::tableRowsForWebsite($data);
    }
}
