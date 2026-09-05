<?php

namespace App\Providers;

use App\Models\User;
use App\Services\TelemetryService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class, fn () => new TenantContext);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('platform.admin', fn (User $user): bool => (bool) $user->is_platform_admin);

        Event::listen(function (QueueBusy $event): void {
            app(TelemetryService::class)->record('queue.busy', [
                'connection' => $event->connectionName,
                'queue' => $event->queue,
                'size' => $event->size,
                'threshold' => config('observability.queue_depth_threshold', 100),
            ], 'warning');
        });

        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('qr-public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('public-orders', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('payment-webhooks', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
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
            : null,
        );
    }
}
