<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\PreviewsOnWebsite;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

class CreatePost extends CreateRecord
{
    use PreviewsOnWebsite;

    protected static string $resource = PostResource::class;

    #[Locked]
    public string $section = 'blog';

    public function mount(): void
    {
        $this->section = PostResource::currentSection();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [$this->previewAction()];
    }

    public function getBreadcrumbs(): array
    {
        return [PostResource::getUrl('index', ['section' => $this->section]) => Post::SECTIONS[$this->section], 'Create'];
    }
}
