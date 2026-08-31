<?php

namespace App\Http\Controllers;

use App\Services\MidtransSubscriptionWebhookService;
use App\Services\MidtransWebhookService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class MidtransWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransWebhookService $webhooks,
        MidtransSubscriptionWebhookService $subscriptionWebhooks,
        TelemetryService $telemetry,
    ): JsonResponse {
        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:150'],
            'transaction_status' => ['required', 'string', 'max:50'],
            'order_id' => ['required', 'string', 'max:150'],
            'status_code' => ['required', 'string', 'max:3'],
            'gross_amount' => ['required'],
            'signature_key' => ['required', 'string', 'size:128'],
            'transaction_time' => ['required', 'date'],
            'settlement_time' => ['nullable', 'date'],
            'fraud_status' => ['nullable', 'string', 'max:30'],
            'payment_type' => ['nullable', 'string', 'max:50'],
            'refund_amount' => ['nullable', 'regex:/\A\d+(?:\.00)?\z/'],
        ]);

        $isSubscription = str_starts_with((string) $data['order_id'], 'meja-subscription-');
        $startedAt = hrtime(true);

        try {
            $result = $isSubscription
                ? $subscriptionWebhooks->handle($data)
                : $webhooks->handle($data);
        } catch (Throwable $exception) {
            $telemetry->recordDuration('payment.webhook.failed', $startedAt, [
                'provider' => 'midtrans',
                'flow' => $isSubscription ? 'subscription' : 'order',
                'transaction_status' => (string) $data['transaction_status'],
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }

        $telemetry->recordDuration('payment.webhook.completed', $startedAt, [
            'provider' => 'midtrans',
            'flow' => $isSubscription ? 'subscription' : 'order',
            'transaction_status' => (string) $data['transaction_status'],
            'processed' => (bool) ($result['processed'] ?? false),
            'duplicate' => (bool) ($result['duplicate'] ?? false),
        ]);

        return response()->json($result);
    }
}
