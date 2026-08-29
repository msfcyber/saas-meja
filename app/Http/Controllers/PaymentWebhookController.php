<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\HandlePaymentWebhookRequest;
use App\Services\PaymentWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PaymentWebhookController extends Controller
{
    public function __invoke(
        HandlePaymentWebhookRequest $request,
        string $provider,
        PaymentWebhookService $webhooks,
    ): JsonResponse {
        if (! preg_match('/\A[a-z0-9_-]{1,50}\z/', $provider)) {
            throw ValidationException::withMessages(['provider' => 'Provider payment tidak valid.']);
        }

        return response()->json($webhooks->handle($provider, $request->validated()));
    }
}
