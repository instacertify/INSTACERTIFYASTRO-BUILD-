<?php

namespace App\Filament\Resources\Vacancies\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Filament\Resources\Vacancies\VacancyResource;
use App\Models\Vacancy;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVacancy extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = VacancyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            Action::make('applicants')
                ->label(fn (Vacancy $record) => 'Applicants ('.$record->applications()->count().')')
                ->icon('heroicon-o-users')->color('gray')
                ->url(fn (Vacancy $record) => JobApplicationResource::getUrl('index', ['filters' => ['vacancy_id' => ['value' => $record->id]]])),
            Action::make('view')->label('View on website')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn (Vacancy $record) => $record->isOpen())
                ->url(fn (Vacancy $record) => rtrim(config('instacertify.frontend_url'), '/').$record->url(), shouldOpenInNewTab: true),
            DeleteAction::make()
                ->modalDescription('Applications for this vacancy are kept and become "general applications". Delete them separately if they are no longer needed.'),
        ];
    }
}
