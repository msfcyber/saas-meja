<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => env('FILESYSTEM_PUBLIC_DRIVER', 'local'),
            'root' => env('FILESYSTEM_PUBLIC_ROOT') ?: storage_path('app/public'),
            'url' => env('FILESYSTEM_PUBLIC_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage'),
            'visibility' => 'public',
            'key' => env('FILESYSTEM_PUBLIC_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('FILESYSTEM_PUBLIC_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('FILESYSTEM_PUBLIC_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('FILESYSTEM_PUBLIC_BUCKET', env('AWS_BUCKET')),
            'endpoint' => env('FILESYSTEM_PUBLIC_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('FILESYSTEM_PUBLIC_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        's3-backup' => [
            'driver' => 's3',
            'key' => env('AWS_BACKUP_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('AWS_BACKUP_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('AWS_BACKUP_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
            'bucket' => env('AWS_BACKUP_BUCKET'),
            'endpoint' => env('AWS_BACKUP_ENDPOINT', env('AWS_ENDPOINT')),
            'use_path_style_endpoint' => env('AWS_BACKUP_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
            'visibility' => 'private',
            'throw' => true,
            'report' => true,
            'options' => array_filter([
                'ServerSideEncryption' => env('AWS_BACKUP_SERVER_SIDE_ENCRYPTION', 'AES256'),
                'SSEKMSKeyId' => env('AWS_BACKUP_SSE_KMS_KEY_ID'),
            ]),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
