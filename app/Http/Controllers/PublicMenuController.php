<?php

namespace App\Http\Controllers;

use App\Enums\TenantStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableQrToken;
use App\Models\Tenant;
use App\Services\AnalyticsEventService;
use App\Services\SubscriptionEntitlementService;
use App\Support\PublicTableAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class PublicMenuController extends Controller
{
    public function show(
        Request $request,
        string $qrToken,
        SubscriptionEntitlementService $entitlements,
        AnalyticsEventService $analytics,
    ): Response {
        if (strlen($qrToken) !== 64 || ! ctype_xdigit($qrToken)) {
            return $this->invalid($request, 'QR meja tidak valid atau sudah tidak berlaku.');
        }

        $token = TableQrToken::withoutGlobalScopes()
            ->where('token_hash', hash('sha256', $qrToken))
            ->first();

        if ($token === null || $token->revoked_at !== null || ($token->expires_at !== null && CarbonImmutable::parse($token->expires_at)->isPast())) {
            return $this->invalid($request, 'QR meja tidak valid atau sudah tidak berlaku.');
        }

        $tenant = Tenant::query()
            ->whereKey($token->tenant_id)
            ->where('status', TenantStatus::Active)
            ->first();

        if ($tenant === null) {
            return $this->invalid($request, 'Menu sedang tidak tersedia.');
        }

        if (! $entitlements->canAcceptOrders($tenant)) {
            return $this->invalid($request, 'Menu sedang tidak tersedia karena subscription belum aktif.');
        }

        $outlet = Outlet::withoutGlobalScopes()
            ->whereKey($token->outlet_id)
            ->where('tenant_id', $token->tenant_id)
            ->first();

        if ($outlet === null || ! $outlet->is_active || ! $outlet->accepts_orders) {
            return $this->invalid($request, 'Outlet sedang tutup dan belum menerima pesanan.');
        }

        $table = DiningTable::withoutGlobalScopes()
            ->whereKey($token->table_id)
            ->where('tenant_id', $token->tenant_id)
            ->where('outlet_id', $token->outlet_id)
            ->first();

        if ($table === null || ! $table->is_active) {
            return $this->invalid($request, 'Meja ini sedang tidak aktif. Silakan minta bantuan staf.');
        }

        $token->update(['last_used_at' => now()]);
        $access = new PublicTableAccess($qrToken, $token, $tenant, $outlet, $table);
        $analytics->recordPublic('qr_opened', $access, $request->session()->getId());
        $analytics->recordPublic('menu_viewed', $access, $request->session()->getId());

        $categories = Category::withoutGlobalScopes()
            ->where('tenant_id', $token->tenant_id)
            ->where('outlet_id', $token->outlet_id)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();
        $categoryIds = $categories->modelKeys();
        $categoryNames = $categories->pluck('name', 'id');

        $products = Product::withoutGlobalScopes()
            ->where('tenant_id', $token->tenant_id)
            ->where('outlet_id', $token->outlet_id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('category_id')->orWhereIn('category_id', $categoryIds))
            ->with([
                'variants' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $token->tenant_id)
                    ->where('outlet_id', $token->outlet_id)
                    ->where('is_active', true)
                    ->orderBy('position')
                    ->orderBy('name'),
                'modifiers' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->where('modifiers.tenant_id', $token->tenant_id)
                    ->where('modifiers.outlet_id', $token->outlet_id)
                    ->where('modifiers.is_active', true)
                    ->with(['options' => fn ($query) => $query
                        ->withoutGlobalScopes()
                        ->where('tenant_id', $token->tenant_id)
                        ->where('outlet_id', $token->outlet_id)
                        ->where('is_active', true)
                        ->orderBy('position')
                        ->orderBy('name')]),
            ])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return $this->render($request, [
            'access' => [
                'valid' => true,
                'message' => null,
                'qr_token' => $qrToken,
            ],
            'outlet' => [
                'name' => $outlet->name,
                'address' => $outlet->address,
                'currency' => $outlet->currency,
            ],
            'table' => [
                'name' => $table->name,
                'code' => $table->code,
            ],
            'categories' => $categories->pluck('name')->values(),
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category_id === null
                    ? 'Lainnya'
                    : ($categoryNames->get($product->category_id) ?? 'Lainnya'),
                'description' => $product->description,
                'price' => $product->base_price,
                'image' => $product->image_path === null ? null : Storage::disk('public')->url($product->image_path),
                'popular' => $product->is_featured,
                'is_available' => $product->is_available,
                'variants' => $product->variants->map(fn (ProductVariant $variant) => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price_delta' => $variant->price_delta,
                    'is_default' => $variant->is_default,
                ])->values(),
                'modifiers' => $product->modifiers->map(fn (Modifier $modifier) => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'selection_type' => $modifier->selection_type->value,
                    'minimum_selections' => $modifier->minimum_selections,
                    'maximum_selections' => $modifier->maximum_selections,
                    'is_required' => $modifier->is_required,
                    'options' => $modifier->options->map(fn (ModifierOption $option) => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                    ])->values(),
                ])->values(),
            ])->values(),
        ]);
    }

    private function invalid(Request $request, string $message): Response
    {
        return $this->render($request, [
            'access' => [
                'valid' => false,
                'message' => $message,
                'qr_token' => null,
            ],
            'outlet' => null,
            'table' => null,
            'categories' => [],
            'products' => [],
        ]);
    }

    /** @param array<string, mixed> $props */
    private function render(Request $request, array $props): Response
    {
        $response = Inertia::render('customer/menu', $props)->toResponse($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
