<?php

namespace App\Console\Commands;

use App\Models\ConsentLog;
use App\Models\JobApplication;
use App\Models\Lead;
use App\Models\Subscriber;
use App\Support\Privacy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Activitylog\Models\Activity;

#[Signature('privacy:prune {--dry-run : Report what would change without changing anything}')]
#[Description('Apply the retention periods from Site settings → Privacy & cookies (DPDP Act s. 8(7), GDPR Art. 5(1)(e))')]
class PrunePersonalData extends Command
{
    public function handle(): int
    {
        $settings = Privacy::settings();
        $dry = (bool) $this->option('dry-run');

        $consents = ConsentLog::where('created_at', '<', now()->subMonths((int) $settings['consentLogMonths']));
        $ips = Lead::whereNotNull('ip')->where('created_at', '<', now()->subDays((int) $settings['ipDays']));
        $closed = Lead::whereNull('anonymised_at')->whereNotIn('status', Lead::OPEN_STATUSES)
            ->where('updated_at', '<', now()->subMonths((int) $settings['leadMonths']));
        $unconfirmed = Subscriber::whereNull('confirmed_at')->whereNull('unsubscribed_at')->where('created_at', '<', now()->subDays(30));
        $applications = JobApplication::whereNull('anonymised_at')
            ->where('updated_at', '<', now()->subMonths((int) $settings['applicationMonths']));

        $this->table(['Data', 'Rule', 'Records'], [
            ['Cookie consent records', "older than {$settings['consentLogMonths']} months → deleted", $consents->count()],
            ['Lead IP address & browser', "older than {$settings['ipDays']} days → removed", $ips->count()],
            ['Closed leads (won/lost)', "untouched for {$settings['leadMonths']} months → anonymised", $closed->count()],
            ['Unconfirmed newsletter sign-ups', 'older than 30 days → deleted', $unconfirmed->count()],
            ['Job applications & CVs', "untouched for {$settings['applicationMonths']} months → CV deleted, anonymised", $applications->count()],
        ]);

        if ($dry) {
            return self::SUCCESS;
        }

        $consents->delete();
        // Bypass Eloquent timestamps: bumping updated_at would postpone the anonymisation rule below.
        $ips->toBase()->update(['ip' => null, 'user_agent' => null]);
        $unconfirmed->delete();

        $closed->chunkById(200, function ($leads) {
            Activity::where('subject_type', (new Lead)->getMorphClass())->whereIn('subject_id', $leads->modelKeys())->delete();
            Lead::whereKey($leads->modelKeys())->update([
                'name' => 'Anonymised',
                'email' => null,
                'phone' => null,
                'company' => null,
                'message' => null,
                'notes' => null,
                'referrer' => null,
                'ip' => null,
                'user_agent' => null,
                'anonymised_at' => now(),
            ]);
        });

        $applications->chunkById(100, function ($batch) {
            $batch->each->deleteCv();
            JobApplication::whereKey($batch->modelKeys())->toBase()->update([
                'name' => 'Anonymised',
                'email' => null,
                'phone' => null,
                'city' => null,
                'linkedin_url' => null,
                'current_ctc' => null,
                'expected_ctc' => null,
                'cover_letter' => null,
                'notes' => null,
                'cv_path' => null,
                'cv_name' => null,
                'ip' => null,
                'user_agent' => null,
                'anonymised_at' => now(),
            ]);
        });

        $this->info('Retention rules applied.');

        return self::SUCCESS;
    }
}
