<?php

namespace App\Filament\Resources\Subscribers\Pages;

use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Support\CsvExport;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;

class ManageSubscribers extends ManageRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
                ->action(fn () => CsvExport::download(
                    'subscribers',
                    $this->getFilteredTableQuery(),
                    ['email' => 'Email', 'status' => 'Status', 'source_page' => 'Source page', 'created_at' => 'Signed up', 'confirmed_at' => 'Confirmed'],
                )),
        ];
    }
}
