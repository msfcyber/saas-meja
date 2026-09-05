<?php

namespace App\Http\Controllers;

use App\Http\Requests\Payment\StorePaymentRefundRequest;
use App\Models\Order;
use App\Services\PaymentGatewayException;
use App\Services\PaymentRefundService;
use Illuminate\Http\RedirectResponse;

class PaymentRefundController extends Controller
{
    public function store(
        StorePaymentRefundRequest $request,
        Order $order,
        PaymentRefundService $refunds,
    ): RedirectResponse {
        $this->authorize('refund', $order);
        $actorId = $request->user()?->getAuthIdentifier();

        try {
            $refunds->refund(
                $order,
                (string) $request->validated('idempotency_key'),
                (string) $request->validated('reason'),
                is_numeric($actorId) ? (int) $actorId : null,
                $request->integer('amount') ?: null,
            );
        } catch (PaymentGatewayException $exception) {
            return back()->withErrors(['refund' => $exception->getMessage()]);
        }

        return to_route('orders')->with('success', 'Refund berhasil dikirim ke gateway pembayaran.');
    }
}
