<?php

namespace App\Filament\Resources\Schemes\Pages;

use App\Filament\Resources\Schemes\SchemeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSchemes extends ManageRecords
{
    protected static string $resource = SchemeResource::class;

    protected static ?string $title = 'Certification tags';

    protected ?string $subheading = 'Tag audits and events with the certifications they cover. Visitors filter the Audits and Events pages by these, and each landing page shows only audits with its tags (Landing pages → Photos of our work).';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
