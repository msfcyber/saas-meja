<?php

namespace App\Http\Controllers\Platform;

use App\Enums\PaymentStatus;
use App\Enums\SaasInvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Plan;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AnalyticsEventService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlatformDashboardController extends Controller
{
    private const TENANTS_PER_PAGE = 12;

    private const PAYMENT_EVENTS_PER_PAGE = 12;

    private const AUDITS_PER_PAGE = 12;

    public function __invoke(Request $request): Response
    {
        $tenantSearch = trim((string) $request->query('tenant_search', ''));
        $tenantStatus = (string) $request->query('tenant_status', 'all');
        $tenantStatuses = array_map(
            static fn (TenantStatus $status): string => $status->value,
            TenantStatus::cases(),
        );

        if (! in_array($tenantStatus, ['all', ...$tenantStatuses], true)) {
            $tenantStatus = 'all';
        }

        $tenantQuery = Tenant::query()
            ->with(['users' => function ($query): void {
                $query
                    ->where('tenant_user.status', 'active')
                    ->where('tenant_user.is_owner', true)
                    ->select(['users.id', 'users.name', 'users.email']);
            }])
            ->when($tenantSearch !== '', function (Builder $query) use ($tenantSearch): void {
                $like = '%'.$tenantSearch.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->where('name', 'like', $like)
                        ->orWhere('slug', 'like', $like)
                        ->orWhereHas('users', function (Builder $query) use ($like): void {
                            $query
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            ->when($tenantStatus !== 'all', fn (Builder $query) => $query->where('status', $tenantStatus));
        $tenantTotal = (clone $tenantQuery)->count();
        $tenantPage = $this->boundedPage(
            $request,
            'tenant_page',
            $tenantTotal,
            self::TENANTS_PER_PAGE,
        );
        $tenants = $tenantQuery
            ->latest('id')
            ->forPage($tenantPage, self::TENANTS_PER_PAGE)
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
        $activeOutletCounts = Outlet::withoutGlobalScopes()
            ->whereIn('tenant_id', $tenantIds)
            ->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
        $activeMemberCounts = DB::table('tenant_user')
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', 'active')
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

        $auditSearch = trim((string) $request->query('audit_search', ''));
        $auditQuery = AuditLog::withoutGlobalScopes()
            ->with(['tenant', 'actor'])
            ->when($auditSearch !== '', function (Builder $query) use ($auditSearch): void {
                $like = '%'.$auditSearch.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->where('event', 'like', $like)
                        ->orWhere('auditable_type', 'like', $like)
                        ->orWhere('request_id', 'like', $like)
                        ->orWhereHas('tenant', fn (Builder $query) => $query->where('name', 'like', $like));
                });
            });
        $auditTotal = (clone $auditQuery)->count();
        $auditPage = $this->boundedPage($request, 'audit_page', $auditTotal, self::AUDITS_PER_PAGE);
        $recentAudits = $auditQuery
            ->latest('id')
            ->forPage($auditPage, self::AUDITS_PER_PAGE)
            ->get();

        $paymentEventSearch = trim((string) $request->query('payment_event_search', ''));
        $paymentEventStatus = (string) $request->query('payment_event_status', 'all');

        if (! in_array($paymentEventStatus, ['all', 'processed', 'pending'], true)) {
            $paymentEventStatus = 'all';
        }

        $paymentEventQuery = PaymentEvent::withoutGlobalScopes()
            ->with([
                'tenant',
                'outlet' => fn ($query) => $query->withoutGlobalScopes(),
                'payment' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->with(['order' => fn ($query) => $query->withoutGlobalScopes()]),
            ])
            ->when($paymentEventSearch !== '', function (Builder $query) use ($paymentEventSearch): void {
                $like = '%'.$paymentEventSearch.'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query
                        ->where('provider', 'like', $like)
                        ->orWhere('event_id', 'like', $like)
                        ->orWhere('event_type', 'like', $like);
                });
            })
            ->when($paymentEventStatus === 'processed', fn (Builder $query) => $query->whereNotNull('processed_at'))
            ->when($paymentEventStatus === 'pending', fn (Builder $query) => $query->whereNull('processed_at'));
        $paymentEventTotal = (clone $paymentEventQuery)->count();
        $paymentEventPage = $this->boundedPage(
            $request,
            'payment_event_page',
            $paymentEventTotal,
            self::PAYMENT_EVENTS_PER_PAGE,
        );
        $recentPaymentEvents = $paymentEventQuery
            ->latest('occurred_at')
            ->latest('id')
            ->forPage($paymentEventPage, self::PAYMENT_EVENTS_PER_PAGE)
            ->get();
        $pendingPayments = Payment::withoutGlobalScopes()
            ->with([
                'tenant',
                'outlet' => fn ($query) => $query->withoutGlobalScopes(),
                'order' => fn ($query) => $query->withoutGlobalScopes(),
            ])
            ->where('status', PaymentStatus::Pending)
            ->whereNotNull('provider_reference')
            ->latest('updated_at')
            ->limit(12)
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
                'pending_payments' => Payment::withoutGlobalScopes()
                    ->where('status', PaymentStatus::Pending)
                    ->whereNotNull('provider_reference')
                    ->count(),
                'unprocessed_payment_events' => PaymentEvent::withoutGlobalScopes()->whereNull('processed_at')->count(),
                'audit_events_24h' => AuditLog::withoutGlobalScopes()->where('created_at', '>=', $auditSince)->count(),
                'payment_events_24h' => PaymentEvent::withoutGlobalScopes()->where('occurred_at', '>=', $auditSince)->count(),
            ],
            'filters' => [
                'tenant_search' => $tenantSearch,
                'tenant_status' => $tenantStatus,
                'payment_event_search' => $paymentEventSearch,
                'payment_event_status' => $paymentEventStatus,
                'audit_search' => $auditSearch,
            ],
            'tenant_pagination' => $this->pagination($tenantPage, self::TENANTS_PER_PAGE, $tenantTotal),
            'payment_event_pagination' => $this->pagination(
                $paymentEventPage,
                self::PAYMENT_EVENTS_PER_PAGE,
                $paymentEventTotal,
            ),
            'audit_pagination' => $this->pagination($auditPage, self::AUDITS_PER_PAGE, $auditTotal),
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
            'tenants' => $tenants->map(function (Tenant $tenant) use ($activeMemberCounts, $activeOutletCounts, $latestSubscriptions, $outletCounts): array {
                $subscription = $latestSubscriptions->get($tenant->id);
                $owner = $tenant->users->first();

                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status->value,
                    'timezone' => $tenant->timezone,
                    'created_at' => $tenant->created_at?->toIso8601String(),
                    'outlets' => (int) ($outletCounts[$tenant->id] ?? 0),
                    'active_outlets' => (int) ($activeOutletCounts[$tenant->id] ?? 0),
                    'active_members' => (int) ($activeMemberCounts[$tenant->id] ?? 0),
                    'owner' => $owner instanceof User ? [
                        'name' => $owner->name,
                        'email' => $owner->email,
                    ] : null,
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
                    'request_id' => $audit->request_id,
                    'created_at' => $audit->created_at?->toIso8601String(),
                ];
            })->values(),
            'payment_events' => $recentPaymentEvents->map(function (PaymentEvent $event): array {
                $tenant = $event->getRelationValue('tenant');
                $outlet = $event->getRelationValue('outlet');
                $payment = $event->getRelationValue('payment');
                $order = $payment instanceof Payment ? $payment->getRelationValue('order') : null;

                return [
                    'id' => $event->id,
                    'event_id' => $event->event_id,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Tenant tidak tersedia',
                    'outlet' => $outlet instanceof Outlet ? $outlet->name : null,
                    'provider' => $event->provider,
                    'event_type' => $event->event_type,
                    'order_number' => $order instanceof Order ? $order->order_number : null,
                    'payment_status' => $payment instanceof Payment ? $payment->status->value : null,
                    'amount' => $event->amount,
                    'currency' => $event->currency,
                    'processed' => $event->processed_at !== null,
                    'processed_at' => $event->processed_at?->toIso8601String(),
                    'occurred_at' => $event->occurred_at->toIso8601String(),
                ];
            })->values(),
            'pending_payments' => $pendingPayments->map(function (Payment $payment): array {
                $tenant = $payment->getRelationValue('tenant');
                $outlet = $payment->getRelationValue('outlet');
                $order = $payment->getRelationValue('order');

                return [
                    'id' => $payment->id,
                    'tenant' => $tenant instanceof Tenant ? $tenant->name : 'Tenant tidak tersedia',
                    'outlet' => $outlet instanceof Outlet ? $outlet->name : null,
                    'order_number' => $order instanceof Order ? $order->order_number : null,
                    'provider' => $payment->provider,
                    'provider_reference' => $payment->provider_reference,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'last_webhook_at' => $payment->last_webhook_at?->toIso8601String(),
                    'created_at' => $payment->created_at?->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    private function boundedPage(Request $request, string $key, int $total, int $perPage): int
    {
        $requested = filter_var($request->query($key, 1), FILTER_VALIDATE_INT);
        $page = $requested === false ? 1 : max(1, (int) $requested);
        $lastPage = max(1, (int) ceil($total / $perPage));

        return min($page, $lastPage);
    }

    /** @return array{page: int, per_page: int, total: int, last_page: int} */
    private function pagination(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }
}
