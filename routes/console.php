<?php

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Jobs\ReconcilePaymentJob;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BackupService;
use App\Services\PaymentLifecycleService;
use App\Services\PaymentReconciliationService;
use App\Services\TelemetryService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:reconcile {--limit=100}', function (PaymentReconciliationService $reconciliation): int {
    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
    $limit = $limit === false ? 100 : max(1, min(500, $limit));
    $payments = Payment::withoutGlobalScopes()
        ->where('provider', 'midtrans')
        ->where('status', PaymentStatus::Pending)
        ->whereNotNull('provider_reference')
        ->where('created_at', '>=', now()->subDay())
        ->orderBy('id')
        ->limit($limit)
        ->get();

    if (config('queue.default') !== 'sync') {
        $payments->each(fn (Payment $payment) => ReconcilePaymentJob::dispatch((int) $payment->getKey()));
        $this->info("Reconciliation diantrekan: {$payments->count()} payment.");

        return 0;
    }

    $processed = 0;
    $failures = 0;

    foreach ($payments as $payment) {
        try {
            $result = $reconciliation->reconcile($payment);

            if (($result['processed'] ?? false) || ($result['duplicate'] ?? false)) {
                $processed++;
            }

            $this->line('Payment #'.$payment->getKey().': '.($result['payment_status'] ?? 'unchanged'));
        } catch (Throwable $exception) {
            $failures++;
            report($exception);
            $this->error('Payment #'.$payment->getKey().': reconciliation gagal.');
        }
    }

    $this->info("Reconciliation selesai: {$payments->count()} diperiksa, {$processed} diproses, {$failures} gagal.");

    return $failures === 0 ? 0 : 1;
})->purpose('Reconcile pending Midtrans payments');

Artisan::command('payments:expire {--limit=100}', function (PaymentLifecycleService $lifecycle): int {
    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
    $limit = $limit === false ? 100 : max(1, min(500, $limit));
    $expired = $lifecycle->expireDue($limit);

    $this->info("Payment kedaluwarsa: {$expired}.");

    return 0;
})->purpose('Expire overdue pending payments');

Artisan::command('ops:backup {--destination= : Override the configured backup destination}', function (
    BackupService $backups,
    TelemetryService $telemetry,
): int {
    $destination = $this->option('destination');
    $destination = is_string($destination) && trim($destination) !== '' ? $destination : null;

    try {
        $result = $backups->create($destination);
        $telemetry->record('backup.completed', [
            'backup_id' => $result['backup_id'],
            'pruned' => $result['pruned'],
        ]);

        $this->info("Backup {$result['backup_id']} selesai.");
        $this->line('Lokasi: '.$result['directory']);

        return 0;
    } catch (Throwable $exception) {
        report($exception);
        $telemetry->record('backup.failed', [
            'reason' => $exception::class,
        ], 'error');
        $this->error('Backup gagal. Periksa konfigurasi dan log operasional.');

        return 1;
    }
})->purpose('Create a verified SQLite database and public asset backup');

Artisan::command('ops:backup:verify {backup : Backup directory to verify} {--restore-drill : Restore into an isolated temporary directory}', function (
    BackupService $backups,
    TelemetryService $telemetry,
): int {
    $restoreDrill = (bool) $this->option('restore-drill');
    $backupArgument = $this->argument('backup');

    if (! is_string($backupArgument)) {
        $this->error('Direktori backup tidak valid.');

        return 1;
    }

    try {
        $result = $backups->verify($backupArgument, $restoreDrill);
        $telemetry->record('backup.verified', [
            'backup_id' => $result['backup_id'],
            'asset_entries' => $result['asset_entries'],
            'restore_drill' => $result['restore_drill'],
        ]);

        $this->info("Backup {$result['backup_id']} valid.");
        $this->line('Database integrity: '.$result['database_integrity']);
        $this->line('Asset entries: '.$result['asset_entries']);

        if ($restoreDrill) {
            $this->line('Restore drill: isolated staging berhasil diverifikasi.');
        }

        return 0;
    } catch (Throwable $exception) {
        report($exception);
        $telemetry->record('backup.verification_failed', [
            'reason' => $exception::class,
            'restore_drill' => $restoreDrill,
        ], 'error');
        $this->error('Verifikasi backup gagal. Periksa manifest dan log operasional.');

        return 1;
    }
})->purpose('Verify a SQLite backup and optionally run an isolated restore drill');

Artisan::command('ops:backup:verify-latest {--destination= : Override the configured backup destination}', function (
    BackupService $backups,
    TelemetryService $telemetry,
): int {
    try {
        $destination = $this->option('destination');
        $destination = is_string($destination) && trim($destination) !== '' ? $destination : null;
        $backup = $backups->latest($destination);
        $result = $backups->verify($backup, restoreDrill: true);
        $telemetry->record('backup.restore_drill_completed', [
            'backup_id' => $result['backup_id'],
            'asset_entries' => $result['asset_entries'],
        ]);

        $this->info("Restore drill backup {$result['backup_id']} berhasil.");
        $this->line('Database integrity: '.$result['database_integrity']);
        $this->line('Asset entries: '.$result['asset_entries']);

        return 0;
    } catch (Throwable $exception) {
        report($exception);
        $telemetry->record('backup.restore_drill_failed', [
            'reason' => $exception::class,
        ], 'error');
        $this->error('Restore drill gagal. Periksa backup terbaru dan log operasional.');

        return 1;
    }
})->purpose('Verify the latest SQLite backup with an isolated restore drill');

Schedule::command('payments:reconcile --limit=100')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('payments:expire --limit=100')
    ->everyMinute()
    ->withoutOverlapping();

Artisan::command('subscriptions:expire', function (): int {
    $subscriptions = Subscription::withoutGlobalScopes()
        ->whereIn('status', [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue])
        ->get();
    $expired = 0;

    foreach ($subscriptions as $subscription) {
        $hasExpiredTrial = $subscription->status === SubscriptionStatus::Trialing
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isPast();
        $hasExpiredPeriod = in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::PastDue], true)
            && $subscription->current_period_ends_at !== null
            && $subscription->current_period_ends_at->isPast();

        if (! $hasExpiredTrial && ! $hasExpiredPeriod) {
            continue;
        }

        $subscription->update(['status' => SubscriptionStatus::Expired]);
        $expired++;
    }

    $this->info("Subscription kadaluarsa: {$expired}.");

    return 0;
})->purpose('Expire trial and subscription periods');

Schedule::command('subscriptions:expire')
    ->hourly()
    ->withoutOverlapping();

if ((bool) config('operations.backup.enabled', false)) {
    Schedule::command('ops:backup')
        ->dailyAt('02:00')
        ->withoutOverlapping();
}

if ((bool) config('operations.backup.restore_drill_enabled', false)) {
    Schedule::command('ops:backup:verify-latest')
        ->quarterly()
        ->withoutOverlapping();
}

$queueConnection = (string) config('queue.default', 'database');

if ((bool) config('observability.queue_monitor_enabled', true)
    && in_array($queueConnection, ['database', 'redis'], true)) {
    $queueConfig = config("queue.connections.{$queueConnection}", []);
    $queueName = is_array($queueConfig) ? (string) ($queueConfig['queue'] ?? 'default') : 'default';
    $queueThreshold = (int) config('observability.queue_depth_threshold', 100);

    Schedule::command("queue:monitor {$queueConnection}:{$queueName} --max={$queueThreshold}")
        ->everyMinute()
        ->withoutOverlapping();
}

Artisan::command('ops:health {--json : Output machine-readable JSON}', function (): int {
    $checks = [];
    $degraded = false;
    $addCheck = function (string $name, string $status, string $value) use (&$checks, &$degraded): void {
        if (! in_array($status, ['ok', 'skipped'], true)) {
            $degraded = true;
        }

        $checks[] = [
            'check' => $name,
            'status' => $status,
            'value' => $value,
        ];
    };

    try {
        DB::connection()->getPdo();
        $addCheck('database', 'ok', 'reachable');
    } catch (Throwable $exception) {
        $addCheck('database', 'fail', $exception::class);
    }

    $queueDriver = (string) config('queue.default');

    if ($queueDriver === 'database') {
        $queueTable = (string) config('queue.connections.database.table', 'jobs');
        $queueThreshold = (int) config('observability.queue_depth_threshold', 100);

        try {
            $depth = DB::table($queueTable)->count();
            $addCheck(
                'queue_depth',
                $depth > $queueThreshold ? 'warning' : 'ok',
                "{$depth} (threshold {$queueThreshold})",
            );
        } catch (Throwable $exception) {
            $addCheck('queue_depth', 'fail', $exception::class);
        }
    } else {
        $addCheck('queue_depth', 'skipped', $queueDriver);
    }

    $failedDriver = (string) config('queue.failed.driver');

    if (str_starts_with($failedDriver, 'database')) {
        $failedTable = (string) config('queue.failed.table', 'failed_jobs');
        $failedThreshold = (int) config('observability.failed_jobs_threshold', 0);

        try {
            $failedJobs = DB::table($failedTable)->count();
            $addCheck(
                'failed_jobs',
                $failedJobs > $failedThreshold ? 'warning' : 'ok',
                "{$failedJobs} (threshold {$failedThreshold})",
            );
        } catch (Throwable $exception) {
            $addCheck('failed_jobs', 'fail', $exception::class);
        }
    } else {
        $addCheck('failed_jobs', 'skipped', $failedDriver);
    }

    $staleMinutes = (int) config('observability.stale_payment_event_minutes', 15);

    try {
        $staleEvents = PaymentEvent::withoutGlobalScopes()
            ->whereNull('processed_at')
            ->where('occurred_at', '<', now()->subMinutes($staleMinutes))
            ->count();
        $addCheck(
            'stale_payment_events',
            $staleEvents > 0 ? 'warning' : 'ok',
            "{$staleEvents} (older than {$staleMinutes} minutes)",
        );
    } catch (Throwable $exception) {
        $addCheck('stale_payment_events', 'fail', $exception::class);
    }

    $summary = [
        'status' => $degraded ? 'degraded' : 'ok',
        'checked_at' => now()->toIso8601String(),
        'checks' => $checks,
    ];

    if ($this->option('json')) {
        $this->line(json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    } else {
        $this->table(['Check', 'Status', 'Value'], $checks);
        $this->line('Health status: '.$summary['status']);
    }

    return $degraded ? 1 : 0;
})->purpose('Check application dependencies and operational thresholds');

Artisan::command('platform:grant {email}', function (): int {
    $user = User::query()->where('email', $this->argument('email'))->first();

    if ($user === null) {
        $this->error('User tidak ditemukan.');

        return 1;
    }

    $user->forceFill(['is_platform_admin' => true])->save();
    $this->info("Akses platform diberikan kepada {$user->email}.");

    return 0;
})->purpose('Grant platform dashboard access to a user');

Artisan::command('platform:revoke {email}', function (): int {
    $user = User::query()->where('email', $this->argument('email'))->first();

    if ($user === null) {
        $this->error('User tidak ditemukan.');

        return 1;
    }

    $user->forceFill(['is_platform_admin' => false])->save();
    $this->info("Akses platform dicabut dari {$user->email}.");

    return 0;
})->purpose('Revoke platform dashboard access from a user');
