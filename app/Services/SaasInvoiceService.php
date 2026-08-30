<?php

namespace App\Services;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaasInvoiceService
{
    public function __construct(private readonly AuditLogService $audits) {}

    public function pendingFor(Subscription $subscription): SaasInvoice
    {
        return DB::transaction(function () use ($subscription): SaasInvoice {
            $lockedSubscription = Subscription::withoutGlobalScopes()
                ->whereKey($subscription->getKey())
                ->where('tenant_id', $subscription->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $existing = SaasInvoice::withoutGlobalScopes()
                ->where('tenant_id', $lockedSubscription->tenant_id)
                ->where('subscription_id', $lockedSubscription->getKey())
                ->where('status', SaasInvoiceStatus::Pending)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $lockedSubscription->loadMissing('plan');
            $plan = $lockedSubscription->plan;
            $startedAt = now();
            $periodStartsAt = $lockedSubscription->current_period_ends_at?->isFuture()
                ? $lockedSubscription->current_period_ends_at
                : $startedAt;
            $provider = (string) config('payments.default_provider', 'midtrans');
            $invoice = SaasInvoice::withoutGlobalScopes()->create([
                'tenant_id' => $lockedSubscription->tenant_id,
                'subscription_id' => $lockedSubscription->getKey(),
                'invoice_number' => 'INV-PENDING-'.strtoupper(Str::random(20)),
                'status' => SaasInvoiceStatus::Pending,
                'amount' => (int) $plan->price,
                'currency' => (string) $plan->currency,
                'provider' => $provider,
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodStartsAt->addMonth(),
                'due_at' => $startedAt->addDay(),
                'metadata' => ['source' => 'subscription_checkout'],
            ]);

            $invoice->update([
                'invoice_number' => 'INV-'.str_pad((string) $invoice->getKey(), 8, '0', STR_PAD_LEFT),
                'provider_reference' => 'meja-subscription-'.$invoice->getKey(),
            ]);

            $this->audits->record('subscription.invoice_created', [
                'tenant_id' => (int) $invoice->tenant_id,
                'auditable_type' => SaasInvoice::class,
                'auditable_id' => (int) $invoice->getKey(),
                'new_values' => [
                    'invoice_number' => $invoice->invoice_number,
                    'status' => $invoice->status->value,
                    'amount' => (int) $invoice->amount,
                    'currency' => (string) $invoice->currency,
                ],
            ]);

            return $invoice->fresh();
        }, attempts: 3);
    }
}
