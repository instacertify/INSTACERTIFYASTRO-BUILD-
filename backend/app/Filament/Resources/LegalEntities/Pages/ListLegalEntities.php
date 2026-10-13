<?php

namespace App\Filament\Resources\LegalEntities\Pages;

use App\Filament\Resources\LegalEntities\LegalEntityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLegalEntities extends ListRecords
{
    protected static string $resource = LegalEntityResource::class;

    protected ?string $subheading = 'The companies that trade under the Instacertify brand, one per market. They share the logo; the footer lists only the ones switched on.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add company')];
    }
}
