<?php

namespace App\Http\Controllers\Platform;

use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\Outlet;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AnalyticsEventService;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    public function __invoke(): Response
    {
        $tenants = Tenant::query()
            ->latest('id')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'status', 'timezone', 'created_at']);
        $tenantIds = $tenants->modelKeys();

        $latestSubscriptions = Subscription::withoutGlobalScopes()
            ->with('plan')
            ->whereIn('tenant_id', $tenantIds)
            ->latest('id')
            ->get()
            ->unique('tenant_id')
            ->keyBy('tenant_id');
        $outletCounts = Outlet::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
        $planSubscriberCounts = Subscription::withoutGlobalScopes()
            ->selectRaw('plan_id, count(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');
        $activeSubscriptionQuery = Subscription::withoutGlobalScopes()
            ->where('status', SubscriptionStatus::Active->value);
        $auditSince = now()->subDay();

        $recentAudits = AuditLog::withoutGlobalScopes()
            ->with(['tenant', 'actor'])
            ->latest('id')
            ->limit(10)
            ->get();
        $recentPaymentEvents = PaymentEvent::withoutGlobalScopes()
            ->with('tenant')
            ->latest('occurred_at')
            ->latest('id')
            ->limit(8)
            ->get();
        $subscriptions = Subscription::withoutGlobalScopes()
            ->with(['tenant', 'plan'])
            ->latest('id')
            ->limit(20)
            ->get();
        $invoices = SaasInvoice::withoutGlobalScopes()
            ->with(['tenant', 'subscription.plan'])
            ->latest('id')
            ->limit(20)
            ->get();
        $analyticsSince = now()->subDays(30);
        $analyticsCounts = AnalyticsEvent::query()
            ->where('occurred_at', '>=', $analyticsSince)
            ->whereIn('event', AnalyticsEventService::EVENTS)
            ->selectRaw('event, count(*) as total')
            ->groupBy('event')
            ->pluck('total', 'event');

        return Inertia::render('platform/dashboard', [
            'overview' => [
                'tenants' => Tenant::query()->count(),
                'active_tenants' => Tenant::query()->where('status', TenantStatus::Active->value)->count(),
                'suspended_tenants' => Tenant::query()->where('status', TenantStatus::Suspended->value)->count(),
                'subscriptions' => Subscription::withoutGlobalScopes()->count(),
                'active_subscriptions' => (clone $activeSubscriptionQuery)->count(),
                'trialing_subscriptions' => Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::Trialing->value)->count(),
                'past_due_subscriptions' => Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::PastDue->value)->count(),
                'pending_invoices' => SaasInvoice::withoutGlobalScopes()->where('status', SaasInvoiceStatus::Pending->value)->count(),
                'audit_events_24h' => AuditLog::withoutGlobalScopes()->where('created_at', '>=', $auditSince)->count(),
                'payment_events_24h' => PaymentEvent::withoutGlobalScopes()->where('occurred_at', '>=', $auditSince)->count(),
            ],
            'plans' => Plan::query()
                ->orderByDesc('is_active')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'billing_interval' => $plan->billing_interval,
                    'is_active' => $plan->is_active,
                    'description' => $plan->description,
                    'limits' => $plan->limits,
                    'features' => $plan->features,
                    'position' => $plan->position,
                    'subscribers' => (int) ($planSubscriberCounts[$plan->id] ?? 0),
                ])
                ->values(),
            'tenants' => $tenants->map(function (Tenant $tenant) use ($latestSubscriptions, $outletCounts): array {
                $subscription = $latestSubscriptions->get($tenant->id);

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->value,
                    'timezone' => $tenant->timezone,
                    'outlets' => (int) ($outletCounts[$tenant->id] ?? 0),
                    'subscription' => $subscription === null ? null : [
                        'status' => $subscription->status->value,
                        'plan' => $subscription->plan?->name,
                        'period_ends_at' => ($subscription->current_period_ends_at ?? $subscription->trial_ends_at)?->toIso8601String(),
                    ],
                ];
            })->values(),
            'subscriptions' => $subscriptions->map(function (Subscription $subscription): array {
                $tenant = $subscription->getRelationValue('tenant');
                $plan = $subscription->getRelationValue('plan');

                return [
                    'id' => $subscription->id,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Tenant tidak tersedia',
                    'tenant_id' => $subscription->tenant_id,
                    'plan_id' => $subscription->plan_id,
                    'plan' => $plan instanceof Plan ? $plan->name : null,
                    'status' => $subscription->status->value,
                    'period_ends_at' => ($subscription->current_period_ends_at ?? $subscription->trial_ends_at)?->toIso8601String(),
                ];
            })->values(),
            'invoices' => $invoices->map(function (SaasInvoice $invoice): array {
                $tenant = $invoice->getRelationValue('tenant');
                $subscription = $invoice->getRelationValue('subscription');

                return [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Tenant tidak tersedia',
                    'subscription' => $subscription?->plan?->name,
                    'status' => $invoice->status->value,
                    'amount' => $invoice->amount,
                    'currency' => $invoice->currency,
                    'due_at' => $invoice->due_at?->toIso8601String(),
                    'paid_at' => $invoice->paid_at?->toIso8601String(),
                ];
            })->values(),
            'analytics' => [
                'period_days' => 30,
                'events' => collect(AnalyticsEventService::EVENTS)->map(fn (string $event): array => [
                    'event' => $event,
                    'total' => (int) ($analyticsCounts[$event] ?? 0),
                ])->values(),
            ],
            'audit_logs' => $recentAudits->map(function (AuditLog $audit): array {
                $tenant = $audit->getRelationValue('tenant');
                $actor = $audit->getRelationValue('actor');

                return [
                    'id' => $audit->id,
                    'event' => $audit->event,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Platform',
                    'actor' => $actor instanceof User
                        ? $actor->name
                        : ($audit->actor_type === 'system' ? 'Sistem' : 'Pengguna'),
                    'resource' => $audit->auditable_type === null
                        ? null
                        : class_basename($audit->auditable_type).($audit->auditable_id === null ? '' : ' #'.$audit->auditable_id),
                    'created_at' => $audit->created_at?->toIso8601String(),
                ];
            })->values(),
            'payment_events' => $recentPaymentEvents->map(function (PaymentEvent $event): array {
                $tenant = $event->getRelationValue('tenant');

                return [
                    'id' => $event->id,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Tenant tidak tersedia',
                    'provider' => $event->provider,
                    'event_type' => $event->event_type,
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    'processed' => $event->processed_at !== null,
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ];
            })->values(),
        ]);
    }
}
