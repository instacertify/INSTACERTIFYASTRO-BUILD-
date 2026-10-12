<?php

namespace App\Filament\Resources\Offices\Pages;

use App\Filament\Resources\Offices\OfficeResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffices extends ListRecords
{
    protected static string $resource = OfficeResource::class;

    protected ?string $subheading = 'Shown on the contact page in this order. Drag to reorder; hovering an office on the website shows its map.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('View contact page')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(rtrim(config('instacertify.frontend_url'), '/').'/contact', shouldOpenInNewTab: true),
            CreateAction::make()->label('Add office'),
        ];
    }
}
