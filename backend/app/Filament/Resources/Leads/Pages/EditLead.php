<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /** Hidden attributes are dropped by attributesToArray(); without this the form shows them empty and saving wipes the notes. */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...$this->getRecord()->only(['notes', 'ip', 'user_agent'])];
    }
}
