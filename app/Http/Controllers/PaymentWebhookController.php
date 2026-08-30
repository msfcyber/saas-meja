<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\HandlePaymentWebhookRequest;
use App\Services\PaymentWebhookService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Throwable;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        HandlePaymentWebhookRequest $request,
        string $provider,
        PaymentWebhookService $webhooks,
        TelemetryService $telemetry,
    ): JsonResponse {
        if (! preg_match('/\A[a-z0-9_-]{1,50}\z/', $provider)) {
            throw ValidationException::withMessages(['provider' => 'Provider payment tidak valid.']);
        }

        $data = $request->validated();
        $startedAt = hrtime(true);

        try {
            $result = $webhooks->handle($provider, $data);
        } catch (Throwable $exception) {
            $telemetry->recordDuration('payment.webhook.failed', $startedAt, [
                'provider' => $provider,
                'event_type' => (string) $data['event_type'],
                'exception' => $exception::class,
            ], 'warning');

            throw $exception;
        }

        $telemetry->recordDuration('payment.webhook.completed', $startedAt, [
            'provider' => $provider,
            'event_type' => (string) $data['event_type'],
            'processed' => $result['processed'],
            'duplicate' => $result['duplicate'],
        ]);

        return response()->json($result);
    }
}
