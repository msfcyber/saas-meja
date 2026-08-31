import { Head, router, useForm } from '@inertiajs/react';
import {
    Activity,
    Building2,
    CheckCircle2,
    CreditCard,
    FileClock,
    Pencil,
    Plus,
    ReceiptText,
    ShieldCheck,
    Store,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Overview = {
    tenants: number;
    active_tenants: number;
    suspended_tenants: number;
    subscriptions: number;
    active_subscriptions: number;
    trialing_subscriptions: number;
    past_due_subscriptions: number;
    pending_invoices: number;
    audit_events_24h: number;
    payment_events_24h: number;
};

type Plan = {
    id: number;
    name: string;
    code: string;
    price: number;
    currency: string;
    billing_interval: string;
    is_active: boolean;
    description: string | null;
    limits: Record<string, number | null>;
    features: string[] | null;
    position: number;
    subscribers: number;
};

type Tenant = {
    id: number;
    name: string;
    slug: string;
    status: string;
    timezone: string;
    outlets: number;
    subscription: {
        status: string;
        plan: string | null;
        period_ends_at: string | null;
    } | null;
};

type AuditLog = {
    id: number;
    event: string;
    tenant: string;
    actor: string;
    resource: string | null;
    created_at: string | null;
};

type PaymentEvent = {
    id: number;
    tenant: string;
    provider: string;
    event_type: string;
    amount: number;
    currency: string;
    processed: boolean;
    occurred_at: string | null;
};

type PlatformSubscription = {
    id: number;
    tenant: string;
    tenant_id: number;
    plan_id: number;
    plan: string | null;
    status: string;
    period_ends_at: string | null;
};

type PlatformInvoice = {
    id: number;
    invoice_number: string;
    tenant: string;
    subscription: string | null;
    status: string;
    amount: number;
    currency: string;
    due_at: string | null;
    paid_at: string | null;
};

type AnalyticsSummary = {
    period_days: number;
    events: Array<{ event: string; total: number }>;
};

type PlanForm = {
    code: string;
    name: string;
    description: string;
    price: number;
    currency: string;
    billing_interval: 'monthly' | 'yearly';
    limits: {
        outlets: number;
        active_tables: number;
        staff: number;
    };
    features: string[];
    is_active: boolean;
    position: number;
};

type Props = {
    overview: Overview;
    plans: Plan[];
    tenants: Tenant[];
    subscriptions: PlatformSubscription[];
    invoices: PlatformInvoice[];
    analytics: AnalyticsSummary;
    audit_logs: AuditLog[];
    payment_events: PaymentEvent[];
};

const emptyPlanForm: PlanForm = {
    code: '',
    name: '',
    description: '',
    price: 0,
    currency: 'IDR',
    billing_interval: 'monthly',
    limits: {
        outlets: 3,
        active_tables: 100,
        staff: 10,
    },
    features: [],
    is_active: true,
    position: 0,
};

const subscriptionStatuses = [
    'trialing',
    'active',
    'past_due',
    'suspended',
    'expired',
    'cancelled',
] as const;

const funnelEvents = [
    'qr_opened',
    'add_to_cart',
    'checkout_started',
    'payment_started',
    'payment_paid',
    'order_completed',
] as const;

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(value: string | null): string {
    if (!value) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusLabel(value: string): string {
    return (
        {
            active: 'Aktif',
            trialing: 'Trial',
            past_due: 'Past due',
            suspended: 'Ditangguhkan',
            inactive: 'Tidak aktif',
            expired: 'Berakhir',
            cancelled: 'Dibatalkan',
        }[value] ?? value
    );
}

function PlatformSubscriptionEditor({
    subscription,
    plans,
}: {
    subscription: PlatformSubscription;
    plans: Plan[];
}) {
    const form = useForm({
        plan_id: subscription.plan_id,
        status: subscription.status,
    });

    function submit(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(`/platform/subscriptions/${subscription.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-end"
        >
            <div className="min-w-0 flex-1">
                <p className="font-semibold">{subscription.tenant}</p>
                <p className="text-muted-foreground mt-1 text-xs">
                    Berakhir {formatDate(subscription.period_ends_at)}
                </p>
            </div>
            <label className="grid gap-1.5 text-xs font-semibold">
                Plan
                <select
                    value={form.data.plan_id}
                    onChange={(event) =>
                        form.setData('plan_id', Number(event.target.value))
                    }
                    className="bg-background h-9 min-w-36 rounded-md border px-3 text-sm font-normal"
                >
                    {plans.map((plan) => (
                        <option key={plan.id} value={plan.id}>
                            {plan.name}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-1.5 text-xs font-semibold">
                Status
                <select
                    value={form.data.status}
                    onChange={(event) =>
                        form.setData('status', event.target.value)
                    }
                    className="bg-background h-9 min-w-32 rounded-md border px-3 text-sm font-normal"
                >
                    {subscriptionStatuses.map((status) => (
                        <option key={status} value={status}>
                            {statusLabel(status)}
                        </option>
                    ))}
                </select>
            </label>
            <Button type="submit" size="sm" disabled={form.processing}>
                Simpan
            </Button>
        </form>
    );
}

function statusClass(value: string): string {
    if (value === 'active' || value === 'trialing') {
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    }

    if (value === 'past_due' || value === 'suspended') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    return 'bg-muted text-muted-foreground';
}

export default function PlatformDashboard({
    overview,
    plans,
    tenants,
    subscriptions,
    invoices,
    analytics,
    audit_logs: auditLogs,
    payment_events: paymentEvents,
}: Props) {
    const [planDialogOpen, setPlanDialogOpen] = useState(false);
    const [editingPlan, setEditingPlan] = useState<Plan | null>(null);
    const planForm = useForm<PlanForm>(emptyPlanForm);
    const metrics = [
        {
            label: 'Tenant aktif',
            value: overview.active_tenants,
            detail: `${overview.tenants} tenant terdaftar`,
            icon: Building2,
        },
        {
            label: 'Subscription aktif',
            value: overview.active_subscriptions,
            detail: `${overview.trialing_subscriptions} sedang trial`,
            icon: CreditCard,
        },
        {
            label: 'Invoice pending',
            value: overview.pending_invoices,
            detail: `${overview.past_due_subscriptions} subscription past due`,
            icon: ReceiptText,
        },
        {
            label: 'Aktivitas 24 jam',
            value: overview.audit_events_24h,
            detail: `${overview.payment_events_24h} payment event`,
            icon: Activity,
        },
    ];

    function openCreatePlan() {
        setEditingPlan(null);
        planForm.setData(emptyPlanForm);
        planForm.clearErrors();
        setPlanDialogOpen(true);
    }

    function openEditPlan(plan: Plan) {
        setEditingPlan(plan);
        planForm.setData({
            code: plan.code,
            name: plan.name,
            description: plan.description ?? '',
            price: plan.price,
            currency: plan.currency,
            billing_interval:
                plan.billing_interval === 'yearly' ? 'yearly' : 'monthly',
            limits: {
                outlets: plan.limits.outlets ?? -1,
                active_tables: plan.limits.active_tables ?? -1,
                staff: plan.limits.staff ?? -1,
            },
            features: plan.features ?? [],
            is_active: plan.is_active,
            position: plan.position,
        });
        planForm.clearErrors();
        setPlanDialogOpen(true);
    }

    function closePlanDialog() {
        setPlanDialogOpen(false);
        setEditingPlan(null);
        planForm.reset();
        planForm.clearErrors();
    }

    function submitPlan(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: closePlanDialog,
        };

        if (editingPlan) {
            planForm.patch(`/platform/plans/${editingPlan.id}`, options);
        } else {
            planForm.post('/platform/plans', options);
        }
    }

    function updateTenantStatus(tenant: Tenant, status: string) {
        if (tenant.status === status) {
            return;
        }

        router.patch(
            `/platform/tenants/${tenant.id}/status`,
            { status },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Platform" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Platform control
                        </p>
                        <h1 className="font-display mt-2 max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">
                            Satu cockpit untuk kesehatan Meja.
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm leading-6">
                            Pantau tenant, subscription, billing, dan event
                            penting tanpa masuk ke konteks operasional tenant
                            tertentu.
                        </p>
                    </div>
                    <div className="bg-card flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold">
                        <ShieldCheck
                            className="text-primary size-4"
                            aria-hidden="true"
                        />
                        Akses platform aktif
                    </div>
                </header>

                <section
                    aria-label="Ringkasan platform"
                    className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {metrics.map((metric) => (
                        <article
                            key={metric.label}
                            className="bg-card rounded-[1.3rem] border p-5"
                        >
                            <span className="bg-secondary text-primary flex size-10 items-center justify-center rounded-xl">
                                <metric.icon
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                            <p className="text-muted-foreground mt-5 text-sm">
                                {metric.label}
                            </p>
                            <p className="font-display mt-1 text-2xl font-bold tracking-tight">
                                {metric.value}
                            </p>
                            <p className="text-muted-foreground mt-1 text-xs">
                                {metric.detail}
                            </p>
                        </article>
                    ))}
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Plan & subscription
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Distribusi plan dan status langganan seluruh
                                    platform.
                                </p>
                            </div>
                            <Button size="sm" onClick={openCreatePlan}>
                                <Plus aria-hidden="true" />
                                Plan baru
                            </Button>
                        </div>
                        <div className="mt-7 grid gap-3">
                            {plans.length === 0 ? (
                                <p className="text-muted-foreground rounded-2xl border border-dashed p-6 text-sm">
                                    Belum ada plan yang terdaftar.
                                </p>
                            ) : (
                                plans.map((plan) => (
                                    <div
                                        key={plan.id}
                                        className="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="bg-secondary text-primary flex size-9 items-center justify-center rounded-xl">
                                                <Store
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <div>
                                                <p className="font-semibold">
                                                    {plan.name}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {plan.code} ·{' '}
                                                    {plan.subscribers}{' '}
                                                    subscription
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-4 sm:justify-end">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-bold ${plan.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                                            >
                                                {plan.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                            <span className="text-sm font-bold">
                                                {formatMoney(
                                                    plan.price,
                                                    plan.currency,
                                                )}{' '}
                                                / {plan.billing_interval}
                                            </span>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    openEditPlan(plan)
                                                }
                                            >
                                                <Pencil aria-hidden="true" />
                                                Edit
                                            </Button>
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </section>

                    <section className="rounded-[1.5rem] border bg-[#293025] p-6 text-[#fffaf0] shadow-[0_20px_60px_-35px_rgba(41,48,37,0.8)] sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-[#dfa281] uppercase">
                                    Guardrail
                                </p>
                                <h2 className="font-display mt-2 text-xl font-bold">
                                    Sinyal yang perlu dilihat.
                                </h2>
                            </div>
                            <FileClock
                                className="size-6 text-[#dfa281]"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="mt-8 grid gap-5">
                            <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                                <span className="text-sm text-[#d8d6c8]">
                                    Total subscription
                                </span>
                                <strong className="text-2xl">
                                    {overview.subscriptions}
                                </strong>
                            </div>
                            <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                                <span className="text-sm text-[#d8d6c8]">
                                    Tenant ditangguhkan
                                </span>
                                <strong className="text-2xl">
                                    {overview.suspended_tenants}
                                </strong>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-[#d8d6c8]">
                                    Payment event 24 jam
                                </span>
                                <strong className="text-2xl">
                                    {overview.payment_events_24h}
                                </strong>
                            </div>
                        </div>
                        <p className="mt-8 text-sm leading-6 text-[#eee9dc]">
                            Dashboard ini bersifat observasi. Perubahan tenant
                            dan billing tetap harus melalui flow terotorisasi
                            masing-masing domain.
                        </p>
                    </section>
                </div>

                <section className="bg-card mt-5 rounded-[1.5rem] border p-6 sm:p-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="font-display text-xl font-bold">
                                Tenant terbaru
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                Maksimal 12 tenant terakhir dan status
                                subscription terkini.
                            </p>
                        </div>
                        <Building2
                            className="text-primary size-6"
                            aria-hidden="true"
                        />
                    </div>
                    {tenants.length === 0 ? (
                        <p className="text-muted-foreground mt-7 rounded-2xl border border-dashed p-6 text-sm">
                            Belum ada tenant.
                        </p>
                    ) : (
                        <div className="mt-7 overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="text-muted-foreground border-b text-xs">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="pb-3 font-semibold"
                                        >
                                            Tenant
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-3 font-semibold"
                                        >
                                            Status
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-3 font-semibold"
                                        >
                                            Subscription
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-3 font-semibold"
                                        >
                                            Outlet
                                        </th>
                                        <th
                                            scope="col"
                                            className="pb-3 font-semibold"
                                        >
                                            Periode
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {tenants.map((tenant) => (
                                        <tr key={tenant.id}>
                                            <td className="py-4">
                                                <p className="font-bold">
                                                    {tenant.name}
                                                </p>
                                                <p className="text-muted-foreground mt-1 text-xs">
                                                    {tenant.slug} ·{' '}
                                                    {tenant.timezone}
                                                </p>
                                            </td>
                                            <td className="py-4">
                                                <label className="grid gap-1.5">
                                                    <span className="sr-only">
                                                        Status {tenant.name}
                                                    </span>
                                                    <select
                                                        value={tenant.status}
                                                        onChange={(event) =>
                                                            updateTenantStatus(
                                                                tenant,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className={`h-8 rounded-full border-0 px-3 text-xs font-bold outline-none ${statusClass(tenant.status)}`}
                                                    >
                                                        <option value="active">
                                                            Aktif
                                                        </option>
                                                        <option value="suspended">
                                                            Ditangguhkan
                                                        </option>
                                                        <option value="inactive">
                                                            Tidak aktif
                                                        </option>
                                                    </select>
                                                </label>
                                            </td>
                                            <td className="py-4">
                                                {tenant.subscription ? (
                                                    <div className="grid gap-1">
                                                        <span
                                                            className={`w-fit rounded-full px-3 py-1 text-xs font-bold ${statusClass(tenant.subscription.status)}`}
                                                        >
                                                            {statusLabel(
                                                                tenant
                                                                    .subscription
                                                                    .status,
                                                            )}
                                                        </span>
                                                        <span className="text-muted-foreground text-xs">
                                                            {tenant.subscription
                                                                .plan ??
                                                                'Tanpa plan'}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Belum ada
                                                    </span>
                                                )}
                                            </td>
                                            <td className="text-muted-foreground py-4">
                                                {tenant.outlets}
                                            </td>
                                            <td className="text-muted-foreground py-4">
                                                {tenant.subscription
                                                    ?.period_ends_at
                                                    ? formatDate(
                                                          tenant.subscription
                                                              .period_ends_at,
                                                      )
                                                    : '-'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-2">
                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Kelola subscription
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Ubah plan atau status subscription secara
                                    ter-audit.
                                </p>
                            </div>
                            <CreditCard
                                className="text-primary size-6"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="mt-7 grid gap-3">
                            {subscriptions.length === 0 ? (
                                <p className="text-muted-foreground rounded-2xl border border-dashed p-6 text-sm">
                                    Belum ada subscription.
                                </p>
                            ) : (
                                subscriptions.map((subscription) => (
                                    <PlatformSubscriptionEditor
                                        key={subscription.id}
                                        subscription={subscription}
                                        plans={plans}
                                    />
                                ))
                            )}
                        </div>
                    </section>

                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Invoice SaaS
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Riwayat invoice lintas tenant dan status
                                    pembayaran terbaru.
                                </p>
                            </div>
                            <ReceiptText
                                className="text-primary size-6"
                                aria-hidden="true"
                            />
                        </div>
                        <div className="mt-7 overflow-x-auto">
                            {invoices.length === 0 ? (
                                <p className="text-muted-foreground rounded-2xl border border-dashed p-6 text-sm">
                                    Belum ada invoice.
                                </p>
                            ) : (
                                <table className="w-full min-w-[620px] text-left text-sm">
                                    <thead className="text-muted-foreground border-b text-xs">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="pb-3 font-semibold"
                                            >
                                                Invoice
                                            </th>
                                            <th
                                                scope="col"
                                                className="pb-3 font-semibold"
                                            >
                                                Tenant
                                            </th>
                                            <th
                                                scope="col"
                                                className="pb-3 font-semibold"
                                            >
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                className="pb-3 text-right font-semibold"
                                            >
                                                Nilai
                                            </th>
                                            <th
                                                scope="col"
                                                className="pb-3 text-right font-semibold"
                                            >
                                                Aksi
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {invoices.map((invoice) => (
                                            <tr key={invoice.id}>
                                                <td className="py-4">
                                                    <p className="font-semibold">
                                                        {invoice.invoice_number}
                                                    </p>
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        Jatuh tempo{' '}
                                                        {formatDate(
                                                            invoice.due_at,
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="text-muted-foreground py-4">
                                                    {invoice.tenant}
                                                </td>
                                                <td className="py-4">
                                                    <span
                                                        className={`rounded-full px-3 py-1 text-xs font-bold ${statusClass(invoice.status)}`}
                                                    >
                                                        {statusLabel(
                                                            invoice.status,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="py-4 text-right font-bold">
                                                    {formatMoney(
                                                        invoice.amount,
                                                        invoice.currency,
                                                    )}
                                                </td>
                                                <td className="py-4 text-right">
                                                    {invoice.status ===
                                                        'pending' && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        `Batalkan invoice ${invoice.invoice_number}?`,
                                                                    )
                                                                ) {
                                                                    router.patch(
                                                                        `/platform/invoices/${invoice.id}/void`,
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    );
                                                                }
                                                            }}
                                                        >
                                                            Batalkan
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </section>
                </div>

                <section className="bg-card mt-5 rounded-[1.5rem] border p-6 sm:p-8">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                Funnel analytics
                            </p>
                            <h2 className="font-display mt-2 text-xl font-bold">
                                Perjalanan pelanggan {analytics.period_days}{' '}
                                hari terakhir.
                            </h2>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Data dianonimkan dan hanya menghitung event
                            allowlist.
                        </p>
                    </div>
                    <div className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        {funnelEvents.map((event) => {
                            const total =
                                analytics.events.find(
                                    (item) => item.event === event,
                                )?.total ?? 0;

                            return (
                                <div
                                    key={event}
                                    className="bg-secondary/60 rounded-2xl p-4"
                                >
                                    <p className="text-muted-foreground text-xs font-semibold">
                                        {event.replaceAll('_', ' ')}
                                    </p>
                                    <p className="font-display mt-3 text-2xl font-bold">
                                        {total}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-2">
                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Audit terbaru
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Event penting dengan metadata payload tetap
                                    dirahasiakan.
                                </p>
                            </div>
                            <CheckCircle2
                                className="text-primary size-6"
                                aria-hidden="true"
                            />
                        </div>
                        {auditLogs.length === 0 ? (
                            <p className="text-muted-foreground mt-7 text-sm">
                                Belum ada audit event.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4">
                                {auditLogs.map((audit) => (
                                    <div
                                        key={audit.id}
                                        className="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                                    >
                                        <span className="bg-primary mt-1 size-2 shrink-0 rounded-full" />
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {audit.event}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {audit.tenant} · {audit.actor}
                                                {audit.resource
                                                    ? ` · ${audit.resource}`
                                                    : ''}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {formatDate(audit.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Payment events
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Event gateway terakhir dan status
                                    pemrosesannya.
                                </p>
                            </div>
                            <Activity
                                className="text-primary size-6"
                                aria-hidden="true"
                            />
                        </div>
                        {paymentEvents.length === 0 ? (
                            <p className="text-muted-foreground mt-7 text-sm">
                                Belum ada payment event.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4">
                                {paymentEvents.map((event) => (
                                    <div
                                        key={event.id}
                                        className="flex items-start justify-between gap-4 border-b pb-4 last:border-0 last:pb-0"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-semibold">
                                                {event.event_type}
                                            </p>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {event.tenant} ·{' '}
                                                {event.provider} ·{' '}
                                                {formatDate(event.occurred_at)}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-bold">
                                                {formatMoney(
                                                    event.amount,
                                                    event.currency,
                                                )}
                                            </p>
                                            <p
                                                className={`mt-1 text-xs font-semibold ${event.processed ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'}`}
                                            >
                                                {event.processed
                                                    ? 'Diproses'
                                                    : 'Menunggu'}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            <Dialog
                open={planDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closePlanDialog();
                    }
                }}
            >
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {editingPlan ? 'Edit plan' : 'Plan baru'}
                        </DialogTitle>
                        <DialogDescription>
                            Harga dan limit menjadi sumber kebenaran untuk
                            entitlement tenant.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitPlan} className="grid gap-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="plan-code">Kode plan</Label>
                                <Input
                                    id="plan-code"
                                    value={planForm.data.code}
                                    onChange={(event) =>
                                        planForm.setData(
                                            'code',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="starter"
                                    required
                                />
                                <InputError message={planForm.errors.code} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-name">Nama plan</Label>
                                <Input
                                    id="plan-name"
                                    value={planForm.data.name}
                                    onChange={(event) =>
                                        planForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Starter"
                                    required
                                />
                                <InputError message={planForm.errors.name} />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="plan-description">Deskripsi</Label>
                            <textarea
                                id="plan-description"
                                value={planForm.data.description}
                                onChange={(event) =>
                                    planForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                className="focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                            />
                            <InputError message={planForm.errors.description} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div className="grid gap-2">
                                <Label htmlFor="plan-price">Harga</Label>
                                <Input
                                    id="plan-price"
                                    type="number"
                                    min="0"
                                    value={planForm.data.price}
                                    onChange={(event) =>
                                        planForm.setData(
                                            'price',
                                            Number(event.target.value),
                                        )
                                    }
                                    required
                                />
                                <InputError message={planForm.errors.price} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-currency">Mata uang</Label>
                                <Input
                                    id="plan-currency"
                                    maxLength={3}
                                    value={planForm.data.currency}
                                    onChange={(event) =>
                                        planForm.setData(
                                            'currency',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    required
                                />
                                <InputError
                                    message={planForm.errors.currency}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="plan-interval">Interval</Label>
                                <select
                                    id="plan-interval"
                                    value={planForm.data.billing_interval}
                                    onChange={(event) =>
                                        planForm.setData(
                                            'billing_interval',
                                            event.target
                                                .value as PlanForm['billing_interval'],
                                        )
                                    }
                                    className="h-9 rounded-md border bg-transparent px-3 text-sm"
                                >
                                    <option value="monthly">Bulanan</option>
                                    <option value="yearly">Tahunan</option>
                                </select>
                                <InputError
                                    message={planForm.errors.billing_interval}
                                />
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(
                                [
                                    ['outlets', 'Limit outlet'],
                                    ['active_tables', 'Limit meja'],
                                    ['staff', 'Limit staf'],
                                ] as const
                            ).map(([key, label]) => (
                                <div key={key} className="grid gap-2">
                                    <Label htmlFor={`plan-limit-${key}`}>
                                        {label}
                                    </Label>
                                    <Input
                                        id={`plan-limit-${key}`}
                                        type="number"
                                        min="-1"
                                        value={planForm.data.limits[key]}
                                        onChange={(event) =>
                                            planForm.setData('limits', {
                                                ...planForm.data.limits,
                                                [key]: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                        required
                                    />
                                    <InputError
                                        message={
                                            planForm.errors[`limits.${key}`]
                                        }
                                    />
                                </div>
                            ))}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="plan-features">
                                Fitur, satu baris per item
                            </Label>
                            <textarea
                                id="plan-features"
                                value={planForm.data.features.join('\n')}
                                onChange={(event) =>
                                    planForm.setData(
                                        'features',
                                        event.target.value
                                            .split(/\r?\n/)
                                            .map((feature) => feature.trim())
                                            .filter(Boolean),
                                    )
                                }
                                className="focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                            />
                            <InputError message={planForm.errors.features} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closePlanDialog}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                disabled={planForm.processing}
                            >
                                {planForm.processing
                                    ? 'Menyimpan...'
                                    : editingPlan
                                      ? 'Simpan perubahan'
                                      : 'Buat plan'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

PlatformDashboard.layout = {
    breadcrumbs: [{ title: 'Platform', href: '/platform' }],
};
