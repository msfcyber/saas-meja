<?php

namespace App\Services;

use App\Enums\ModifierSelectionType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TenantStatus;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemModifier;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\TableQrToken;
use App\Models\TaxSetting;
use App\Models\Tenant;
use App\Support\PublicTableAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

final class PublicOrderService
{
    public function __construct(
        private readonly OrderStatusService $statuses,
        private readonly TelemetryService $telemetry,
        private readonly AnalyticsEventService $analytics,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: Order, access_token: string, created: bool}
     */
    public function create(PublicTableAccess $access, array $data): array
    {
        $fingerprint = $this->fingerprint($data);
        $tenantId = (int) $access->tenant->getKey();
        $outletId = (int) $access->outlet->getKey();
        $idempotencyKey = (string) $data['idempotency_key'];
        $startedAt = hrtime(true);

        try {
            $result = DB::transaction(function () use ($access, $data, $fingerprint, $tenantId, $outletId, $idempotencyKey): array {
                $this->assertFreshAccess($access);

                $existing = Order::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $outletId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if ($existing->idempotency_fingerprint !== $fingerprint) {
                        throw new ConflictHttpException('Idempotency key sudah digunakan untuk checkout yang berbeda.');
                    }

                    return [
                        'order' => $this->loadOrder($existing),
                        'access_token' => $this->decryptAccessToken($existing),
                        'created' => false,
                    ];
                }

                $items = is_array($data['items'] ?? null) ? array_values($data['items']) : [];
                $lines = [];

                foreach ($items as $index => $item) {
                    if (! is_array($item)) {
                        $this->invalid("items.{$index}", 'Item pesanan tidak valid.');
                    }

                    $lines[] = $this->priceItem($item, (int) $index, $tenantId, $outletId);
                }

                $outlet = Outlet::withoutGlobalScopes()
                    ->whereKey($outletId)
                    ->where('tenant_id', $tenantId)
                    ->firstOrFail();
                $taxSetting = TaxSetting::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('outlet_id', $outletId)
                    ->first();
                $subtotal = array_sum(array_map(
                    static fn (array $line): int => $line['line_total'],
                    $lines,
                ));
                $discountAmount = 0;
                $feeAmount = 0;
                $taxEnabled = $taxSetting !== null && $taxSetting->is_enabled === true && (int) $taxSetting->rate_basis_points > 0;
                $taxRate = $taxEnabled ? (int) $taxSetting->rate_basis_points : 0;
                $taxName = $taxEnabled && is_string($taxSetting->name) ? $taxSetting->name : null;
                $taxInclusive = $taxEnabled && $taxSetting->is_inclusive === true;
                $taxAmount = $taxEnabled
                    ? ($taxInclusive
                        ? $this->roundDivision($subtotal * $taxRate, 10000 + $taxRate)
                        : $this->roundDivision($subtotal * $taxRate, 10000))
                    : 0;
                $grandTotal = $subtotal - $discountAmount + $feeAmount + ($taxInclusive ? 0 : $taxAmount);
                $sequence = $this->nextOrderSequence($tenantId, $outletId);
                $accessToken = bin2hex(random_bytes(32));
                $now = now();

                $order = Order::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'outlet_id' => $outletId,
                    'table_id' => $access->table->getKey(),
                    'outlet_name_snapshot' => $outlet->name,
                    'outlet_address_snapshot' => $outlet->address,
                    'outlet_phone_snapshot' => $outlet->phone,
                    'table_name_snapshot' => $access->table->name,
                    'table_code_snapshot' => $access->table->code,
                    'order_sequence' => $sequence,
                    'order_number' => 'A-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                    'customer_name' => $this->nullableString($data['customer_name'] ?? null),
                    'status' => OrderStatus::Draft,
                    'subtotal' => $subtotal,
                    'discount_amount' => $discountAmount,
                    'tax_name_snapshot' => $taxName,
                    'tax_rate_snapshot' => $taxRate,
                    'tax_inclusive_snapshot' => $taxInclusive,
                    'tax_amount' => $taxAmount,
                    'fee_amount' => $feeAmount,
                    'grand_total' => $grandTotal,
                    'currency' => (string) $outlet->currency,
                    'idempotency_key' => $idempotencyKey,
                    'idempotency_fingerprint' => $fingerprint,
                    'access_token_hash' => hash('sha256', $accessToken),
                    'access_token_encrypted' => Crypt::encryptString($accessToken),
                ]);

                $this->statuses->record($order, null, OrderStatus::Draft);
                $this->statuses->transition($order, OrderStatus::AwaitingPayment);

                foreach ($lines as $line) {
                    /** @var OrderItem $orderItem */
                    $orderItem = OrderItem::withoutGlobalScopes()->create([
                        'tenant_id' => $tenantId,
                        'outlet_id' => $outletId,
                        'order_id' => $order->getKey(),
                        'product_id' => $line['product_id'],
                        'variant_id' => $line['variant_id'],
                        'product_name_snapshot' => $line['product_name'],
                        'product_description_snapshot' => $line['product_description'],
                        'variant_name_snapshot' => $line['variant_name'],
                        'base_price_snapshot' => $line['base_price'],
                        'variant_price_delta_snapshot' => $line['variant_price_delta'],
                        'modifier_amount_snapshot' => $line['modifier_amount'],
                        'unit_price' => $line['unit_price'],
                        'quantity' => $line['quantity'],
                        'line_total' => $line['line_total'],
                        'note' => $line['note'],
                    ]);

                    foreach ($line['modifiers'] as $modifier) {
                        OrderItemModifier::withoutGlobalScopes()->create([
                            'tenant_id' => $tenantId,
                            'outlet_id' => $outletId,
                            'order_item_id' => $orderItem->getKey(),
                            'modifier_id' => $modifier['modifier_id'],
                            'modifier_option_id' => $modifier['option_id'],
                            'modifier_name_snapshot' => $modifier['modifier_name'],
                            'option_name_snapshot' => $modifier['option_name'],
                            'price_delta_snapshot' => $modifier['price_delta'],
                        ]);
                    }
                }

                $payment = Payment::withoutGlobalScopes()->create([
                    'tenant_id' => $tenantId,
                    'outlet_id' => $outletId,
                    'order_id' => $order->getKey(),
                    'method' => (string) $data['payment_method'],
                    'status' => PaymentStatus::Pending,
                    'amount' => $grandTotal,
                    'currency' => (string) $outlet->currency,
                    'provider' => (string) config('payments.default_provider', 'midtrans'),
                    'expires_at' => $now->copy()->addMinutes(15),
                ]);
                $payment->update(['provider_reference' => 'meja-payment-'.$payment->getKey()]);

                return [
                    'order' => $this->loadOrder($order),
                    'access_token' => $accessToken,
                    'created' => true,
                ];
            }, attempts: 3);
        } catch (Throwable $exception) {
            $this->telemetry->recordDuration('checkout.failed', $startedAt, [
                'flow' => 'public_order',
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }

        $this->telemetry->recordDuration('checkout.completed', $startedAt, [
            'flow' => 'public_order',
            'created' => $result['created'],
            'outcome' => $result['created'] ? 'created' : 'idempotent',
        ]);

        if ($result['created']) {
            $this->analytics->recordPublic(
                'order_created',
                $access,
                orderId: (int) $result['order']->getKey(),
                properties: ['status' => OrderStatus::AwaitingPayment->value],
            );
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function priceItem(array $item, int $index, int $tenantId, int $outletId): array
    {
        $product = Product::withoutGlobalScopes()
            ->whereKey((int) $item['product_id'])
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->where('is_available', true)
            ->lockForUpdate()
            ->first();

        if ($product === null) {
            $this->invalid("items.{$index}.product_id", 'Produk sudah tidak tersedia.');
        }

        if ($product->category_id !== null && ! Category::withoutGlobalScopes()
            ->whereKey($product->category_id)
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->exists()) {
            $this->invalid("items.{$index}.product_id", 'Kategori produk sudah tidak tersedia.');
        }

        $variants = ProductVariant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->lockForUpdate()
            ->get();
        $variantId = array_key_exists('variant_id', $item) && $item['variant_id'] !== null
            ? (int) $item['variant_id']
            : null;
        $variant = $variantId === null
            ? ($variants->firstWhere('is_default', true) ?? $variants->first())
            : $variants->firstWhere('id', $variantId);

        if ($variantId !== null && $variant === null) {
            $this->invalid("items.{$index}.variant_id", 'Varian produk sudah tidak tersedia.');
        }

        $assignedModifierIds = DB::table('product_modifier')
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('product_id', $product->getKey())
            ->orderBy('position')
            ->pluck('modifier_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $modifiers = Modifier::withoutGlobalScopes()
            ->whereIn('id', $assignedModifierIds)
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->with(['options' => fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->where('outlet_id', $outletId)
                ->where('is_active', true)
                ->orderBy('position')
                ->orderBy('name')])
            ->get()
            ->keyBy('id');
        $optionIds = is_array($item['modifier_option_ids'] ?? null)
            ? array_map(static fn (mixed $id): int => (int) $id, $item['modifier_option_ids'])
            : [];

        if (count($optionIds) !== count(array_unique($optionIds))) {
            $this->invalid("items.{$index}.modifier_option_ids", 'Pilihan tambahan tidak boleh duplikat.');
        }

        $selectedOptions = ModifierOption::withoutGlobalScopes()
            ->whereIn('id', $optionIds)
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
        $validSelectedCount = $selectedOptions->filter(
            fn (ModifierOption $option): bool => $modifiers->has((int) $option->modifier_id),
        )->count();

        if ($validSelectedCount !== count($optionIds)) {
            $this->invalid("items.{$index}.modifier_option_ids", 'Pilihan tambahan tidak tersedia untuk produk ini.');
        }

        $selectedModifiers = [];
        $modifierAmount = 0;

        foreach ($modifiers as $modifier) {
            $selected = $selectedOptions->filter(
                fn (ModifierOption $option): bool => (int) $option->modifier_id === (int) $modifier->getKey(),
            );
            $selectionCount = $selected->count();
            $minimum = max((int) $modifier->minimum_selections, $modifier->is_required ? 1 : 0);
            $maximum = (int) $modifier->maximum_selections;

            if ($selectionCount < $minimum || $selectionCount > $maximum || ($modifier->selection_type === ModifierSelectionType::Single && $selectionCount > 1)) {
                $this->invalid("items.{$index}.modifier_option_ids", "Pilihan untuk {$modifier->name} tidak sesuai.");
            }

            foreach ($selected as $option) {
                $priceDelta = (int) $option->price_delta;
                $modifierAmount += $priceDelta;
                $selectedModifiers[] = [
                    'modifier_id' => $modifier->getKey(),
                    'option_id' => $option->getKey(),
                    'modifier_name' => (string) $modifier->name,
                    'option_name' => (string) $option->name,
                    'price_delta' => $priceDelta,
                ];
            }
        }

        $basePrice = (int) $product->base_price;
        $variantPriceDelta = $variant === null ? 0 : (int) $variant->price_delta;
        $unitPrice = $basePrice + $variantPriceDelta + $modifierAmount;

        if ($unitPrice < 0) {
            $this->invalid("items.{$index}.product_id", 'Harga produk tidak valid.');
        }

        $quantity = (int) $item['quantity'];

        return [
            'product_id' => $product->getKey(),
            'variant_id' => $variant?->getKey(),
            'product_name' => (string) $product->name,
            'product_description' => $product->description,
            'variant_name' => $variant?->name,
            'base_price' => $basePrice,
            'variant_price_delta' => $variantPriceDelta,
            'modifier_amount' => $modifierAmount,
            'unit_price' => $unitPrice,
            'quantity' => $quantity,
            'line_total' => $unitPrice * $quantity,
            'note' => $this->nullableString($item['note'] ?? null),
            'modifiers' => $selectedModifiers,
        ];
    }

    private function assertFreshAccess(PublicTableAccess $access): void
    {
        $tenantId = (int) $access->tenant->getKey();
        $outletId = (int) $access->outlet->getKey();
        $tableId = (int) $access->table->getKey();
        $qrToken = TableQrToken::withoutGlobalScopes()
            ->whereKey($access->qrToken->getKey())
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('table_id', $tableId)
            ->where('token_hash', hash('sha256', $access->plainToken))
            ->lockForUpdate()
            ->first();
        $tenant = Tenant::query()
            ->whereKey($tenantId)
            ->where('status', TenantStatus::Active)
            ->lockForUpdate()
            ->first();
        $outlet = Outlet::withoutGlobalScopes()
            ->whereKey($outletId)
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('accepts_orders', true)
            ->lockForUpdate()
            ->first();
        $table = DiningTable::withoutGlobalScopes()
            ->whereKey($tableId)
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($qrToken === null || $qrToken->revoked_at !== null || ($qrToken->expires_at !== null && CarbonImmutable::parse((string) $qrToken->expires_at)->isPast())) {
            $this->invalid('qr_token', 'QR meja tidak valid atau sudah tidak berlaku.');
        }

        if ($tenant === null) {
            $this->invalid('qr_token', 'Menu sedang tidak tersedia.');
        }

        if ($outlet === null) {
            $this->invalid('qr_token', 'Outlet sedang tutup dan belum menerima pesanan.');
        }

        if ($table === null) {
            $this->invalid('qr_token', 'Meja ini sedang tidak aktif.');
        }
    }

    private function nextOrderSequence(int $tenantId, int $outletId): int
    {
        $latest = Order::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('outlet_id', $outletId)
            ->orderByDesc('order_sequence')
            ->lockForUpdate()
            ->first();

        return ($latest === null ? 0 : (int) $latest->order_sequence) + 1;
    }

    /** @param array<string, mixed> $data */
    private function fingerprint(array $data): string
    {
        unset($data['idempotency_key']);

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function decryptAccessToken(Order $order): string
    {
        try {
            return Crypt::decryptString((string) $order->access_token_encrypted);
        } catch (Throwable) {
            throw new ConflictHttpException('Token akses order tidak dapat dipulihkan.');
        }
    }

    private function loadOrder(Order $order): Order
    {
        $order->load([
            'items' => fn ($query) => $query->withoutGlobalScopes(),
            'payments' => fn ($query) => $query->withoutGlobalScopes(),
            'statusHistories' => fn ($query) => $query->withoutGlobalScopes(),
            'table' => fn ($query) => $query->withoutGlobalScopes(),
            'outlet' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
        $order->items->load(['modifiers' => fn ($query) => $query->withoutGlobalScopes()]);

        return $order;
    }

    private function roundDivision(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
