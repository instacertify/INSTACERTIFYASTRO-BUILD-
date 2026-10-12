<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/** The categories of one resource section only. */
class ManageCategories extends ManageRecords
{
    protected static string $resource = CategoryResource::class;

    #[Url]
    public string $section = 'blog';

    public function mount(): void
    {
        if (! isset(Post::TYPES[$this->section])) {
            $this->section = 'blog';
        }

        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return Post::SECTIONS[$this->section].' categories';
    }

    public function getBreadcrumbs(): array
    {
        return [PostResource::getUrl('index', ['section' => $this->section]) => Post::SECTIONS[$this->section], 'Categories'];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('type', $this->section);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('articles')->label('Back to '.strtolower(Post::SECTIONS[$this->section]))->color('gray')
                ->url(fn () => PostResource::getUrl('index', ['section' => $this->section])),
            CreateAction::make()->label('New category'),
        ];
    }
}
