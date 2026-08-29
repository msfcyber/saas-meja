<?php

use App\Http\Controllers\Context\SwitchOutletController;
use App\Http\Controllers\Context\SwitchTenantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\ProductAvailabilityController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

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
        Route::inertia('orders', 'orders')->middleware('permission:order.view')->name('orders');
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:menu.manage')->name('products');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:menu.manage')->name('products.store');
        Route::patch('products/{product}/availability', ProductAvailabilityController::class)->middleware('permission:menu.manage')->name('products.availability.update');
        Route::get('tables', [DiningTableController::class, 'index'])->middleware('permission:table.manage')->name('tables');
        Route::post('tables', [DiningTableController::class, 'store'])->middleware('permission:table.manage')->name('tables.store');
    });
});

require __DIR__.'/settings.php';
