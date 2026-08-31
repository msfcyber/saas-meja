<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SaasInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Http\Requests\Platform\UpdateSubscriptionRequest;
use App\Http\Requests\Platform\UpdateTenantStatusRequest;
use App\Http\Requests\Platform\VoidInvoiceRequest;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformManagementController extends Controller
{
    public function storePlan(UpdatePlanRequest $request, AuditLogService $audits): RedirectResponse
    {
        $plan = DB::transaction(function () use ($request, $audits): Plan {
            $plan = Plan::query()->create($request->validated());

            $audits->record('platform.plan.created', [
                'auditable_type' => Plan::class,
                'auditable_id' => (int) $plan->getKey(),
                'new_values' => $this->planAuditValues($plan),
            ]);

            return $plan;
        });

        return to_route('platform.dashboard')->with('success', "Plan {$plan->name} berhasil dibuat.");
    }

    public function updatePlan(
        UpdatePlanRequest $request,
        Plan $plan,
        AuditLogService $audits,
    ): RedirectResponse {
        $oldValues = $this->planAuditValues($plan);
        $plan->update($request->validated());
        $plan->refresh();

        $audits->record('platform.plan.updated', [
            'auditable_type' => Plan::class,
            'auditable_id' => (int) $plan->getKey(),
            'old_values' => $oldValues,
            'new_values' => $this->planAuditValues($plan),
        ]);

        return to_route('platform.dashboard')->with('success', "Plan {$plan->name} berhasil diperbarui.");
    }

    public function updateTenantStatus(
        UpdateTenantStatusRequest $request,
        Tenant $tenant,
        AuditLogService $audits,
    ): RedirectResponse {
        $oldStatus = $tenant->status->value;
        $status = $request->validated('status');

        if ($oldStatus === $status) {
            throw ValidationException::withMessages([
                'status' => 'Status tenant tidak berubah.',
            ]);
        }

        $tenant->update(['status' => $status]);
        $audits->record('platform.tenant.status_updated', [
            'tenant_id' => (int) $tenant->getKey(),
            'auditable_type' => Tenant::class,
            'auditable_id' => (int) $tenant->getKey(),
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $tenant->fresh()->status->value],
        ]);

        return to_route('platform.dashboard')->with('success', "Status tenant {$tenant->name} berhasil diperbarui.");
    }

    public function updateSubscription(
        UpdateSubscriptionRequest $request,
        int $subscription,
        AuditLogService $audits,
    ): RedirectResponse {
        $subscriptionModel = Subscription::withoutGlobalScopes()->findOrFail($subscription);
        $oldValues = [
            'plan_id' => (int) $subscriptionModel->plan_id,
            'status' => $subscriptionModel->status->value,
        ];
        $subscriptionModel->update($request->validated());
        $subscriptionModel->refresh();

        $audits->record('platform.subscription.updated', [
            'tenant_id' => (int) $subscriptionModel->tenant_id,
            'auditable_type' => Subscription::class,
            'auditable_id' => (int) $subscriptionModel->getKey(),
            'old_values' => $oldValues,
            'new_values' => [
                'plan_id' => (int) $subscriptionModel->plan_id,
                'status' => $subscriptionModel->status->value,
            ],
        ]);

        return to_route('platform.dashboard')->with('success', 'Subscription berhasil diperbarui.');
    }

    public function voidInvoice(
        VoidInvoiceRequest $request,
        int $invoice,
        AuditLogService $audits,
    ): RedirectResponse {
        $invoiceModel = SaasInvoice::withoutGlobalScopes()->findOrFail($invoice);

        if ($invoiceModel->status !== SaasInvoiceStatus::Pending) {
            throw ValidationException::withMessages([
                'invoice' => 'Hanya invoice pending yang dapat dibatalkan.',
            ]);
        }

        DB::transaction(function () use ($invoiceModel, $audits): void {
            $invoiceModel->update(['status' => SaasInvoiceStatus::Void]);
            $audits->record('platform.invoice.voided', [
                'tenant_id' => (int) $invoiceModel->tenant_id,
                'auditable_type' => SaasInvoice::class,
                'auditable_id' => (int) $invoiceModel->getKey(),
                'old_values' => ['status' => SaasInvoiceStatus::Pending->value],
                'new_values' => ['status' => SaasInvoiceStatus::Void->value],
            ]);
        });

        return to_route('platform.dashboard')->with('success', 'Invoice berhasil dibatalkan.');
    }

    /** @return array<string, mixed> */
    private function planAuditValues(Plan $plan): array
    {
        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'price' => (int) $plan->price,
            'currency' => $plan->currency,
            'billing_interval' => $plan->billing_interval,
            'limits' => $plan->limits,
            'features' => $plan->features,
            'is_active' => (bool) $plan->is_active,
            'position' => (int) $plan->position,
        ];
    }
}
