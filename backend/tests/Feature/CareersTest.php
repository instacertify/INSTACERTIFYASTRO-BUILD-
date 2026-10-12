<?php

namespace Tests\Feature;

use App\Filament\Resources\JobApplications\Pages\ListJobApplications;
use App\Models\JobApplication;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vacancy;
use App\Notifications\NewJobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CareersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(JobApplication::cvDisk());
        Notification::fake();
    }

    private function vacancy(array $attributes = []): Vacancy
    {
        static $n = 0;
        $n++;

        return Vacancy::create([
            'title' => "Certification Executive {$n}",
            'slug' => "certification-executive-{$n}",
            'department' => 'Certification',
            'summary' => 'Handle BIS and WPC projects end to end.',
            'description' => '<p>Details</p>',
            'status' => 'open',
            ...$attributes,
        ]);
    }

    private function application(Vacancy $vacancy, array $overrides = []): array
    {
        return [
            'vacancy' => $vacancy->slug,
            'name' => 'Riya Kapoor',
            'email' => 'Riya@Example.com',
            'phone' => '+91 98765 43210',
            'city' => 'Noida',
            'experience_years' => '3.5',
            'notice_period' => '30-days',
            'cv' => UploadedFile::fake()->create('Riya CV.pdf', 300, 'application/pdf'),
            'consent' => '1',
            ...$overrides,
        ];
    }

    public function test_only_open_vacancies_are_listed(): void
    {
        $open = $this->vacancy(['salary_min' => 400000, 'salary_max' => 600000, 'salary_visible' => true, 'experience_min' => 2, 'experience_max' => 4]);
        $this->vacancy(['status' => 'draft']);
        $this->vacancy(['status' => 'closed']);
        $this->vacancy(['closes_at' => today()->subDay()]);
        $this->vacancy(['published_at' => now()->addWeek()]);

        $this->getJson('/api/v1/vacancies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $open->slug)
            ->assertJsonPath('data.0.url', "/careers/{$open->slug}")
            ->assertJsonPath('data.0.experience', '2–4 years')
            ->assertJsonPath('data.0.salary.min', 400000)
            ->assertJsonMissingPath('data.0.hiringTeam');
    }

    public function test_candidate_can_apply_with_cv_and_team_is_notified(): void
    {
        $manager = User::factory()->create(['role' => 'editor']);
        $vacancy = $this->vacancy();
        $vacancy->hiringTeam()->attach($manager);

        $this->post('/api/v1/applications', $this->application($vacancy), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'message']]);

        $application = JobApplication::sole();
        $this->assertSame($vacancy->id, $application->vacancy_id);
        $this->assertSame('riya@example.com', $application->email);
        $this->assertSame('+919876543210', $application->phone);
        $this->assertSame('Riya CV.pdf', $application->cv_name);
        $this->assertTrue($application->consent);
        $this->assertNotNull($application->consent_version);
        $this->assertStringStartsWith('cvs/', $application->cv_path);
        Storage::disk(JobApplication::cvDisk())->assertExists($application->cv_path);

        Notification::assertSentOnDemand(NewJobApplication::class);
        Notification::assertSentTo($manager, NewJobApplication::class);
    }

    public function test_invalid_applications_are_rejected(): void
    {
        $vacancy = $this->vacancy();
        $closed = $this->vacancy(['status' => 'closed']);

        $this->postJson('/api/v1/applications', $this->application($closed))
            ->assertJsonValidationErrors('vacancy');

        $this->postJson('/api/v1/applications', $this->application($vacancy, [
            'cv' => UploadedFile::fake()->create('cv.exe', 10, 'application/x-msdownload'),
            'consent' => '0',
            'linkedin_url' => 'javascript:alert(1)',
        ]))->assertJsonValidationErrors(['cv', 'consent', 'linkedin_url']);

        $this->postJson('/api/v1/applications', $this->application($vacancy, [
            'cv' => UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'),
        ]))->assertJsonValidationErrors('cv');

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertEmpty(Storage::disk(JobApplication::cvDisk())->allFiles());
    }

    public function test_general_applications_can_be_switched_off(): void
    {
        $payload = $this->application($this->vacancy(), ['vacancy' => null]);

        $this->post('/api/v1/applications', $payload, ['Accept' => 'application/json'])->assertCreated();
        $this->assertNull(JobApplication::sole()->vacancy_id);

        Setting::updateOrCreate(['key' => 'site'], ['value' => ['careers' => ['generalApplications' => false]]]);
        $this->postJson('/api/v1/applications', $this->application($this->vacancy(), ['vacancy' => null]))
            ->assertJsonValidationErrors('vacancy');
    }

    public function test_access_follows_role_and_hiring_team(): void
    {
        $sales = $this->vacancy(['title' => 'Sales Manager']);
        $lab = $this->vacancy(['title' => 'Lab Coordinator']);
        $manager = User::factory()->create(['role' => 'sales']);
        $sales->hiringTeam()->attach($manager);

        $mine = JobApplication::create(['vacancy_id' => $sales->id, 'name' => 'Mine', 'email' => 'a@example.com', 'cv_path' => 'cvs/a.pdf']);
        $other = JobApplication::create(['vacancy_id' => $lab->id, 'name' => 'Other', 'email' => 'b@example.com', 'cv_path' => 'cvs/b.pdf']);
        $general = JobApplication::create(['name' => 'General', 'email' => 'c@example.com']);
        Storage::disk(JobApplication::cvDisk())->put('cvs/a.pdf', '%PDF-1.4 test');
        Storage::disk(JobApplication::cvDisk())->put('cvs/b.pdf', '%PDF-1.4 test');

        // HR sees everything and manages vacancies.
        $this->actingAs(User::factory()->create(['role' => 'hr']));
        $this->get('/admin/vacancies')->assertOk();
        $this->get('/admin/vacancies/create')->assertOk();
        $this->get("/admin/vacancies/{$sales->id}/edit")->assertOk();
        $this->get("/admin/job-applications/{$other->id}")->assertOk();
        $this->get("/admin/job-applications/{$other->id}/cv")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/admin/leads')->assertForbidden();
        Livewire::test(ListJobApplications::class)->assertCanSeeTableRecords([$mine, $other, $general]);

        // A hiring-team member sees only their vacancy's applicants and cannot manage vacancies.
        $this->actingAs($manager);
        $this->get('/admin/job-applications')->assertOk();
        $this->get("/admin/job-applications/{$mine->id}")->assertOk();
        $this->get("/admin/job-applications/{$mine->id}/cv")->assertOk();
        $this->get("/admin/job-applications/{$other->id}")->assertNotFound();
        $this->get("/admin/job-applications/{$other->id}/cv")->assertForbidden();
        $this->get('/admin/vacancies')->assertForbidden();
        Livewire::test(ListJobApplications::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other, $general]);

        // Everyone else sees nothing.
        $this->actingAs(User::factory()->create(['role' => 'editor']));
        $this->get('/admin/job-applications')->assertForbidden();
        $this->get("/admin/job-applications/{$mine->id}/cv")->assertForbidden();

        $this->assertDatabaseHas('activity_log', ['subject_id' => $mine->id, 'event' => 'cv_viewed', 'causer_id' => $manager->id]);
    }

    public function test_cv_is_deleted_with_the_application_and_by_retention(): void
    {
        $vacancy = $this->vacancy();
        $disk = Storage::disk(JobApplication::cvDisk());

        $deleted = JobApplication::create(['vacancy_id' => $vacancy->id, 'name' => 'A', 'cv_path' => 'cvs/a.pdf']);
        $disk->put('cvs/a.pdf', 'x');
        $deleted->delete();
        $disk->assertMissing('cvs/a.pdf');

        $old = JobApplication::create(['vacancy_id' => $vacancy->id, 'name' => 'Old', 'email' => 'old@example.com', 'phone' => '+911234567890', 'cv_path' => 'cvs/old.pdf']);
        $recent = JobApplication::create(['vacancy_id' => $vacancy->id, 'name' => 'Recent', 'cv_path' => 'cvs/recent.pdf']);
        $disk->put('cvs/old.pdf', 'x');
        $disk->put('cvs/recent.pdf', 'x');
        JobApplication::whereKey($old->id)->toBase()->update(['updated_at' => now()->subMonths(13)]);

        $this->artisan('privacy:prune')->assertSuccessful();

        $old->refresh();
        $this->assertSame('Anonymised', $old->name);
        $this->assertNull($old->email);
        $this->assertNull($old->cv_path);
        $this->assertNotNull($old->anonymised_at);
        $disk->assertMissing('cvs/old.pdf');
        $disk->assertExists('cvs/recent.pdf');
        $this->assertSame('Recent', $recent->fresh()->name);
    }
}
