<?php

namespace App\Http\Controllers;

use App\Services\MidtransWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransWebhookController extends Controller
{
    public function __invoke(Request $request, MidtransWebhookService $webhooks): JsonResponse
    {
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

        return response()->json($webhooks->handle($data));
    }
}
