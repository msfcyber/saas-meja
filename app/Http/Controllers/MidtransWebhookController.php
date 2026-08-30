<?php

namespace App\Http\Controllers;

use App\Services\MidtransSubscriptionWebhookService;
use App\Services\MidtransWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MidtransWebhookService $webhooks,
        MidtransSubscriptionWebhookService $subscriptionWebhooks,
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
        ]);

        if (str_starts_with((string) $data['order_id'], 'meja-subscription-')) {
            return response()->json($subscriptionWebhooks->handle($data));
        }

        return response()->json($webhooks->handle($data));
    }
}
