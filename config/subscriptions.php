<?php

return [
    'trial_days' => (int) env('SUBSCRIPTION_TRIAL_DAYS', 14),
    'default_plan' => [
        'code' => env('SUBSCRIPTION_DEFAULT_PLAN', 'tumbuh'),
        'name' => 'Tumbuh',
        'description' => 'Plan awal untuk bisnis F&B yang sedang bertumbuh.',
        'price' => 299000,
        'currency' => 'IDR',
        'billing_interval' => 'monthly',
        'limits' => [
            'outlets' => 3,
            'active_tables' => 100,
            'staff' => 10,
        ],
        'features' => [
            'menu',
            'qr_ordering',
            'order_board',
            'online_payment',
            'sales_report',
        ],
    ],
];
