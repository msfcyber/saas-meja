<?php

namespace App\Http\Controllers;

use App\Models\SaasInvoice;
use App\Services\PaymentGatewayException;
use App\Services\SubscriptionCheckoutService;
use App\Services\SubscriptionEntitlementService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function index(TenantContext $context, SubscriptionEntitlementService $entitlements): Response
    {
        $tenant = $context->tenantOrFail();
        $invoices = SaasInvoice::query()
            ->with('subscription.plan')
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('subscription', [
            'subscription' => $entitlements->summary($tenant),
            'invoices' => $invoices->map(fn (SaasInvoice $invoice): array => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => $invoice->status->value,
                'amount' => $invoice->amount,
                'currency' => $invoice->currency,
                'due_at' => $invoice->due_at?->toIso8601String(),
                'paid_at' => $invoice->paid_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function checkout(
        Request $request,
        TenantContext $context,
        SubscriptionCheckoutService $checkout,
    ): JsonResponse|RedirectResponse {
        try {
            $result = $checkout->start($context->tenantOrFail(), route('subscription'));
        } catch (PaymentGatewayException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 503);
            }

            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json($result);
        }

        Inertia::flash('subscription_checkout', $result);

        return back()->with('success', 'Sesi pembayaran subscription siap.');
    }
}
