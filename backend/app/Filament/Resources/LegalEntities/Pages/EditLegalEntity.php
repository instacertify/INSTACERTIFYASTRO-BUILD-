<?php

namespace App\Filament\Resources\LegalEntities\Pages;

use App\Filament\Resources\LegalEntities\LegalEntityResource;
use App\Models\LegalEntity;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLegalEntity extends EditRecord
{
    protected static string $resource = LegalEntityResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->hidden(fn (LegalEntity $record) => $record->is_primary)];
    }
}
