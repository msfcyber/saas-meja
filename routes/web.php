<?php

use App\Http\Controllers\Auth\GoogleAuthenticationController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Context\SwitchOutletController;
use App\Http\Controllers\Context\SwitchTenantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiningTableController;
use App\Http\Controllers\ModifierController;
use App\Http\Controllers\ModifierOptionController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OutletController;
use App\Http\Controllers\PaymentRefundController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformManagementController;
use App\Http\Controllers\ProductAvailabilityController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductModifierController;
use App\Http\Controllers\ProductVariantController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicOrderController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TableQrCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::get('auth/google/redirect', [GoogleAuthenticationController::class, 'redirect'])
    ->middleware('throttle:public-orders')
    ->name('auth.google.redirect');
Route::get('auth/google/callback', [GoogleAuthenticationController::class, 'callback'])
    ->middleware('throttle:public-orders')
    ->name('auth.google.callback');

Route::get('q/{qrToken}', [PublicMenuController::class, 'show'])
    ->middleware('throttle:qr-public')
    ->name('public.qr');

Route::get('q/{qrToken}/checkout', [PublicOrderController::class, 'checkout'])
    ->middleware('throttle:qr-public')
    ->name('public.checkout');

Route::get('o/{accessToken}', [PublicOrderController::class, 'show'])
    ->middleware('throttle:public-orders')
    ->name('public.order');

Route::get('o/{accessToken}/receipt', [ReceiptController::class, 'showPublic'])
    ->middleware('throttle:public-orders')
    ->name('public.order.receipt');

Route::prefix('demo')->group(function () {
    Route::inertia('menu', 'customer/menu')->name('demo.menu');
    Route::inertia('checkout', 'customer/checkout')->name('demo.checkout');
    Route::inertia('tracking', 'customer/tracking')->name('demo.tracking');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    Route::post('context/tenant/{tenant}', SwitchTenantController::class)->name('context.tenant.switch');

    Route::get('platform', PlatformDashboardController::class)
        ->middleware('can:platform.admin')
        ->name('platform.dashboard');
    Route::middleware('can:platform.admin')->prefix('platform')->group(function () {
        Route::post('plans', [PlatformManagementController::class, 'storePlan'])->name('platform.plans.store');
        Route::patch('plans/{plan}', [PlatformManagementController::class, 'updatePlan'])->name('platform.plans.update');
        Route::patch('tenants/{tenant}/status', [PlatformManagementController::class, 'updateTenantStatus'])->name('platform.tenants.status.update');
        Route::patch('subscriptions/{subscription}', [PlatformManagementController::class, 'updateSubscription'])->name('platform.subscriptions.update');
        Route::patch('invoices/{invoice}/void', [PlatformManagementController::class, 'voidInvoice'])->name('platform.invoices.void');
        Route::post('payments/{payment}/reconcile', [PlatformManagementController::class, 'reconcilePayment'])->name('platform.payments.reconcile');
    });

    Route::middleware('tenant.required')->group(function () {
        Route::post('context/outlet/{outlet}', SwitchOutletController::class)->name('context.outlet.switch');
        Route::get('dashboard', [DashboardController::class, 'index'])->middleware('permission:order.view')->name('dashboard');
        Route::get('subscription', [SubscriptionController::class, 'index'])->middleware('permission:subscription.manage')->name('subscription');
        Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('permission:subscription.manage')->name('subscription.checkout');
        Route::get('reports/sales', [ReportController::class, 'sales'])->middleware('permission:report.view')->name('reports.sales');
        Route::get('outlets', [OutletController::class, 'index'])->middleware('permission:outlet.manage')->name('outlets');
        Route::post('outlets', [OutletController::class, 'store'])->middleware('permission:outlet.manage')->name('outlets.store');
        Route::patch('outlets/{outlet}', [OutletController::class, 'update'])->middleware('permission:outlet.manage')->name('outlets.update');
        Route::patch('outlets/{outlet}/tax-settings', [OutletController::class, 'updateTaxSettings'])->middleware('permission:tax.manage')->name('outlets.tax-settings.update');
        Route::get('staff', [StaffController::class, 'index'])->middleware('permission:staff.manage')->name('staff');
        Route::post('staff', [StaffController::class, 'store'])->middleware('permission:staff.manage')->name('staff.store');
        Route::patch('staff/{staff}', [StaffController::class, 'update'])->middleware('permission:staff.manage')->name('staff.update');
        Route::delete('staff/{staff}', [StaffController::class, 'destroy'])->middleware('permission:staff.manage')->name('staff.destroy');
        Route::get('orders', [OrderController::class, 'index'])->middleware('permission:order.view')->name('orders');
        Route::put('orders/notifications', [OrderController::class, 'updateNotificationPreferences'])->middleware('permission:order.view')->name('orders.notifications.update');
        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->middleware('permission:order.update_status')->name('orders.status.update');
        Route::post('orders/{order}/refund', [PaymentRefundController::class, 'store'])->middleware('permission:payment.refund')->name('orders.refund');
        Route::get('orders/{order}/receipt', [ReceiptController::class, 'showStaff'])->middleware('permission:payment.view')->name('orders.receipt');
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:menu.manage')->name('products');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:menu.manage')->name('products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->middleware('permission:menu.manage')->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:menu.manage')->name('products.destroy');
        Route::patch('products/{product}/availability', ProductAvailabilityController::class)->middleware('permission:menu.manage')->name('products.availability.update');
        Route::post('products/{product}/variants', [ProductVariantController::class, 'store'])->middleware('permission:menu.manage')->name('products.variants.store');
        Route::put('products/{product}/modifiers', [ProductModifierController::class, 'update'])->middleware('permission:menu.manage')->name('products.modifiers.update');
        Route::patch('product-variants/{variant}', [ProductVariantController::class, 'update'])->middleware('permission:menu.manage')->name('product-variants.update');
        Route::delete('product-variants/{variant}', [ProductVariantController::class, 'destroy'])->middleware('permission:menu.manage')->name('product-variants.destroy');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:menu.manage')->name('categories.store');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:menu.manage')->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:menu.manage')->name('categories.destroy');
        Route::post('modifiers', [ModifierController::class, 'store'])->middleware('permission:menu.manage')->name('modifiers.store');
        Route::patch('modifiers/{modifier}', [ModifierController::class, 'update'])->middleware('permission:menu.manage')->name('modifiers.update');
        Route::delete('modifiers/{modifier}', [ModifierController::class, 'destroy'])->middleware('permission:menu.manage')->name('modifiers.destroy');
        Route::post('modifiers/{modifier}/options', [ModifierOptionController::class, 'store'])->middleware('permission:menu.manage')->name('modifiers.options.store');
        Route::patch('modifier-options/{option}', [ModifierOptionController::class, 'update'])->middleware('permission:menu.manage')->name('modifier-options.update');
        Route::delete('modifier-options/{option}', [ModifierOptionController::class, 'destroy'])->middleware('permission:menu.manage')->name('modifier-options.destroy');
        Route::get('tables', [DiningTableController::class, 'index'])->middleware('permission:table.manage')->name('tables');
        Route::post('tables', [DiningTableController::class, 'store'])->middleware('permission:table.manage')->name('tables.store');
        Route::patch('tables/{table}', [DiningTableController::class, 'update'])->middleware('permission:table.manage')->name('tables.update');
        Route::post('tables/{table}/regenerate-qr', [TableQrCodeController::class, 'regenerate'])->middleware('permission:table.manage')->name('tables.qr.regenerate');
        Route::post('tables/{table}/revoke-qr', [TableQrCodeController::class, 'revoke'])->middleware('permission:table.manage')->name('tables.qr.revoke');
        Route::get('tables/{table}/qr/download', [TableQrCodeController::class, 'download'])->middleware('permission:table.manage')->name('tables.qr.download');
        Route::get('tables/{table}/qr/print', [TableQrCodeController::class, 'print'])->middleware('permission:table.manage')->name('tables.qr.print');
    });
});

require __DIR__.'/settings.php';
