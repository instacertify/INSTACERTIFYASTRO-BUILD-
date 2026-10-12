<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction(), DeleteAction::make()];
    }
}
