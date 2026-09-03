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
            $now = now();
            $overdueInvoices = SaasInvoice::withoutGlobalScopes()
                ->where('tenant_id', $lockedSubscription->tenant_id)
                ->where('subscription_id', $lockedSubscription->getKey())
                ->where('status', SaasInvoiceStatus::Pending)
                ->whereNotNull('due_at')
                ->where('due_at', '<=', $now)
                ->lockForUpdate()
                ->get();

            foreach ($overdueInvoices as $invoice) {
                $this->expire($invoice);
            }

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
            $startedAt = $now;
            $periodStartsAt = $lockedSubscription->current_period_ends_at?->isFuture()
                ? $lockedSubscription->current_period_ends_at
                : $startedAt;
            $periodEndsAt = $plan->billing_interval === 'yearly'
                ? $periodStartsAt->addYear()
                : $periodStartsAt->addMonth();
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
                'period_ends_at' => $periodEndsAt,
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

    public function expireDue(int $limit = 100): int
    {
        $invoiceIds = SaasInvoice::withoutGlobalScopes()
            ->where('status', SaasInvoiceStatus::Pending)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->orderBy('id')
            ->limit(max(1, min(500, $limit)))
            ->pluck('id');
        $expired = 0;

        foreach ($invoiceIds as $invoiceId) {
            $didExpire = DB::transaction(function () use ($invoiceId): bool {
                $invoice = SaasInvoice::withoutGlobalScopes()
                    ->whereKey($invoiceId)
                    ->lockForUpdate()
                    ->first();

                if ($invoice === null
                    || $invoice->status !== SaasInvoiceStatus::Pending
                    || $invoice->due_at === null
                    || $invoice->due_at->isFuture()) {
                    return false;
                }

                $this->expire($invoice);

                return true;
            }, attempts: 3);

            $expired += $didExpire ? 1 : 0;
        }

        return $expired;
    }

    private function expire(SaasInvoice $invoice): void
    {
        $invoice->update(['status' => SaasInvoiceStatus::Expired]);
        $this->audits->record('subscription.invoice_expired', [
            'tenant_id' => (int) $invoice->tenant_id,
            'actor_type' => 'system',
            'auditable_type' => SaasInvoice::class,
            'auditable_id' => (int) $invoice->getKey(),
            'old_values' => ['status' => SaasInvoiceStatus::Pending->value],
            'new_values' => ['status' => SaasInvoiceStatus::Expired->value],
        ]);
    }
}
