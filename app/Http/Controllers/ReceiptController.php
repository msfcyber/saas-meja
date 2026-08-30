<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ReceiptController extends Controller
{
    public function showPublic(string $accessToken): Response
    {
        $order = $this->findPublicOrder($accessToken);

        if ($order === null) {
            abort(404, 'Order tidak ditemukan.');
        }

        return $this->render($this->loadReceipt($order));
    }

    public function showPublicJson(string $accessToken): JsonResponse
    {
        $order = $this->findPublicOrder($accessToken);

        if ($order === null) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        $order = $this->loadReceipt($order);

        return response()->json(['receipt' => $this->receiptData($order)]);
    }

    public function showStaff(Order $order): Response
    {
        $this->authorize('view', $order);

        return $this->render($this->loadReceipt($order));
    }

    private function findPublicOrder(string $accessToken): ?Order
    {
        if (strlen($accessToken) !== 64 || ! ctype_xdigit($accessToken)) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('access_token_hash', hash('sha256', $accessToken))
            ->first();
    }

    private function loadReceipt(Order $order): Order
    {
        $order->load([
            'items' => fn ($query) => $query->withoutGlobalScopes()->orderBy('id'),
            'payments' => fn ($query) => $query->withoutGlobalScopes()->orderByDesc('id'),
            'table' => fn ($query) => $query->withoutGlobalScopes(),
            'outlet' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
        $order->items->load(['modifiers' => fn ($query) => $query->withoutGlobalScopes()->orderBy('id')]);
        $this->receiptPayment($order);

        return $order;
    }

    private function receiptPayment(Order $order): Payment
    {
        $payment = $order->payments->first(
            fn (Payment $payment): bool => in_array(
                $payment->status,
                [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded, PaymentStatus::Refunded],
                true,
            ),
        );

        if ($payment === null) {
            abort(404, 'Struk tersedia setelah pembayaran berhasil.');
        }

        return $payment;
    }

    /** @return array<string, mixed> */
    private function receiptData(Order $order): array
    {
        $payment = $this->receiptPayment($order);
        $items = [];

        foreach ($order->items as $item) {
            $modifiers = [];

            foreach ($item->modifiers as $modifier) {
                $modifiers[] = [
                    'modifier_name' => $modifier->modifier_name_snapshot,
                    'option_name' => $modifier->option_name_snapshot,
                    'price_delta' => $modifier->price_delta_snapshot,
                ];
            }

            $items[] = [
                'product_name' => $item->product_name_snapshot,
                'variant_name' => $item->variant_name_snapshot,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->line_total,
                'note' => $item->note,
                'modifiers' => $modifiers,
            ];
        }

        return [
            'outlet' => [
                'name' => $order->outlet_name_snapshot ?? $order->outlet?->name,
                'address' => $order->outlet_address_snapshot ?? $order->outlet?->address,
                'phone' => $order->outlet_phone_snapshot ?? $order->outlet?->phone,
            ],
            'order' => [
                'number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'table' => $order->table_name_snapshot ?? $order->table?->name,
                'created_at' => $order->created_at?->toIso8601String(),
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => $order->subtotal,
                'discount_amount' => $order->discount_amount,
                'tax_name' => $order->tax_name_snapshot,
                'tax_amount' => $order->tax_amount,
                'fee_amount' => $order->fee_amount,
                'grand_total' => $order->grand_total,
            ],
            'currency' => $order->currency,
            'payment' => [
                'status' => $payment->status->value,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ],
        ];
    }

    private function render(Order $order): Response
    {
        return response()
            ->view('receipts.show', [
                'order' => $order,
                'payment' => $this->receiptPayment($order),
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
