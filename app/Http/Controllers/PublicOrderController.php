<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\Customer\StartGuestPaymentRequest;
use App\Http\Requests\Customer\StoreGuestOrderRequest;
use App\Http\Requests\Customer\ValidateGuestCartRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TaxSetting;
use App\Services\AnalyticsEventService;
use App\Services\PaymentCheckoutService;
use App\Services\PaymentGatewayException;
use App\Services\PaymentLifecycleService;
use App\Services\PublicAnalyticsSessionService;
use App\Services\PublicOrderService;
use App\Services\PublicTableAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PublicOrderController extends Controller
{
    public function checkout(Request $request, string $qrToken, PublicTableAccessService $accessService, PublicAnalyticsSessionService $analyticsSessions): Response
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
            'analytics_token' => $analyticsSessions->issue($access)['token'],
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

        return $this->noStore(response()->json([
            'order' => $orderResource,
            'access_token' => $result['access_token'],
            'tracking_url' => route('public.order', ['accessToken' => $result['access_token']]),
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200));
    }

    public function validateCart(
        ValidateGuestCartRequest $request,
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

        return $this->noStore(response()->json(['quote' => $orders->preview($access, $data)]));
    }

    public function show(
        Request $request,
        string $accessToken,
        PaymentLifecycleService $lifecycle,
    ): HttpResponse {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return $this->noStoreResponse(Inertia::render('customer/tracking', [
                'access' => [
                    'valid' => false,
                    'message' => 'Tautan tracking order tidak valid atau sudah tidak tersedia.',
                ],
                'order' => null,
            ])->toResponse($request));
        }

        $this->expireLatestPayment($order, $lifecycle);
        $order->refresh();
        $this->loadPublicOrder($order);

        return $this->noStoreResponse(Inertia::render('customer/tracking', [
            'access' => ['valid' => true, 'message' => null],
            'order' => (new OrderResource($order))->resolve($request),
            'realtime' => [
                'channel' => OrderStatusUpdated::customerChannel((string) $order->access_token_hash),
                'poll_url' => route('public.orders.show', ['accessToken' => $accessToken]),
                'payment_start_url' => route('public.orders.payment.start', ['accessToken' => $accessToken]),
                'receipt_url' => route('public.order.receipt', ['accessToken' => $accessToken]),
            ],
            'payment' => $this->paymentPayload(
                Payment::withoutGlobalScopes()->where('order_id', $order->getKey())->latest('id')->firstOrFail(),
                $accessToken,
            ),
        ])->toResponse($request));
    }

    public function showJson(
        Request $request,
        string $accessToken,
        PaymentLifecycleService $lifecycle,
    ): JsonResponse {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return $this->noStore(response()->json(['message' => 'Order tidak ditemukan.'], 404));
        }

        $this->expireLatestPayment($order, $lifecycle);
        $order->refresh();
        $this->loadPublicOrder($order);

        return $this->noStore(response()->json([
            'order' => (new OrderResource($order))->resolve($request),
        ]));
    }

    public function paymentStatus(
        string $accessToken,
        PaymentLifecycleService $lifecycle,
    ): JsonResponse {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return $this->noStore(response()->json(['message' => 'Order tidak ditemukan.'], 404));
        }

        $payment = $this->expireLatestPayment($order, $lifecycle);

        return $this->noStore(response()->json($this->paymentPayload($payment, $accessToken)));
    }

    public function startPayment(
        StartGuestPaymentRequest $request,
        string $accessToken,
        PaymentCheckoutService $payments,
        PaymentLifecycleService $lifecycle,
        AnalyticsEventService $analytics,
    ): JsonResponse {
        $order = $this->findOrder($accessToken);

        if ($order === null) {
            return $this->noStore(response()->json(['message' => 'Order tidak ditemukan.'], 404));
        }

        try {
            $paymentMethod = $request->validated('payment_method');
            $paymentMethod = is_string($paymentMethod) ? $paymentMethod : null;
            $payment = $lifecycle->paymentForCheckout(
                $order,
                $paymentMethod,
            );
        } catch (ConflictHttpException $exception) {
            return $this->noStore(response()->json(['message' => $exception->getMessage()], 409));
        }

        try {
            $checkout = $payments->start(
                $payment,
                $order,
                route('public.order', ['accessToken' => $accessToken]),
            );
        } catch (PaymentGatewayException $exception) {
            return $this->noStore(response()->json(['message' => $exception->getMessage()], 503));
        }

        $analytics->record('payment_started', (int) $order->tenant_id, (int) $order->outlet_id, [
            'order_id' => (int) $order->getKey(),
        ]);

        return $this->noStore(response()->json($checkout));
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

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    private function noStoreResponse(HttpResponse $response): HttpResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
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

    private function expireLatestPayment(
        Order $order,
        PaymentLifecycleService $lifecycle,
    ): Payment {
        $payment = Payment::withoutGlobalScopes()
            ->where('order_id', $order->getKey())
            ->latest('id')
            ->firstOrFail();
        $lifecycle->expireIfDue($payment);

        return Payment::withoutGlobalScopes()
            ->whereKey($payment->getKey())
            ->firstOrFail();
    }

    /** @return array{status: string, provider: string|null, redirect_url: string|null, start_url: string, expires_at: string|null} */
    private function paymentPayload(Payment $payment, string $accessToken): array
    {
        $metadata = is_array($payment->metadata) ? $payment->metadata : [];
        $midtrans = is_array($metadata['midtrans'] ?? null) ? $metadata['midtrans'] : [];
        $redirectUrl = $midtrans['redirect_url'] ?? null;

        return [
            'status' => $payment->status->value,
            'provider' => $payment->provider,
            'redirect_url' => $payment->status === PaymentStatus::Pending && is_string($redirectUrl)
                ? $redirectUrl
                : null,
            'start_url' => route('public.orders.payment.start', ['accessToken' => $accessToken]),
            'expires_at' => $payment->expires_at?->toIso8601String(),
        ];
    }
}
