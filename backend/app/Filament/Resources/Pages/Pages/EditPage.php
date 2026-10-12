<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return PageResource::aboutBlocksForForm($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PageResource::aboutBlocksForSave($data);
    }
}
