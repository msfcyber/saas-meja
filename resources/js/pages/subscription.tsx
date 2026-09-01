import { Head, router, useForm } from '@inertiajs/react';
import { CreditCard, Gauge, ReceiptText, Sparkles } from 'lucide-react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Plan = {
    code: string;
    name: string;
    price: number;
    currency: string;
    billing_interval: string;
    limits: Record<string, number | null>;
    features: string[] | null;
};

type SubscriptionSummary = {
    status: string | null;
    trial_ends_at: string | null;
    current_period_ends_at: string | null;
    plan: Plan | null;
    usage: { outlets: number; active_tables: number; staff: number };
    can_accept_orders: boolean;
};

type Invoice = {
    id: number;
    invoice_number: string;
    status: string;
    amount: number;
    currency: string;
    due_at: string | null;
    paid_at: string | null;
};

type Props = {
    subscription: SubscriptionSummary;
    invoices: Invoice[];
};

const labels: Record<string, string> = {
    outlets: 'Outlet aktif',
    active_tables: 'Meja aktif',
    staff: 'Staf aktif',
};

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

    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(value),
    );
}

function statusLabel(status: string | null): string {
    return (
        {
            trialing: 'Masa trial',
            active: 'Aktif',
            past_due: 'Menunggu pembayaran',
            suspended: 'Ditangguhkan',
            expired: 'Berakhir',
            cancelled: 'Dibatalkan',
        }[status ?? ''] ?? 'Belum tersedia'
    );
}

export default function Subscription({ subscription, invoices }: Props) {
    const form = useForm({});
    const paymentError = (
        form.errors as unknown as Record<string, string | undefined>
    ).payment;

    useEffect(() => {
        return router.on('flash', (event) => {
            const checkout = (event as CustomEvent).detail?.flash
                ?.subscription_checkout as
                | { redirect_url?: string }
                | undefined;

            if (checkout?.redirect_url) {
                window.location.assign(checkout.redirect_url);
            }
        });
    }, []);

    const plan = subscription.plan;
    const trialing = subscription.status === 'trialing';

    function startCheckout(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/subscription/checkout', { preserveScroll: true });
    }

    return (
        <>
            <Head title="Subscription" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Langganan bisnis
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Subscription & billing
                        </h1>
                        <p className="text-muted-foreground mt-2 max-w-2xl text-sm">
                            Kelola akses ordering, batas resource, dan
                            pembayaran plan dari satu tempat.
                        </p>
                    </div>
                    <div className="bg-card flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold">
                        <span
                            className={`size-2 rounded-full ${subscription.can_accept_orders ? 'bg-emerald-600' : 'bg-amber-500'}`}
                        />
                        {subscription.can_accept_orders
                            ? 'Ordering aktif'
                            : 'Ordering perlu subscription aktif'}
                    </div>
                </div>

                <div className="mt-8 grid gap-5 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.85fr)]">
                    <section className="rounded-[1.5rem] border bg-[#293025] p-6 text-[#fffaf0] shadow-[0_20px_60px_-35px_rgba(41,48,37,0.8)] sm:p-8">
                        <div className="flex items-start justify-between gap-4">
                            <div className="flex size-12 items-center justify-center rounded-2xl bg-[#dfa281] text-[#293025]">
                                <Sparkles
                                    className="size-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-bold">
                                {statusLabel(subscription.status)}
                            </span>
                        </div>
                        <p className="mt-8 text-sm text-[#d8d6c8]">
                            Plan saat ini
                        </p>
                        <h2 className="font-display mt-1 text-3xl font-bold">
                            {plan?.name ?? 'Belum ada plan'}
                        </h2>
                        {plan && (
                            <p className="mt-2 text-sm text-[#d8d6c8]">
                                {formatMoney(plan.price, plan.currency)} /{' '}
                                {plan.billing_interval}
                            </p>
                        )}
                        <p className="mt-6 max-w-xl text-sm leading-6 text-[#eee9dc]">
                            {trialing
                                ? `Trial berakhir ${formatDate(subscription.trial_ends_at)}. Bayar sekarang untuk mengaktifkan periode berlangganan.`
                                : `Periode aktif berakhir ${formatDate(subscription.current_period_ends_at)}.`}
                        </p>
                        <form onSubmit={startCheckout} className="mt-8">
                            <Button
                                type="submit"
                                disabled={form.processing || plan === null}
                                className="min-h-12 rounded-full bg-[#fffaf0] px-5 font-bold text-[#293025] hover:bg-white"
                                aria-describedby={
                                    paymentError
                                        ? 'subscription-payment-error'
                                        : undefined
                                }
                            >
                                {form.processing && <Spinner />}
                                <CreditCard aria-hidden="true" />
                                {trialing
                                    ? 'Aktifkan plan berbayar'
                                    : 'Bayar / perpanjang'}
                            </Button>
                            <InputError
                                id="subscription-payment-error"
                                className="mt-3 text-[#ffc7b0]"
                                message={paymentError}
                            />
                        </form>
                    </section>

                    <section className="bg-card rounded-[1.5rem] border p-6 sm:p-8">
                        <div className="flex items-center gap-3">
                            <div className="bg-secondary text-primary flex size-11 items-center justify-center rounded-2xl">
                                <Gauge className="size-5" aria-hidden="true" />
                            </div>
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Pemakaian plan
                                </h2>
                                <p className="text-muted-foreground text-sm">
                                    Resource aktif tenant ini
                                </p>
                            </div>
                        </div>
                        <div className="mt-7 grid gap-5">
                            {Object.entries(subscription.usage).map(
                                ([key, value]) => {
                                    const limit = plan?.limits[key] ?? null;
                                    const percentage =
                                        limit && limit > 0
                                            ? Math.min(
                                                  (value / limit) * 100,
                                                  100,
                                              )
                                            : 0;

                                    return (
                                        <div key={key}>
                                            <div className="flex items-center justify-between gap-4 text-sm">
                                                <span className="font-semibold">
                                                    {labels[key] ?? key}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {value} /{' '}
                                                    {limit === null
                                                        ? 'tak terbatas'
                                                        : limit}
                                                </span>
                                            </div>
                                            <div className="bg-secondary mt-2 h-2 overflow-hidden rounded-full">
                                                <div
                                                    {...(limit === null
                                                        ? {}
                                                        : {
                                                              role: 'progressbar',
                                                              'aria-label':
                                                                  labels[key] ??
                                                                  key,
                                                              'aria-valuemin': 0,
                                                              'aria-valuemax':
                                                                  limit,
                                                              'aria-valuenow':
                                                                  Math.min(
                                                                      value,
                                                                      limit,
                                                                  ),
                                                          })}
                                                    className="bg-primary h-full rounded-full transition-[width]"
                                                    style={{
                                                        width: `${percentage}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    );
                                },
                            )}
                        </div>
                    </section>
                </div>

                <section className="bg-card mt-5 rounded-[1.5rem] border p-6 sm:p-8">
                    <div className="flex items-center gap-3">
                        <div className="bg-secondary text-primary flex size-11 items-center justify-center rounded-2xl">
                            <ReceiptText
                                className="size-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div>
                            <h2 className="font-display text-xl font-bold">
                                Invoice
                            </h2>
                            <p className="text-muted-foreground text-sm">
                                Riwayat invoice subscription tenant.
                            </p>
                        </div>
                    </div>
                    {invoices.length === 0 ? (
                        <p className="text-muted-foreground mt-7 rounded-2xl border border-dashed p-6 text-sm">
                            Belum ada invoice subscription.
                        </p>
                    ) : (
                        <div className="mt-7 grid gap-3">
                            {invoices.map((invoice) => (
                                <div
                                    key={invoice.id}
                                    className="flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div>
                                        <p className="font-semibold">
                                            {invoice.invoice_number}
                                        </p>
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            Jatuh tempo{' '}
                                            {formatDate(invoice.due_at)}
                                            {invoice.paid_at &&
                                                ` · dibayar ${formatDate(invoice.paid_at)}`}
                                        </p>
                                    </div>
                                    <div className="flex items-center justify-between gap-4 sm:justify-end">
                                        <span className="text-sm font-bold">
                                            {formatMoney(
                                                invoice.amount,
                                                invoice.currency,
                                            )}
                                        </span>
                                        <span className="bg-secondary rounded-full px-3 py-1 text-xs font-bold capitalize">
                                            {invoice.status}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Subscription.layout = {
    breadcrumbs: [{ title: 'Subscription', href: '/subscription' }],
};
