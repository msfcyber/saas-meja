<?php

return [
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
    'webhook_tolerance' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),
];
