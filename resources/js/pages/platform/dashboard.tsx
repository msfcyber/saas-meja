import { Head } from "@inertiajs/react";
import {
    Activity,
    Building2,
    CheckCircle2,
    CreditCard,
    FileClock,
    ReceiptText,
    ShieldCheck,
    Store,
} from "lucide-react";

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

type Props = {
    overview: Overview;
    plans: Plan[];
    tenants: Tenant[];
    audit_logs: AuditLog[];
    payment_events: PaymentEvent[];
};

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(value: string | null): string {
    if (!value) {
        return "-";
    }

    return new Intl.DateTimeFormat("id-ID", {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value));
}

function statusLabel(value: string): string {
    return (
        {
            active: "Aktif",
            trialing: "Trial",
            past_due: "Past due",
            suspended: "Ditangguhkan",
            inactive: "Tidak aktif",
            expired: "Berakhir",
            cancelled: "Dibatalkan",
        }[value] ?? value
    );
}

function statusClass(value: string): string {
    if (value === "active" || value === "trialing") {
        return "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300";
    }

    if (value === "past_due" || value === "suspended") {
        return "bg-amber-500/10 text-amber-700 dark:text-amber-300";
    }

    return "bg-muted text-muted-foreground";
}

export default function PlatformDashboard({
    overview,
    plans,
    tenants,
    audit_logs: auditLogs,
    payment_events: paymentEvents,
}: Props) {
    const metrics = [
        {
            label: "Tenant aktif",
            value: overview.active_tenants,
            detail: `${overview.tenants} tenant terdaftar`,
            icon: Building2,
        },
        {
            label: "Subscription aktif",
            value: overview.active_subscriptions,
            detail: `${overview.trialing_subscriptions} sedang trial`,
            icon: CreditCard,
        },
        {
            label: "Invoice pending",
            value: overview.pending_invoices,
            detail: `${overview.past_due_subscriptions} subscription past due`,
            icon: ReceiptText,
        },
        {
            label: "Aktivitas 24 jam",
            value: overview.audit_events_24h,
            detail: `${overview.payment_events_24h} payment event`,
            icon: Activity,
        },
    ];

    return (
        <>
            <Head title="Platform" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                            Platform control
                        </p>
                        <h1 className="font-display mt-2 max-w-3xl text-3xl font-bold tracking-tight sm:text-4xl">
                            Satu cockpit untuk kesehatan Meja.
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground">
                            Pantau tenant, subscription, billing, dan event penting tanpa masuk ke
                            konteks operasional tenant tertentu.
                        </p>
                    </div>
                    <div className="flex min-h-11 items-center gap-2 rounded-full border bg-card px-4 text-sm font-bold">
                        <ShieldCheck className="size-4 text-primary" aria-hidden="true" />
                        Akses platform aktif
                    </div>
                </header>

                <section
                    aria-label="Ringkasan platform"
                    className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {metrics.map((metric) => (
                        <article key={metric.label} className="rounded-[1.3rem] border bg-card p-5">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                                <metric.icon className="size-5" aria-hidden="true" />
                            </span>
                            <p className="mt-5 text-sm text-muted-foreground">{metric.label}</p>
                            <p className="font-display mt-1 text-2xl font-bold tracking-tight">
                                {metric.value}
                            </p>
                            <p className="mt-1 text-xs text-muted-foreground">{metric.detail}</p>
                        </article>
                    ))}
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Plan & subscription
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Distribusi plan dan status langganan seluruh platform.
                                </p>
                            </div>
                            <CreditCard className="size-6 text-primary" aria-hidden="true" />
                        </div>
                        <div className="mt-7 grid gap-3">
                            {plans.length === 0 ? (
                                <p className="rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                                    Belum ada plan yang terdaftar.
                                </p>
                            ) : (
                                plans.map((plan) => (
                                    <div
                                        key={plan.id}
                                        className="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-9 items-center justify-center rounded-xl bg-secondary text-primary">
                                                <Store className="size-4" aria-hidden="true" />
                                            </span>
                                            <div>
                                                <p className="font-semibold">{plan.name}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {plan.code} · {plan.subscribers} subscription
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between gap-4 sm:justify-end">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-bold ${plan.is_active ? "bg-emerald-500/10 text-emerald-700 dark:text-emerald-300" : "bg-muted text-muted-foreground"}`}
                                            >
                                                {plan.is_active ? "Aktif" : "Nonaktif"}
                                            </span>
                                            <span className="text-sm font-bold">
                                                {formatMoney(plan.price, plan.currency)} /{" "}
                                                {plan.billing_interval}
                                            </span>
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
                            <FileClock className="size-6 text-[#dfa281]" aria-hidden="true" />
                        </div>
                        <div className="mt-8 grid gap-5">
                            <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                                <span className="text-sm text-[#d8d6c8]">Total subscription</span>
                                <strong className="text-2xl">{overview.subscriptions}</strong>
                            </div>
                            <div className="flex items-center justify-between gap-4 border-b border-white/10 pb-4">
                                <span className="text-sm text-[#d8d6c8]">Tenant ditangguhkan</span>
                                <strong className="text-2xl">{overview.suspended_tenants}</strong>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <span className="text-sm text-[#d8d6c8]">Payment event 24 jam</span>
                                <strong className="text-2xl">{overview.payment_events_24h}</strong>
                            </div>
                        </div>
                        <p className="mt-8 text-sm leading-6 text-[#eee9dc]">
                            Dashboard ini bersifat observasi. Perubahan tenant dan billing tetap
                            harus melalui flow terotorisasi masing-masing domain.
                        </p>
                    </section>
                </div>

                <section className="mt-5 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="font-display text-xl font-bold">Tenant terbaru</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Maksimal 12 tenant terakhir dan status subscription terkini.
                            </p>
                        </div>
                        <Building2 className="size-6 text-primary" aria-hidden="true" />
                    </div>
                    {tenants.length === 0 ? (
                        <p className="mt-7 rounded-2xl border border-dashed p-6 text-sm text-muted-foreground">
                            Belum ada tenant.
                        </p>
                    ) : (
                        <div className="mt-7 overflow-x-auto">
                            <table className="w-full min-w-[760px] text-left text-sm">
                                <thead className="border-b text-xs text-muted-foreground">
                                    <tr>
                                        <th scope="col" className="pb-3 font-semibold">
                                            Tenant
                                        </th>
                                        <th scope="col" className="pb-3 font-semibold">
                                            Status
                                        </th>
                                        <th scope="col" className="pb-3 font-semibold">
                                            Subscription
                                        </th>
                                        <th scope="col" className="pb-3 font-semibold">
                                            Outlet
                                        </th>
                                        <th scope="col" className="pb-3 font-semibold">
                                            Periode
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {tenants.map((tenant) => (
                                        <tr key={tenant.id}>
                                            <td className="py-4">
                                                <p className="font-bold">{tenant.name}</p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {tenant.slug} · {tenant.timezone}
                                                </p>
                                            </td>
                                            <td className="py-4">
                                                <span
                                                    className={`rounded-full px-3 py-1 text-xs font-bold ${statusClass(tenant.status)}`}
                                                >
                                                    {statusLabel(tenant.status)}
                                                </span>
                                            </td>
                                            <td className="py-4">
                                                {tenant.subscription ? (
                                                    <div className="grid gap-1">
                                                        <span
                                                            className={`w-fit rounded-full px-3 py-1 text-xs font-bold ${statusClass(tenant.subscription.status)}`}
                                                        >
                                                            {statusLabel(
                                                                tenant.subscription.status,
                                                            )}
                                                        </span>
                                                        <span className="text-xs text-muted-foreground">
                                                            {tenant.subscription.plan ??
                                                                "Tanpa plan"}
                                                        </span>
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        Belum ada
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-4 text-muted-foreground">
                                                {tenant.outlets}
                                            </td>
                                            <td className="py-4 text-muted-foreground">
                                                {tenant.subscription?.period_ends_at
                                                    ? formatDate(tenant.subscription.period_ends_at)
                                                    : "-"}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-2">
                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">Audit terbaru</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Event penting dengan metadata payload tetap dirahasiakan.
                                </p>
                            </div>
                            <CheckCircle2 className="size-6 text-primary" aria-hidden="true" />
                        </div>
                        {auditLogs.length === 0 ? (
                            <p className="mt-7 text-sm text-muted-foreground">
                                Belum ada audit event.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4">
                                {auditLogs.map((audit) => (
                                    <div
                                        key={audit.id}
                                        className="flex gap-3 border-b pb-4 last:border-0 last:pb-0"
                                    >
                                        <span className="mt-1 size-2 shrink-0 rounded-full bg-primary" />
                                        <div className="min-w-0">
                                            <p className="font-semibold">{audit.event}</p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {audit.tenant} · {audit.actor}
                                                {audit.resource ? ` · ${audit.resource}` : ""}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatDate(audit.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">Payment events</h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Event gateway terakhir dan status pemrosesannya.
                                </p>
                            </div>
                            <Activity className="size-6 text-primary" aria-hidden="true" />
                        </div>
                        {paymentEvents.length === 0 ? (
                            <p className="mt-7 text-sm text-muted-foreground">
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
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {event.tenant} · {event.provider} ·{" "}
                                                {formatDate(event.occurred_at)}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right">
                                            <p className="text-sm font-bold">
                                                {formatMoney(event.amount, event.currency)}
                                            </p>
                                            <p
                                                className={`mt-1 text-xs font-semibold ${event.processed ? "text-emerald-700 dark:text-emerald-300" : "text-amber-700 dark:text-amber-300"}`}
                                            >
                                                {event.processed ? "Diproses" : "Menunggu"}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

PlatformDashboard.layout = { breadcrumbs: [{ title: "Platform", href: "/platform" }] };
