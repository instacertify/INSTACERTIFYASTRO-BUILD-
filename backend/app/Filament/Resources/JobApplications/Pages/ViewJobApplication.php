<?php

namespace App\Filament\Resources\JobApplications\Pages;

use App\Filament\Resources\JobApplications\JobApplicationResource;
use App\Models\JobApplication;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJobApplication extends ViewRecord
{
    protected static string $resource = JobApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            JobApplicationResource::cvAction()->color('primary'),
            JobApplicationResource::updateAction(),
            Action::make('whatsapp')->label('WhatsApp')->icon('heroicon-o-chat-bubble-left-right')->color('success')
                ->visible(fn (JobApplication $record) => filled($record->phone))
                ->url(fn (JobApplication $record) => 'https://wa.me/'.ltrim($record->phone, '+'), shouldOpenInNewTab: true),
            DeleteAction::make()->modalDescription('This permanently deletes the application and the CV file.'),
        ];
    }
}
