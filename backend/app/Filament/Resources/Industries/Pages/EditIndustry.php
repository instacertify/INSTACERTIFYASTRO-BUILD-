<?php

namespace App\Filament\Resources\Industries\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Industries\IndustryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIndustry extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = IndustryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            DeleteAction::make(),
        ];
    }
}
