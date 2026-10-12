<?php

namespace App\Filament\Resources\LandingPages\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\LandingPages\LandingPageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingPage extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = LandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            Action::make('view')->label('View on site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => rtrim(config('instacertify.frontend_url'), '/').$this->record->url(), shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
