<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Event;
use App\Models\IndustryCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Scheme;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\Subscriber;
use App\Notifications\ConfirmSubscription;
use App\Support\SchemeClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EditorialApiTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $attributes = []): Post
    {
        return Post::create([
            'type' => 'blog',
            'title' => 'BIS CRS guide',
            'slug' => 'bis-crs-guide',
            'content' => '<p>'.str_repeat('word ', 450).'</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'faqs' => [['q' => 'Is it mandatory?', 'a' => 'Yes.']],
            ...$attributes,
        ]);
    }

    public function test_posts_are_listed_by_type_and_hide_drafts_and_scheduled(): void
    {
        $author = Author::create(['name' => 'Priya Sharma', 'slug' => 'priya-sharma']);
        $category = Category::create(['type' => 'blog', 'name' => 'Indian compliance', 'slug' => 'indian-compliance']);
        $this->makePost(['author_id' => $author->id, 'category_id' => $category->id]);
        $this->makePost(['slug' => 'draft', 'status' => 'draft']);
        $this->makePost(['slug' => 'scheduled', 'published_at' => now()->addWeek()]);
        $this->makePost(['type' => 'news', 'slug' => 'news-item']);

        $this->getJson('/api/v1/posts?type=blog')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', '/resources/blogs/bis-crs-guide')
            ->assertJsonPath('data.0.author.name', 'Priya Sharma')
            ->assertJsonPath('data.0.category.slug', 'indian-compliance')
            ->assertJsonPath('data.0.readingTime', 3)
            ->assertJsonMissingPath('data.0.content')
            ->assertJsonPath('meta.total', 1);

        $this->getJson('/api/v1/posts?type=nonsense')->assertUnprocessable();
    }

    public function test_post_detail_includes_content_faqs_and_seo(): void
    {
        $this->makePost(['type' => 'news', 'slug' => 'screen-protectors', 'seo' => ['title' => 'Custom title']]);

        $this->getJson('/api/v1/posts/news/screen-protectors')
            ->assertOk()
            ->assertJsonPath('data.url', '/news-item/screen-protectors')
            ->assertJsonPath('data.faqs.0.q', 'Is it mandatory?')
            ->assertJsonPath('data.seo.title', 'Custom title');

        $this->getJson('/api/v1/posts/blog/screen-protectors')->assertNotFound();
        $this->getJson('/api/v1/posts/unknown/screen-protectors')->assertNotFound();
    }

    public function test_posts_and_events_carry_their_industry_for_list_filters(): void
    {
        $toys = IndustryCategory::firstOrCreate(['slug' => 'toys'], ['name' => 'Toys']);
        $this->makePost(['type' => 'audit', 'slug' => 'toy-audit', 'industry_category_id' => $toys->id]);
        $this->makePost(['type' => 'audit', 'slug' => 'unsorted-audit', 'title' => 'Factory visit', 'industry_category_id' => null, 'published_at' => now()->subWeek()]);
        Event::create(['title' => 'Toy fair', 'slug' => 'toy-fair', 'industry_category_id' => $toys->id, 'starts_at' => now()->addMonth(), 'status' => 'published', 'published_at' => now()->subDay()]);

        $this->getJson('/api/v1/posts?type=audit')
            ->assertOk()
            ->assertJsonPath('data.0.industry', ['name' => 'Toys', 'slug' => 'toys'])
            ->assertJsonPath('data.1.industry', null);
        $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.industry.slug', 'toys');
    }

    public function test_audits_and_events_carry_certification_tags_and_work_type(): void
    {
        $isi = Scheme::where('slug', 'bis-isi')->firstOrFail();
        $msds = Scheme::where('slug', 'msds')->firstOrFail();
        $this->makePost(['type' => 'audit', 'slug' => 'cable-audit', 'work_type' => 'audit'])->schemes()->sync([$isi->id]);
        $this->makePost(['type' => 'audit', 'slug' => 'sds-story', 'work_type' => 'case_study', 'published_at' => now()->subWeek()])->schemes()->sync([$msds->id]);
        Event::create(['title' => 'Cable fair', 'slug' => 'cable-fair', 'starts_at' => now()->addMonth(), 'status' => 'published', 'published_at' => now()->subDay()])
            ->schemes()->sync([$isi->id]);

        $this->getJson('/api/v1/posts?type=audit')
            ->assertOk()
            ->assertJsonPath('data.0.schemes', [['name' => 'BIS ISI mark', 'slug' => 'bis-isi']])
            ->assertJsonPath('data.0.workTypeLabel', 'Factory audit')
            ->assertJsonPath('data.1.workType', 'case_study');
        $this->getJson('/api/v1/posts?type=audit&scheme=msds')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'sds-story');
        $this->getJson('/api/v1/events')->assertOk()->assertJsonPath('data.0.schemes.0.slug', 'bis-isi');
        $this->getJson('/api/v1/events/cable-fair')->assertOk()->assertJsonPath('data.schemes.0.slug', 'bis-isi');
    }

    public function test_certification_tags_and_work_type_are_suggested_from_the_title(): void
    {
        $this->assertSame(['bis-crs'], SchemeClassifier::classify('A BIS CRS Compliance Strategy for an Induction Stove', 'Covers IS 302 and IS 16046.'));
        $this->assertSame(['bis-isi'], SchemeClassifier::classify('Instacertify Completes Major IS 694:2010 Cable Audit in Noida'));
        $this->assertSame(['bis-isi'], SchemeClassifier::classify('Milestone achieved in Malaysia', 'The BIS audit of White Portland Cement under IS 8042.'));
        $this->assertSame(['msds'], SchemeClassifier::classify('How Instacertify Helped an Agarbatti Exporter Prepare a Globally Usable SDS'));
        $this->assertSame(['epr'], SchemeClassifier::classify('Bharat Pack Expo 2025', null, 'Paper & Packaging'));
        $this->assertSame([], SchemeClassifier::classify('Formnext 2025', null, 'Machinery, Tools & Instruments'));

        $this->assertSame('case_study', SchemeClassifier::workType('How Instacertify Helped an BIS ISI Mark Certification for Tile Adhesive'));
        $this->assertSame('inspection', SchemeClassifier::workType('Pre-shipment inspection of LED lamps in Shenzhen'));
        $this->assertSame('audit', SchemeClassifier::workType('Milestone achieved in Malaysia', 'The BIS audit of White Portland Cement.'));
        $this->assertNull(SchemeClassifier::workType('Trade fair round-up'));
    }

    public function test_events_can_be_filtered_to_upcoming(): void
    {
        foreach (['past' => now()->subMonth(), 'soon' => now()->addMonth()] as $slug => $date) {
            Event::create(['title' => ucfirst($slug), 'slug' => $slug, 'starts_at' => $date, 'status' => 'published', 'published_at' => now()->subYear()]);
        }

        $this->getJson('/api/v1/events?when=upcoming')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.slug', 'soon');
        $this->getJson('/api/v1/events')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/events/past')->assertOk()->assertJsonPath('data.url', '/resources/events/past');
    }

    public function test_standards_are_grouped_and_resolved_by_path(): void
    {
        $category = StandardCategory::create(['title' => 'Mandatory CRS standards', 'slug' => 'mandatory-crs-standards']);
        Standard::create([
            'standard_category_id' => $category->id,
            'title' => 'IS 13252',
            'slug' => 'is-13252',
            'products' => [['is_number' => 'IS 13252 (Part 1)', 'name' => 'Laptops']],
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/standards')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'mandatory-crs-standards')
            ->assertJsonPath('data.0.standards.0.title', 'IS 13252');

        $this->getJson('/api/v1/standards/resources/standards/mandatory-crs-standards/is-13252')
            ->assertOk()
            ->assertJsonPath('data.products.0.name', 'Laptops');
    }

    public function test_pages_authors_faqs_and_redirects(): void
    {
        Page::create(['path' => 'privacy-policy', 'template' => 'policy', 'title' => 'Privacy policy', 'content' => '<p>Hi</p>', 'status' => 'published', 'published_at' => now()->subDay()]);
        $author = Author::create(['name' => 'Pankaj Kumar', 'slug' => 'pankaj-kumar']);
        $this->makePost(['author_id' => $author->id]);

        Redirect::create(['from_path' => 'https://instacertify.com/old-page/', 'to_path' => '/new-page']);

        $this->getJson('/api/v1/pages/privacy-policy')->assertOk()->assertJsonPath('data.template', 'policy');
        $this->getJson('/api/v1/authors/pankaj-kumar')->assertOk()->assertJsonCount(1, 'data.posts');
        $redirects = collect($this->getJson('/api/v1/redirects')->assertOk()->json('data'))->keyBy('from');
        $this->assertSame(['from' => '/old-page', 'to' => '/new-page', 'status' => 301], $redirects['/old-page']);
    }

    public function test_newsletter_uses_double_opt_in(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/subscribers', ['email' => 'Buyer@Example.com'])->assertCreated();
        $subscriber = Subscriber::sole();
        $this->assertSame('buyer@example.com', $subscriber->email);
        $this->assertSame('pending', $subscriber->status);
        Notification::assertSentOnDemand(ConfirmSubscription::class);

        $this->get("/api/v1/subscribers/{$subscriber->id}/confirm")->assertForbidden();

        $this->get(URL::temporarySignedRoute('api.subscribers.confirm', now()->addDay(), $subscriber))->assertRedirect();
        $this->assertSame('active', $subscriber->fresh()->status);

        $this->postJson('/api/v1/subscribers', ['email' => 'buyer@example.com'])->assertCreated();
        Notification::assertSentOnDemandTimes(ConfirmSubscription::class, 1);

        $this->get(URL::signedRoute('api.subscribers.unsubscribe', $subscriber))->assertRedirect();
        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
    }

    public function test_newsletter_rejects_bots_and_bad_emails(): void
    {
        $this->postJson('/api/v1/subscribers', ['email' => 'not-an-email'])->assertUnprocessable();
        $this->postJson('/api/v1/subscribers', ['email' => 'a@b.com', 'website' => 'spam'])->assertUnprocessable();
        $this->assertDatabaseCount('subscribers', 0);
    }
}
