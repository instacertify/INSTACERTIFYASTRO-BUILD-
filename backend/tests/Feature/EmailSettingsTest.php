<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageEmail;
use App\Filament\Pages\ManageSite;
use App\Filament\Resources\Leads\Pages\ListLeads;
use App\Jobs\ProcessNewLead;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewLeadNotification;
use App\Notifications\TestEmail;
use App\Support\MailSettings;
use Database\Seeders\ContentSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ContentSeeder::class);
    }

    private function saveWorkspaceAccount(): void
    {
        Livewire::test(ManageEmail::class)
            ->set('data.leadRecipients', ['contact@instacertify.com', 'sales@instacertify.com'])
            ->set('data.careersRecipients', ['hr@instacertify.com'])
            ->set('data.enabled', true)
            ->set('data.username', 'contact@instacertify.com')
            ->set('data.password', 'abcd efgh ijkl mnop')
            ->set('data.fromAddress', 'contact@instacertify.com')
            ->set('data.fromName', 'Instacertify Website')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_only_admins_can_open_the_email_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'sales']));
        $this->get('/admin/manage-email')->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/manage-email')->assertOk()->assertSee('App password');
    }

    public function test_google_workspace_account_is_saved_encrypted_and_used_for_sending(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->saveWorkspaceAccount();

        $stored = Setting::get('mail');
        $this->assertNotSame('abcdefghijklmnop', $stored['password']);
        $this->assertSame('abcdefghijklmnop', MailSettings::password(), 'Spaces Google shows in app passwords are removed');

        $this->assertSame(MailSettings::MAILER, config('mail.default'));
        $this->assertSame('smtp.gmail.com', config('mail.mailers.workspace.host'));
        $this->assertSame(587, config('mail.mailers.workspace.port'));
        $this->assertSame('smtp', config('mail.mailers.workspace.scheme'));
        $this->assertSame('contact@instacertify.com', config('mail.from.address'));

        $log = Activity::where('log_name', 'settings')->latest('id')->first();
        $this->assertSame('Email & notifications', $log->properties['page']);
        $this->assertSame('(hidden)', $log->properties['attributes']['password']);

        // Saving again with the password field empty keeps the stored password.
        Livewire::test(ManageEmail::class)->set('data.fromName', 'Instacertify')->call('save')->assertHasNoErrors();
        $this->assertSame('abcdefghijklmnop', MailSettings::password());

        // Switching the account off hands sending back to the .env mailer.
        Livewire::test(ManageEmail::class)->set('data.enabled', false)->call('save')->assertHasNoErrors();
        $this->assertSame('array', config('mail.default'));
    }

    public function test_switching_the_account_on_needs_an_app_password(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageEmail::class)
            ->set('data.enabled', true)
            ->set('data.password', null)
            ->call('save')
            ->assertHasErrors(['data.password']);

        Livewire::test(ManageEmail::class)
            ->set('data.leadRecipients', ['not-an-email'])
            ->call('save')
            ->assertHasErrors();
    }

    public function test_new_leads_go_to_the_saved_recipients_with_reply_to_the_customer(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->saveWorkspaceAccount();

        $lead = Lead::create(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'phone' => '+919876543210', 'consent' => true]);
        (new ProcessNewLead($lead))->handle();

        Notification::assertSentOnDemand(NewLeadNotification::class, function (NewLeadNotification $notification, array $channels, AnonymousNotifiable $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === ['contact@instacertify.com', 'sales@instacertify.com']
                && $mail->replyTo === [['asha@example.com', 'Asha Verma']];
        });
    }

    public function test_sent_lead_email_is_shown_on_the_settings_page(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->saveWorkspaceAccount();

        (new ProcessNewLead(Lead::create(['name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => true])))->handle();

        $this->assertNull(MailSettings::lastDelivery()['error']);
        $this->get('/admin/manage-email')->assertOk()->assertSee('Last lead email sent');
    }

    public function test_a_mail_outage_does_not_break_the_enquiry_form_and_is_reported(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageEmail::class)
            ->set('data.leadRecipients', ['contact@instacertify.com'])
            ->set('data.enabled', true)
            ->set('data.username', 'contact@instacertify.com')
            ->set('data.password', 'abcdefghijklmnop')
            ->set('data.host', '127.0.0.1')
            ->set('data.port', 465)
            ->call('save')
            ->assertHasNoErrors();

        // phpunit.xml runs the queue synchronously, so the email is attempted inside the request.
        $this->postJson('/api/v1/leads', ['type' => 'quote', 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => true])
            ->assertCreated();

        $this->assertDatabaseHas('leads', ['email' => 'asha@example.com']);
        $this->assertNotNull(MailSettings::lastDelivery()['error']);
        $this->get('/admin/manage-email')->assertOk()->assertSee('The last lead email FAILED')->assertSee('could not reach the SMTP server');
    }

    public function test_the_team_is_warned_until_every_enquiry_is_emailed(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/admin')->assertSee('Lead emails are not being delivered');
        $this->actingAs(User::factory()->create(['role' => 'sales']))->get('/admin/leads')->assertSee('Lead emails are not being delivered');
        $this->actingAs(User::factory()->create(['role' => 'editor']))->get('/admin/posts')->assertDontSee('Lead emails');

        $this->actingAs($admin);
        $this->saveWorkspaceAccount();
        $this->postJson('/api/v1/leads', ['type' => 'quote', 'name' => 'Asha Verma', 'email' => 'asha@example.com', 'consent' => true])->assertCreated();
        $this->assertNotNull(Lead::sole()->emailed_at);
        $this->get('/admin')->assertDontSee('Lead emails need attention');

        // A worker outage or a failed send leaves the enquiry unsent.
        $missed = Lead::create(['name' => 'Ravi Kumar', 'phone' => '+919876543210', 'consent' => true, 'consented_at' => now()]);
        $this->travel(31)->minutes();
        $this->get('/admin')->assertSee('1 enquiry has not been emailed to the team');

        Livewire::test(ListLeads::class, ['activeTab' => 'all'])
            ->filterTable('emailed', false)
            ->assertCanSeeTableRecords([$missed])
            ->callAction(TestAction::make('email')->table($missed))
            ->assertNotified('Email queued');

        $this->assertNotNull($missed->fresh()->emailed_at);
        $this->get('/admin')->assertDontSee('Lead emails need attention');
    }

    public function test_the_test_inbox_is_not_mistaken_for_delivery(): void
    {
        app('mail.manager');
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mailpit']);

        $this->assertFalse(MailSettings::delivers());
        $this->assertStringContainsString('Mailpit', MailSettings::status());

        config(['mail.mailers.smtp.host' => 'smtp.example.com']);
        $this->assertTrue(MailSettings::delivers());
    }

    public function test_recipients_fall_back_to_the_env_address(): void
    {
        config(['instacertify.leads.notify_email' => 'contact@instacertify.com']);

        $this->assertSame(['contact@instacertify.com'], MailSettings::leadRecipients());
    }

    public function test_test_email_is_sent_to_the_lead_recipients(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->saveWorkspaceAccount();

        Livewire::test(ManageEmail::class)->call('sendTest')->assertNotified('Test email sent');

        Notification::assertSentOnDemand(TestEmail::class, fn ($n, $c, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['contact@instacertify.com', 'sales@instacertify.com']);
    }

    public function test_test_email_explains_a_failed_connection(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageEmail::class)
            ->set('data.enabled', true)
            ->set('data.username', 'contact@instacertify.com')
            ->set('data.password', 'abcdefghijklmnop')
            ->set('data.host', '127.0.0.1')
            ->set('data.port', 465)
            ->call('sendTest')
            ->assertHasNoErrors()
            ->assertNotified('The test email could not be sent');

        $this->assertSame('array', config('mail.default'), 'Unsaved test settings are not left in place');
    }

    public function test_tracking_ids_are_cleaned_up_and_published(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageSite::class)
            ->set('data.integrations.gtm', 'gtm-abc1234')
            ->call('save')
            ->assertHasNoErrors();

        Livewire::test(ManageSite::class)
            ->set('data.integrations.gtm', ' gtm-njq6bk77 ')
            ->set('data.integrations.clarity', 'X9UH7UCA0F')
            ->set('data.integrations.googleAds', 'AW-123456789')
            ->set('data.integrations.googleAdsLeadLabel', 'AW-123456789/AbC-D_efGhI')
            ->set('data.integrations.googleVerification', '<meta name="google-site-verification" content="abc123XYZ_-def" />')
            ->set('data.integrations.bingVerification', 'B1C2D3E4F5')
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.integrations.gtm', 'GTM-NJQ6BK77')
            ->assertJsonPath('data.integrations.clarity', 'x9uh7uca0f')
            ->assertJsonPath('data.integrations.googleAdsLeadLabel', 'AbC-D_efGhI')
            ->assertJsonPath('data.integrations.googleVerification', 'abc123XYZ_-def')
            ->assertJsonPath('data.integrations.bingVerification', 'B1C2D3E4F5');

        Livewire::test(ManageSite::class)
            ->set('data.integrations.gtm', 'UA-1234')
            ->set('data.integrations.metaPixel', 'abc')
            ->call('save')
            ->assertHasErrors(['data.integrations.gtm', 'data.integrations.metaPixel']);
    }

    public function test_pasted_code_snippets_keep_only_the_id(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageSite::class)
            ->set('data.integrations.gtm', "<!-- Google Tag Manager -->\n<script>(function(w,d,s,l,i){w[l]=w[l]||[];})(window,document,'script','dataLayer','GTM-NJQ6BK77');</script>")
            ->set('data.integrations.googleTag', '<script async src="https://www.googletagmanager.com/gtag/js?id=G-AB12CD34EF"></script>')
            ->set('data.integrations.clarity', '<script type="text/javascript">(function(c,l,a,r,i,t,y){})(window, document, "clarity", "script", "x9uh7uca0f");</script>')
            ->set('data.integrations.metaPixel', "<script>fbq('init', '123456789012345'); fbq('track', 'PageView');</script>")
            ->set('data.integrations.linkedinPartner', '<script type="text/javascript">_linkedin_partner_id = "7654321";</script>')
            ->set('data.integrations.microsoftAds', '<script>(function(w,d,t,r,u){var o={ti:"343210987", enableAutoSpaTracking: true};})(window,document,"script","//bat.bing.com/bat.js","uetq");</script>')
            ->set('data.integrations.ahrefsVerification', '<meta name="ahrefs-site-verification" content="0123abcd4567ef89">')
            ->set('data.integrations.verificationFiles', ['a' => ['name' => ' BingSiteAuth.xml ', 'content' => '<users><user>ABC</user></users>']])
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.integrations.gtm', 'GTM-NJQ6BK77')
            ->assertJsonPath('data.integrations.googleTag', 'G-AB12CD34EF')
            ->assertJsonPath('data.integrations.clarity', 'x9uh7uca0f')
            ->assertJsonPath('data.integrations.metaPixel', '123456789012345')
            ->assertJsonPath('data.integrations.linkedinPartner', '7654321')
            ->assertJsonPath('data.integrations.microsoftAds', '343210987')
            ->assertJsonPath('data.integrations.ahrefsVerification', '0123abcd4567ef89')
            ->assertJsonPath('data.integrations.verificationFiles.0.name', 'BingSiteAuth.xml');

        Livewire::test(ManageSite::class)
            ->set('data.integrations.verificationFiles', ['a' => ['name' => '../secret.php', 'content' => 'x'], 'b' => ['name' => 'robots.txt', 'content' => 'x']])
            ->call('save')
            ->assertHasErrors(['data.integrations.verificationFiles.a.name', 'data.integrations.verificationFiles.b.name']);
    }

    public function test_contact_conversions_and_indexnow_key_are_saved(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(ManageSite::class)
            ->set('data.integrations.googleAds', 'AW-123456789')
            ->set('data.integrations.googleAdsCallLabel', 'AW-123456789/CaLL_label-1')
            ->set('data.integrations.googleAdsWhatsappLabel', ' WhAts_App-2 ')
            ->set('data.integrations.linkedinPartner', '7654321')
            ->set('data.integrations.linkedinLeadConversion', "<script>window.lintrk('track', { conversion_id: 12345678 });</script>")
            ->set('data.integrations.indexNowKey', 'a1b2c3d4e5f60718293a4b5c6d7e8f90')
            ->call('save')
            ->assertHasNoErrors();

        $this->getJson('/api/v1/site')
            ->assertJsonPath('data.integrations.googleAdsCallLabel', 'CaLL_label-1')
            ->assertJsonPath('data.integrations.googleAdsWhatsappLabel', 'WhAts_App-2')
            ->assertJsonPath('data.integrations.linkedinLeadConversion', '12345678')
            ->assertJsonPath('data.integrations.indexNowKey', 'a1b2c3d4e5f60718293a4b5c6d7e8f90');

        Livewire::test(ManageSite::class)
            ->set('data.integrations.googleAdsCallLabel', 'bad label!')
            ->set('data.integrations.linkedinLeadConversion', 'abc')
            ->set('data.integrations.indexNowKey', '../key')
            ->call('save')
            ->assertHasErrors(['data.integrations.googleAdsCallLabel', 'data.integrations.linkedinLeadConversion', 'data.integrations.indexNowKey']);
    }
}
