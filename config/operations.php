<?php

return [
    'backup' => [
        'enabled' => (bool) env('OPS_BACKUP_ENABLED', false),
        'destination' => env('OPS_BACKUP_DESTINATION', storage_path('backups')),
        'retention_days' => max(1, (int) env('OPS_BACKUP_RETENTION_DAYS', 30)),
        'restore_drill_enabled' => (bool) env('OPS_BACKUP_RESTORE_DRILL_ENABLED', false),
    ],
];
