<?php

namespace App\Filament\Concerns;

use App\Support\Preview;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Js;

/** "Preview" button for editors: shows the form as the website would render it, without saving anything. */
trait PreviewsOnWebsite
{
    protected function previewAction(): Action
    {
        return Action::make('preview')->label('Preview')->icon('heroicon-o-eye')->color('gray')
            ->tooltip('See how this looks on the website, including unsaved changes. Nothing is saved or published.')
            ->action(function (): void {
                $url = $this->previewUrl();
                $this->js('window.open('.Js::from($url).', "_blank")');
                Notification::make()->title('Preview opened in a new tab')
                    ->body('Unsaved changes are included. The link works for '.(Preview::MINUTES / 60).' hours; click Preview again after more edits.')
                    ->actions([Action::make('open')->label('Open preview')->url($url, shouldOpenInNewTab: true)])
                    ->send();
            });
    }

    protected function previewUrl(): string
    {
        // Hooks stay off so relationship fields (e.g. a vacancy's hiring team) and uploads are not saved.
        $data = $this->form->getState(shouldCallHooksBefore: false);

        if ($this instanceof EditRecord) {
            $record = clone $this->getRecord();
            $data = $this->mutateFormDataBeforeSave($data);
        } elseif ($this instanceof CreateRecord) {
            $record = new ($this->getModel());
            $data = $this->mutateFormDataBeforeCreate($data);
        }

        $record->fill(Preview::withoutUploads($data, $record->attributesToArray()));

        return Preview::forRecord($record);
    }
}
