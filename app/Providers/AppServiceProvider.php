<?php

namespace App\Providers;

use App\Events\ScanCompleted;
use App\Events\ScanFailed;
use App\Listeners\GenerateAuditOnScanCompleted;
use App\Listeners\LogScanCompleted;
use App\Listeners\LogScanFailed;
use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Listeners\NotifyScanCompleted;
use App\Listeners\NotifyScanFailed;
use App\Models\Issue;
use App\Observers\IssueObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Logout::class, LogSuccessfulLogout::class);
        Event::listen(ScanCompleted::class, GenerateAuditOnScanCompleted::class);
        Event::listen(ScanCompleted::class, NotifyScanCompleted::class);
        Event::listen(ScanCompleted::class, LogScanCompleted::class);
        Event::listen(ScanFailed::class, NotifyScanFailed::class);
        Event::listen(ScanFailed::class, LogScanFailed::class);
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
