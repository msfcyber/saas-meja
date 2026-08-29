<?php

use App\Http\Controllers\Context\SwitchOutletController;
use App\Http\Controllers\Context\SwitchTenantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductAvailabilityController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\TableQrCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('q/{qrToken}', [PublicMenuController::class, 'show'])
    ->middleware('throttle:qr-public')
    ->name('public.qr');

Route::get('q/{qrToken}/checkout', [PublicOrderController::class, 'checkout'])
    ->middleware('throttle:qr-public')
    ->name('public.checkout');

Route::get('o/{accessToken}', [PublicOrderController::class, 'show'])
    ->middleware('throttle:public-orders')
    ->name('public.order');

Route::prefix('demo')->group(function () {
    Route::inertia('menu', 'customer/menu')->name('demo.menu');
    Route::inertia('checkout', 'customer/checkout')->name('demo.checkout');
    Route::inertia('tracking', 'customer/tracking')->name('demo.tracking');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::post('context/tenant/{tenant}', SwitchTenantController::class)->name('context.tenant.switch');

    Route::middleware('tenant.required')->group(function () {
        Route::post('context/outlet/{outlet}', SwitchOutletController::class)->name('context.outlet.switch');
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:order.view')->name('dashboard');
        Route::get('orders', [OrderController::class, 'index'])->middleware('permission:order.view')->name('orders');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:order.update_status')->name('orders.status.update');
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:menu.manage')->name('products');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:menu.manage')->name('products.store');
        Route::patch('products/{product}/availability', ProductAvailabilityController::class)->middleware('permission:menu.manage')->name('products.availability.update');
        Route::get('tables', [DiningTableController::class, 'index'])->middleware('permission:table.manage')->name('tables');
        Route::post('tables', [DiningTableController::class, 'store'])->middleware('permission:table.manage')->name('tables.store');
        Route::post('tables/{table}/regenerate-qr', [TableQrCodeController::class, 'regenerate'])->middleware('permission:table.manage')->name('tables.qr.regenerate');
        Route::post('tables/{table}/revoke-qr', [TableQrCodeController::class, 'revoke'])->middleware('permission:table.manage')->name('tables.qr.revoke');
        Route::get('tables/{table}/qr/download', [TableQrCodeController::class, 'download'])->middleware('permission:table.manage')->name('tables.qr.download');
        Route::get('tables/{table}/qr/print', [TableQrCodeController::class, 'print'])->middleware('permission:table.manage')->name('tables.qr.print');
    });
});

require __DIR__.'/settings.php';
