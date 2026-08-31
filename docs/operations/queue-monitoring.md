# Queue Monitoring

The application schedules Laravel's `queue:monitor` command every minute when
the configured queue connection is `database` or `redis`. The monitor uses the
same threshold as the `ops:health` command:

```dotenv
OBSERVABILITY_QUEUE_MONITOR_ENABLED=true
OBSERVABILITY_QUEUE_DEPTH_THRESHOLD=100
```

When the queue reaches the threshold, Laravel dispatches `QueueBusy`. The
application records a structured `queue.busy` warning containing only the
connection, queue, size, and threshold. Route the application telemetry log to
the production alerting system and alert on `event=queue.busy`.

## Deployment

Run one scheduler process or a system cron entry for the application:

```sh
php artisan schedule:work
```

Run a worker for the selected queue connection. Production should use Redis
for queued reconciliation jobs and retry/backoff handling:

```dotenv
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
```

The scheduled `payments:reconcile` command dispatches one
`ReconcilePaymentJob` per pending payment when the queue connection is not
`sync`. The job retries transient provider failures three times with 60, 300,
and 900 second backoff intervals. The `sync` connection is retained for local
and test determinism.

The current Laravel 13 dependency graph does not have a stable Horizon release
whose `illuminate/*` constraints include v13. The Docker deployment therefore
uses `queue:work redis` and the existing queue monitor. Add Horizon only after a
compatible release is available, and configure its supervisors for Redis queues
only. Do not point Horizon at the local database queue configuration.

## Response

1. Confirm the worker process is running and connected to the configured queue.
2. Inspect failed jobs with `php artisan queue:failed` and retry only known-safe
   jobs.
3. Check application telemetry for recurring `queue.busy` events.
4. Use `php artisan ops:health --json` to verify queue depth, failed jobs, and
   stale payment events.
5. Record the incident and any queue configuration or worker changes.
