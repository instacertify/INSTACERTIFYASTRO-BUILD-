<?php

namespace App\Filament\Resources\LegalEntities\Pages;

use App\Filament\Resources\LegalEntities\LegalEntityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLegalEntity extends CreateRecord
{
    protected static string $resource = LegalEntityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
