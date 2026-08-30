<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\Customer\StoreGuestOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TaxSetting;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentGatewayException;
use App\Services\PublicOrderService;
use App\Services\PublicTableAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PublicOrderController extends Controller
{
    public function checkout(Request $request, string $qrToken, PublicTableAccessService $accessService): Response
    {
        $access = $accessService->resolve($qrToken);

        if ($access === null) {
            return Inertia::render('customer/menu', [
                'access' => [
                    'valid' => false,
                    'message' => 'QR meja tidak valid atau outlet belum menerima pesanan.',
                ],
                'outlet' => null,
                'table' => null,
                'categories' => [],
                'products' => [],
            ]);
        }

        $taxSetting = TaxSetting::withoutGlobalScopes()
            ->where('tenant_id', $access->tenant->getKey())
            ->where('outlet_id', $access->outlet->getKey())
            ->first();

        return Inertia::render('customer/checkout', [
            'access' => ['valid' => true, 'message' => null],
            'qr_token' => $qrToken,
            'outlet' => [
                'name' => $access->outlet->name,
                'currency' => $access->outlet->currency,
            ],
            'table' => [
                'name' => $access->table->name,
                'code' => $access->table->code,
            ],
            'tax' => [
                'enabled' => $taxSetting?->is_enabled === true,
                'name' => $taxSetting?->name,
                'rate_basis_points' => $taxSetting === null ? 0 : $taxSetting->rate_basis_points,
                'inclusive' => $taxSetting?->is_inclusive === true,
            ],
        ]);
    }

    public function store(
        StoreGuestOrderRequest $request,
        PublicTableAccessService $accessService,
        PublicOrderService $orders,
    ): JsonResponse {
        $data = $request->validated();
        $access = $accessService->resolve((string) $data['qr_token']);

        if ($access === null) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR meja tidak valid atau outlet belum menerima pesanan.',
            ]);
        }

        $result = $orders->create($access, $data);
        $order = $result['order'];
        $orderResource = (new OrderResource($order))->resolve($request);

        return response()->json([
            'order' => $orderResource,
            'access_token' => $result['access_token'],
            'tracking_url' => route('public.order', ['accessToken' => $result['access_token']]),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function show(Request $request, string $accessToken): Response
    {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return Inertia::render('customer/tracking', [
                'access' => [
                    'valid' => false,
                    'message' => 'Tautan tracking order tidak valid atau sudah tidak tersedia.',
                ],
                'order' => null,
            ]);
        }

        $this->loadPublicOrder($order);

        return Inertia::render('customer/tracking', [
            'access' => ['valid' => true, 'message' => null],
            'order' => (new OrderResource($order))->resolve($request),
            'realtime' => [
                'channel' => OrderStatusUpdated::customerChannel((string) $order->access_token_hash),
                'poll_url' => route('public.orders.show', ['accessToken' => $accessToken]),
            ],
        ]);
    }

    public function showJson(Request $request, string $accessToken): JsonResponse
    {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        $this->loadPublicOrder($order);

        return response()->json([
            'order' => (new OrderResource($order))->resolve($request),
        ]);
    }

    public function startPayment(
        Request $request,
        string $accessToken,
        PaymentCheckoutService $payments,
    ): JsonResponse {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        $payment = Payment::withoutGlobalScopes()
            ->where('order_id', $order->getKey())
            ->latest('id')
            ->firstOrFail();

        if ($payment->status !== PaymentStatus::Pending) {
            return response()->json(['message' => 'Payment ini tidak lagi menunggu pembayaran.'], 409);
        }

        try {
            $checkout = $payments->start(
                $payment,
                $order,
                route('public.order', ['accessToken' => $accessToken]),
            );
        } catch (PaymentGatewayException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }

        return response()->json($checkout);
    }

    private function findOrder(string $accessToken): ?Order
    {
        if (strlen($accessToken) !== 64 || ! ctype_xdigit($accessToken)) {
            return null;
        }

        return Order::withoutGlobalScopes()
            ->where('access_token_hash', hash('sha256', $accessToken))
            ->first();
    }

    private function loadPublicOrder(Order $order): void
    {
        $order->load([
            'items' => fn ($query) => $query->withoutGlobalScopes(),
            'payments' => fn ($query) => $query->withoutGlobalScopes(),
            'statusHistories' => fn ($query) => $query->withoutGlobalScopes(),
            'table' => fn ($query) => $query->withoutGlobalScopes(),
            'outlet' => fn ($query) => $query->withoutGlobalScopes(),
        ]);
        $order->items->load(['modifiers' => fn ($query) => $query->withoutGlobalScopes()]);
    }
}
