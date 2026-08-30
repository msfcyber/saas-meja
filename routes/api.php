<?php

use App\Http\Controllers\MidtransWebhookController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Middleware\VerifyPaymentWebhookSignature;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/payments/{provider}', PaymentWebhookController::class)
    ->where('provider', '[a-z0-9_-]+')
    ->middleware(VerifyPaymentWebhookSignature::class)
    ->name('payments.webhook');

Route::post('webhooks/midtrans', MidtransWebhookController::class)->name('payments.midtrans.webhook');

Route::middleware('throttle:public-orders')->group(function () {
    Route::post('public/orders', [PublicOrderController::class, 'store'])->name('public.orders.store');
    Route::get('public/orders/{accessToken}', [PublicOrderController::class, 'showJson'])->name('public.orders.show');
    Route::post('public/orders/{accessToken}/payment', [PublicOrderController::class, 'startPayment'])
        ->name('public.orders.payment.start');
});
