<?php

namespace App\Filament\Resources\Vacancies\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Vacancies\VacancyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVacancy extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = VacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }
}
