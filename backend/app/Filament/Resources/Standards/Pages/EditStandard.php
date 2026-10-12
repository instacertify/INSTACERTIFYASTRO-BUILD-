<?php

namespace App\Filament\Resources\Standards\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Standards\StandardResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStandard extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = StandardResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction(), DeleteAction::make()];
    }
}
