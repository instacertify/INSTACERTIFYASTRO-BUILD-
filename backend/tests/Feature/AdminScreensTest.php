<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Event;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\Office;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Post;
use App\Models\Standard;
use App\Models\StandardCategory;
use App\Models\User;
use App\Models\Vacancy;
use Database\Seeders\ContentSeeder;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/** Every admin screen, including ones added after this test was written, opens for an admin. */
class AdminScreensTest extends TestCase
{
    use RefreshDatabase;

    private const SKIP = ['admin/login', 'admin/password-reset/request', 'admin/password-reset/reset', 'admin/job-applications/{application}/cv'];

    public function test_every_admin_screen_opens(): void
    {
        $this->seed(ContentSeeder::class);
        $this->createOneOfEach();
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $urls = [];
        $missing = [];
        foreach (Router::getRoutes()->getRoutes() as $route) {
            /** @var Route $route */
            if (! in_array('GET', $route->methods()) || ! str_starts_with($route->uri(), 'admin') || in_array($route->uri(), self::SKIP)) {
                continue;
            }
            $params = $route->parameterNames();
            if ($params === []) {
                $urls[] = '/'.$route->uri();

                continue;
            }
            $page = $route->getControllerClass();
            if ($params === ['record'] && $page && (is_subclass_of($page, EditRecord::class) || is_subclass_of($page, ViewRecord::class))) {
                $record = $page::getResource()::getModel()::query()->first();
                $record ? $urls[] = '/'.str_replace('{record}', (string) $record->getRouteKey(), $route->uri()) : $missing[] = $route->uri();

                continue;
            }
            $missing[] = $route->uri();
        }

        $this->assertSame([], $missing, 'Admin screens without a record to open');
        $this->assertGreaterThan(50, count($urls));

        $failed = [];
        foreach ($urls as $url) {
            $status = $this->get($url)->status();
            if ($status !== 200) {
                $failed[] = "{$url} → {$status}";
            }
        }
        $this->assertSame([], $failed);
    }

    private function createOneOfEach(): void
    {
        Lead::create(['name' => 'Test Lead', 'email' => 'lead@example.com', 'consent' => true]);
        $author = Author::create(['name' => 'Priya Sharma', 'slug' => 'priya-sharma']);
        Post::create(['type' => 'news', 'title' => 'BIS update', 'slug' => 'bis-update', 'content' => '<p>Hello</p>', 'status' => 'published', 'author_id' => $author->id]);
        Event::create(['title' => 'Electronica', 'slug' => 'electronica', 'starts_at' => now()->addMonth(), 'status' => 'published']);
        $category = StandardCategory::create(['title' => 'Mandatory CRS Standards', 'slug' => 'mandatory-crs-standards']);
        Standard::create(['standard_category_id' => $category->id, 'title' => 'CRS for monitors', 'slug' => 'crs-monitors', 'path' => 'x']);
        Page::firstOrCreate(['path' => 'privacy-policy'], ['title' => 'Privacy policy', 'template' => 'policy', 'content' => '<p>Policy</p>']);
        Office::create(['name' => 'Noida head office', 'type' => 'head', 'street' => 'A-34, Sector 63A', 'city' => 'Noida', 'country' => 'India', 'is_published' => true]);
        $vacancy = Vacancy::create(['title' => 'Certification Executive', 'slug' => 'certification-executive', 'department' => 'Certification', 'summary' => 'BIS and WPC projects.', 'description' => '<p>Details</p>', 'status' => 'open']);
        JobApplication::create(['vacancy_id' => $vacancy->id, 'name' => 'Riya Kapoor', 'email' => 'riya@example.com', 'cv_path' => 'cvs/riya.pdf']);
        Payment::create(['gateway' => 'razorpay', 'amount' => 150050, 'currency' => 'INR', 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'status' => 'paid', 'paid_at' => now()]);
    }
}
