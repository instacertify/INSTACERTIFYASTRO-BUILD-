<?php

namespace Tests\Feature;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Filament\Resources\IndustryCategories\Pages\ManageIndustryCategories;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Category;
use App\Models\Event;
use App\Models\IndustryCategory;
use App\Models\Post;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use App\Support\IndustryClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResourceSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function article(string $type, string $title, array $extra = []): Post
    {
        return Post::create(['type' => $type, 'title' => $title, 'slug' => str($title)->slug(), 'content' => '<p>Text</p>', 'status' => 'published', 'published_at' => now()->subDay(), ...$extra]);
    }

    public function test_titles_are_sorted_into_industries(): void
    {
        $this->assertSame('Toys', IndustryClassifier::classify('BIS certification for toys: QCO explained', null));
        $this->assertSame('Steel Products', IndustryClassifier::classify('New QCO for stainless steel pipes', null));
        $this->assertSame('Cables & Wires', IndustryClassifier::classify('CRS registration for USB cables', null));
        $this->assertNull(IndustryClassifier::classify('How to apply for a BIS licence', null));
    }

    public function test_new_articles_and_events_get_an_industry_from_their_title(): void
    {
        $toys = IndustryCategory::where('name', 'Toys')->value('id');
        $steel = IndustryCategory::where('name', 'Steel Products')->value('id');

        $this->assertSame($toys, $this->article('news', 'QCO for toys extended')->industry_category_id);
        $this->assertNull($this->article('blog', 'How to apply for a BIS licence')->industry_category_id);
        $this->assertSame($steel, $this->article('blog', 'Toys made of steel', ['industry_category_id' => $steel])->industry_category_id);

        $event = Event::create(['title' => 'Toy Fair Mumbai', 'slug' => 'toy-fair', 'starts_at' => now()->addMonth()]);
        $this->assertSame($toys, $event->industry_category_id);
    }

    public function test_each_section_lists_only_its_own_articles(): void
    {
        $blog = $this->article('blog', 'Toy safety checklist');
        $news = $this->article('news', 'QCO for toys extended');
        $audit = $this->article('audit', 'Factory audit for steel mills');

        Livewire::test(ListPosts::class, ['section' => 'news'])
            ->assertCanSeeTableRecords([$news])
            ->assertCanNotSeeTableRecords([$blog, $audit])
            ->assertSee('Regulatory news');

        Livewire::test(ListPosts::class)
            ->assertSet('section', 'blog')
            ->assertCanSeeTableRecords([$blog])
            ->assertCanNotSeeTableRecords([$news, $audit]);
    }

    public function test_old_tab_links_open_the_right_section(): void
    {
        $audit = $this->article('audit', 'Factory audit for steel mills');

        Livewire::test(ListPosts::class, ['activeTab' => 'audit'])
            ->assertSet('section', 'audit')
            ->assertSet('activeTab', 'all')
            ->assertCanSeeTableRecords([$audit]);
    }

    public function test_industry_tabs_count_only_the_current_section(): void
    {
        $toys = IndustryCategory::where('name', 'Toys')->value('id');
        $toyNews = $this->article('news', 'QCO for toys extended');
        $this->article('news', 'QCO for toys deferred');
        $plainNews = $this->article('news', 'BIS portal maintenance');
        $toyBlog = $this->article('blog', 'Toy safety checklist');

        $page = Livewire::test(ListPosts::class, ['section' => 'news']);
        $tabs = $page->instance()->getTabs();
        $this->assertSame('3', $tabs['all']->getBadge());
        $this->assertSame('2', $tabs["industry-{$toys}"]->getBadge());
        $this->assertSame('1', $tabs['none']->getBadge());

        $page->set('activeTab', "industry-{$toys}")
            ->assertCanSeeTableRecords([$toyNews])
            ->assertCanNotSeeTableRecords([$plainNews, $toyBlog]);
    }

    public function test_less_common_industries_share_an_other_tab(): void
    {
        $titles = ['toys', 'steel pipes', 'USB cables', 'LED lamps', 'cement', 'textiles', 'helmets', 'plywood', 'footwear', 'paints'];
        foreach ($titles as $i => $title) {
            foreach (range(0, 9 - $i) as $n) {
                $this->article('news', "QCO for {$title} update {$n}");
            }
        }

        $tabs = Livewire::test(ListPosts::class, ['section' => 'news'])->instance()->getTabs();

        $this->assertCount(8, array_filter(array_keys($tabs), fn ($key) => str_starts_with($key, 'industry-')));
        $shown = array_sum(array_map(fn ($tab) => (int) $tab->getBadge(), array_filter($tabs, fn ($key) => str_starts_with($key, 'industry-'), ARRAY_FILTER_USE_KEY)));
        $this->assertSame((string) (Post::ofType('news')->whereNotNull('industry_category_id')->count() - $shown), $tabs['other']->getBadge());
        $this->assertGreaterThan(0, (int) $tabs['other']->getBadge());
    }

    public function test_categories_are_managed_per_section(): void
    {
        $blogTopic = Category::create(['type' => 'blog', 'name' => 'Guides', 'slug' => 'guides']);
        $newsTopic = Category::create(['type' => 'news', 'name' => 'QCO notifications', 'slug' => 'qco']);

        Livewire::test(ManageCategories::class, ['section' => 'news'])
            ->assertCanSeeTableRecords([$newsTopic])
            ->assertCanNotSeeTableRecords([$blogTopic])
            ->assertSee('Regulatory news categories')
            ->callAction('create', ['name' => 'Deadlines', 'slug' => 'deadlines'])
            ->assertHasNoActionErrors();

        $this->assertSame('news', Category::where('slug', 'deadlines')->value('type'));
    }

    public function test_new_article_starts_in_the_section_it_was_created_from(): void
    {
        $this->get(PostResource::getUrl('create', ['section' => 'update']))
            ->assertOk()
            ->assertSee('Regulatory updates');
    }

    public function test_sidebar_shows_each_section_with_its_own_categories(): void
    {
        $this->get(PostResource::getUrl('index', ['section' => 'audit']))
            ->assertOk()
            ->assertSeeInOrder(['Resources', 'Blogs', 'Regulatory news', 'Audits &amp; inspections', 'Categories', 'Regulatory updates', 'Trade fairs &amp; events', 'Industry subcategories'], false);
    }

    public function test_events_have_industry_tabs(): void
    {
        $fair = Event::create(['title' => 'Toy Fair Mumbai', 'slug' => 'toy-fair', 'starts_at' => now()->addMonth()]);
        $webinar = Event::create(['title' => 'BIS webinar', 'slug' => 'bis-webinar', 'starts_at' => now()->addMonth()]);

        Livewire::test(ListEvents::class)
            ->set('activeTab', 'industry-'.$fair->industry_category_id)
            ->assertCanSeeTableRecords([$fair])
            ->assertCanNotSeeTableRecords([$webinar]);
    }

    public function test_standard_sector_and_industry_stay_in_step(): void
    {
        $category = StandardCategory::create(['title' => 'BIS ISI', 'slug' => 'bis-isi']);
        $standard = Standard::create(['standard_category_id' => $category->id, 'title' => 'IS 17283 Hand tools', 'slug' => 'hand-tools', 'sector' => 'Hand Tools']);

        $created = IndustryCategory::where('name', 'Hand Tools')->first();
        $this->assertSame($created->id, $standard->industry_category_id);

        $steel = IndustryCategory::where('name', 'Steel Products')->first();
        $standard->update(['industry_category_id' => $steel->id]);
        $this->assertSame('Steel Products', $standard->fresh()->sector);

        $steel->update(['name' => 'Steel & Iron Products']);
        $this->assertSame('Steel & Iron Products', $standard->fresh()->sector);
    }

    public function test_subcategory_page_counts_items_per_section(): void
    {
        $this->article('news', 'QCO for toys extended');
        $this->article('blog', 'Toy safety checklist');
        $this->article('blog', 'Choosing a toy testing lab');

        Livewire::test(ManageIndustryCategories::class)
            ->assertCanSeeTableRecords([IndustryCategory::where('name', 'Toys')->first()])
            ->assertTableColumnStateSet('posts_blog_count', 2, IndustryCategory::where('name', 'Toys')->first())
            ->assertTableColumnStateSet('posts_news_count', 1, IndustryCategory::where('name', 'Toys')->first());
    }
}
