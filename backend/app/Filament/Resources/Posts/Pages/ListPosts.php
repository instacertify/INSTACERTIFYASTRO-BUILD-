<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Concerns\HasIndustryTabs;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/** One resource section (blogs, news, audits or updates) per page; its industry subcategories are the tabs. */
class ListPosts extends ListRecords
{
    use HasIndustryTabs;

    protected static string $resource = PostResource::class;

    #[Url]
    public string $section = 'blog';

    public function mount(): void
    {
        // Older links used ?tab=news for the section.
        if (isset(Post::TYPES[$this->activeTab ?? ''])) {
            $this->section = $this->activeTab;
            $this->activeTab = null;
        }
        if (! isset(Post::TYPES[$this->section])) {
            $this->section = 'blog';
        }

        parent::mount();
    }

    public function getTitle(): string|Htmlable
    {
        return Post::SECTIONS[$this->section];
    }

    public function getBreadcrumbs(): array
    {
        return [PostResource::getUrl('index', ['section' => $this->section]) => Post::SECTIONS[$this->section], 'List'];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('type', $this->section);
    }

    protected function industryTabsQuery(): Builder
    {
        return Post::ofType($this->section);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('categories')->label('Categories')->icon('heroicon-o-tag')->color('gray')
                ->url(fn () => CategoryResource::getUrl('index', ['section' => $this->section])),
            CreateAction::make()->label('New article')
                ->url(fn () => PostResource::getUrl('create', ['section' => $this->section])),
        ];
    }
}
