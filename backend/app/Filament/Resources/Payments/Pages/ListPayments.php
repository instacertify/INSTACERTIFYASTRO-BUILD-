<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ListRecords;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected ?string $subheading = 'Payments made on the website\'s /pay page. Only paid ones are shown at first; change the status filter to see attempts that were not completed.';
}
