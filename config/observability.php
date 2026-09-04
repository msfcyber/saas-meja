<?php

return [
    'slow_request_ms' => max(0, (int) env('OBSERVABILITY_SLOW_REQUEST_MS', 1000)),
    'queue_depth_threshold' => max(0, (int) env('OBSERVABILITY_QUEUE_DEPTH_THRESHOLD', 100)),
    'queue_monitor_enabled' => (bool) env('OBSERVABILITY_QUEUE_MONITOR_ENABLED', true),
    'failed_jobs_threshold' => max(0, (int) env('OBSERVABILITY_FAILED_JOBS_THRESHOLD', 0)),
    'stale_payment_event_minutes' => max(1, (int) env('OBSERVABILITY_STALE_PAYMENT_EVENT_MINUTES', 15)),
    'pending_refunds_threshold' => max(0, (int) env('OBSERVABILITY_PENDING_REFUNDS_THRESHOLD', 0)),
    'pending_invoices_threshold' => max(0, (int) env('OBSERVABILITY_PENDING_INVOICES_THRESHOLD', 100)),
];
