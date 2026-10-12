<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageHomePage;
use App\Filament\Resources\LandingPages\Pages\EditLandingPage;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Vacancies\Pages\EditVacancy;
use App\Models\Author;
use App\Models\LandingPage;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Models\Vacancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class PreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['instacertify.preview_url' => 'https://preview.test']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    /** The draft the Preview click handed to the website. */
    private function previewed(Testable $component): array
    {
        $component->assertHasNoFormErrors();
        $js = collect($component->effects['xjs'] ?? [])->pluck('expression')->last();
        $this->assertNotNull($js, 'Preview did not open a tab.');
        preg_match('#https://preview\.test/preview/([A-Za-z0-9]+)#', stripslashes($js), $match);
        $this->assertNotEmpty($match, $js);

        return $this->getJson('/api/v1/preview/'.$match[1])
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->json('data');
    }

    public function test_landing_page_preview_shows_unsaved_edits_without_saving_them(): void
    {
        $page = LandingPage::create([
            'title' => 'BIS', 'path' => 'lp/bis', 'status' => 'draft',
            'content' => ['hero' => ['headline' => 'Saved headline']],
        ]);

        $preview = $this->previewed(Livewire::test(EditLandingPage::class, ['record' => $page->getKey()])
            ->fillForm(['content.hero.headline' => 'Unsaved headline'])
            ->callAction('preview'));

        $this->assertSame('ad', $preview['kind']);
        $this->assertSame('lp/bis', $preview['data']['path']);
        $this->assertSame('Unsaved headline', $preview['data']['content']['hero']['headline']);
        $this->assertSame('Saved headline', $page->fresh()->content['hero']['headline']);
    }

    public function test_a_new_page_can_be_previewed_before_it_is_created(): void
    {
        $preview = $this->previewed(Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Quality policy', 'path' => 'quality-policy', 'template' => 'default', 'status' => 'draft', 'content' => '<p>Our policy</p>'])
            ->callAction('preview'));

        $this->assertSame('page', $preview['kind']);
        $this->assertSame('<p>Our policy</p>', $preview['data']['content']);
        $this->assertArrayHasKey('seo', $preview['data']);
        $this->assertFalse(Page::where('path', 'quality-policy')->exists());
    }

    public function test_post_preview_includes_its_author_and_full_content(): void
    {
        $author = Author::create(['name' => 'Asha Verma', 'slug' => 'asha-verma', 'status' => 'published']);
        $post = Post::create([
            'type' => 'blog', 'title' => 'Draft', 'slug' => 'draft', 'status' => 'draft',
            'author_id' => $author->id, 'content' => '<p>Saved</p>',
        ]);

        $preview = $this->previewed(Livewire::test(EditPost::class, ['record' => $post->getKey()])
            ->fillForm(['content' => '<p>Edited</p>'])
            ->callAction('preview'));

        $this->assertSame('post', $preview['kind']);
        $this->assertSame('<p>Edited</p>', $preview['data']['content']);
        $this->assertSame('Asha Verma', $preview['data']['author']['name']);
        $this->assertSame('<p>Saved</p>', $post->fresh()->content);
    }

    public function test_vacancy_preview_does_not_save_the_hiring_team(): void
    {
        $vacancy = Vacancy::create([
            'title' => 'Certification Executive', 'slug' => 'certification-executive', 'department' => 'Certification',
            'summary' => 'Handle BIS and WPC projects end to end.', 'description' => '<p>Details</p>', 'status' => 'open',
        ]);
        $recruiter = User::factory()->create();

        $preview = $this->previewed(Livewire::test(EditVacancy::class, ['record' => $vacancy->getKey()])
            ->fillForm(['title' => 'Unsaved title', 'hiringTeam' => [$recruiter->id]])
            ->callAction('preview'));

        $this->assertSame('vacancy', $preview['kind']);
        $this->assertSame('Unsaved title', $preview['data']['title']);
        $this->assertCount(0, $vacancy->hiringTeam()->get());
        $this->assertSame('Certification Executive', $vacancy->fresh()->title);
    }

    public function test_home_page_preview_uses_the_edited_hero(): void
    {
        $preview = $this->previewed(Livewire::test(ManageHomePage::class)
            ->fillForm(['hero.title' => 'Preview headline'])
            ->callAction('preview'));

        $this->assertSame('home', $preview['kind']);
        $this->assertSame('Preview headline', $preview['data']['home']['hero']['title']);
        $this->assertIsArray($preview['data']['process']);
    }

    public function test_unknown_or_expired_links_are_not_found(): void
    {
        $this->getJson('/api/v1/preview/'.str_repeat('a', 40))->assertNotFound();
    }
}
