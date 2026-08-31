<?php

return [
    'backup' => [
        'enabled' => (bool) env('OPS_BACKUP_ENABLED', false),
        'destination' => env('OPS_BACKUP_DESTINATION', storage_path('backups')),
        'retention_days' => max(1, (int) env('OPS_BACKUP_RETENTION_DAYS', 30)),
        'restore_drill_enabled' => (bool) env('OPS_BACKUP_RESTORE_DRILL_ENABLED', false),
        'remote_enabled' => (bool) env('OPS_BACKUP_REMOTE_ENABLED', false),
        'remote_disk' => env('OPS_BACKUP_REMOTE_DISK', 's3-backup'),
        'remote_prefix' => trim((string) env('OPS_BACKUP_REMOTE_PREFIX', 'meja'), '/'),
        'mysql_credentials_file' => env('OPS_BACKUP_MYSQL_CREDENTIALS_FILE', '/run/secrets/meja-mysql.cnf'),
        'mysql_dump_binary' => env('OPS_BACKUP_MYSQL_DUMP_BINARY', 'mysqldump'),
        'mysql_dump_timeout' => max(60, (int) env('OPS_BACKUP_MYSQL_DUMP_TIMEOUT', 900)),
    ],
];
