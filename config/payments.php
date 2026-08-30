<?php

return [
    'default_provider' => env('PAYMENT_DEFAULT_PROVIDER', 'midtrans'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    'webhook_tolerance' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'snap_url' => env('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/v1/transactions'),
        'status_url' => env('MIDTRANS_STATUS_URL', 'https://api.sandbox.midtrans.com/v2'),
    ],
];
