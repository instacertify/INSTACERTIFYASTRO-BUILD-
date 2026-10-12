<?php

namespace App\Filament\Resources\Offices\Pages;

use App\Filament\Resources\Offices\OfficeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOffice extends CreateRecord
{
    protected static string $resource = OfficeResource::class;

    protected static bool $canCreateAnother = true;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
