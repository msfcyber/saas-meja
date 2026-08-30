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
when queue throughput and Horizon are required:

```dotenv
QUEUE_CONNECTION=redis
REDIS_QUEUE=default
```

Horizon is not enabled by this repository yet. If it is introduced, install it
as a deployment dependency and configure its supervisors for Redis queues only.
Do not point Horizon at the local database queue configuration.

## Response

1. Confirm the worker process is running and connected to the configured queue.
2. Inspect failed jobs with `php artisan queue:failed` and retry only known-safe
   jobs.
3. Check application telemetry for recurring `queue.busy` events.
4. Use `php artisan ops:health --json` to verify queue depth, failed jobs, and
   stale payment events.
5. Record the incident and any queue configuration or worker changes.
