<?php

namespace App\Providers;

use App\Events\ScanCompleted;
use App\Events\ScanFailed;
use App\Listeners\GenerateAuditOnScanCompleted;
use App\Listeners\LogFailedLogin;
use App\Listeners\LogScanCompleted;
use App\Listeners\LogScanFailed;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Listeners\NotifyScanCompleted;
use App\Listeners\NotifyScanFailed;
use App\Models\Agency;
use App\Models\Issue;
use App\Notifications\JobFailedNotification;
use App\Observers\IssueObserver;
use App\Services\RoutedEmailNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Issue::observe(IssueObserver::class);

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(ScanCompleted::class, GenerateAuditOnScanCompleted::class);
        Event::listen(ScanCompleted::class, NotifyScanCompleted::class);
        Event::listen(ScanCompleted::class, LogScanCompleted::class);
        Event::listen(ScanFailed::class, NotifyScanFailed::class);
        Event::listen(ScanFailed::class, LogScanFailed::class);

        $this->registerFailedJobAlert();
    }

    protected function registerFailedJobAlert(): void
    {
        Queue::failing(function (\Illuminate\Queue\Events\JobFailed $event): void {
            $payload = $event->job->payload();
            $agencyId = $payload['data']['agency_id'] ?? null;

            if (! $agencyId) {
                // Try to decode the serialized command to find an agency_id property.
                try {
                    $command = unserialize($payload['data']['command'] ?? '');
                    if (is_object($command)) {
                        $agencyId = $command->agency_id
                            ?? $command->scan?->agency_id
                            ?? $command->issue?->agency_id
                            ?? $command->audit?->agency_id
                            ?? $command->contentAudit?->agency_id
                            ?? $command->report?->agency_id
                            ?? $command->riskAdvisory?->agency_id
                            ?? $command->issueCluster?->agency_id
                            ?? null;
                    }
                } catch (\Throwable) {
                    // Unserialize failure — skip agency scoping.
                }
            }

            $notification = new JobFailedNotification($event);
            $notifier = app(RoutedEmailNotifier::class);

            if ($agencyId) {
                $notifier->notify($agencyId, 'scan_failures', $notification);
            } else {
                // Broadcast to all agencies that subscribe to failure alerts.
                Agency::query()->each(function (Agency $agency) use ($notifier, $notification): void {
                    $notifier->notify($agency, 'scan_failures', $notification);
                });
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
