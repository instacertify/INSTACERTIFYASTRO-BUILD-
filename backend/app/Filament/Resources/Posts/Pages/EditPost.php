<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->previewAction(),
            Action::make('view')->label('View on site')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => rtrim(config('instacertify.frontend_url'), '/').$this->record->url(), shouldOpenInNewTab: true),
            DeleteAction::make()->successRedirectUrl(fn () => PostResource::getUrl('index', ['section' => $this->record->type])),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            PostResource::getUrl('index', ['section' => $this->record->type]) => Post::SECTIONS[$this->record->type] ?? 'Articles',
            'Edit',
        ];
    }
}
