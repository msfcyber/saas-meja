import { Head, router } from '@inertiajs/react';
import {
    BarChart3,
    CalendarRange,
    CircleAlert,
    CircleCheck,
    CreditCard,
    ReceiptText,
    TrendingUp,
    Trophy,
} from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Outlet = { id: number; name: string; code: string };
type Summary = {
    orders: number;
    gross_sales: number;
    average_order: number;
    refunded_orders: number;
    refunded_amount: number;
};
type PaymentMethod = { method: string; orders: number; amount: number };
type DailySale = { date: string; orders: number; amount: number };
type TopProduct = { name: string; quantity: number; amount: number };
type Transaction = {
    order_number: string;
    outlet: string | null;
    status: string;
    payment_method: string | null;
    amount: number;
    paid_at: string | null;
};
type Props = {
    filters: { from: string; to: string; outlet: number | null };
    outlets: Outlet[];
    summary: Summary;
    payment_methods: PaymentMethod[];
    daily_sales: DailySale[];
    top_products: TopProduct[];
    transactions: Transaction[];
};

function formatMoney(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
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

function formatDay(value: string): string {
    return new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
    }).format(new Date(`${value}T00:00:00`));
}

function formatRange(from: string, to: string): string {
    const formatter = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
    const fromDate = formatter.format(new Date(`${from}T00:00:00`));
    const toDate = formatter.format(new Date(`${to}T00:00:00`));

    return from === to ? fromDate : `${fromDate} - ${toDate}`;
}

function formatCompactMoney(amount: number): string {
    return `Rp ${new Intl.NumberFormat('id-ID', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(amount)}`;
}

function formatPaymentMethod(method: string): string {
    return method.replace(/_/g, ' ');
}

function statusTone(status: string): string {
    const normalized = status.toLowerCase();

    if (
        normalized.includes('refund') ||
        normalized.includes('batal') ||
        normalized.includes('gagal')
    ) {
        return 'bg-destructive/10 text-destructive';
    }

    if (
        normalized.includes('selesai') ||
        normalized.includes('completed') ||
        normalized.includes('paid') ||
        normalized.includes('bayar')
    ) {
        return 'bg-accent text-accent-foreground';
    }

    return 'bg-secondary text-secondary-foreground';
}

export default function SalesReport({
    filters,
    outlets,
    summary,
    payment_methods: paymentMethods,
    daily_sales: dailySales,
    top_products: topProducts,
    transactions,
}: Props) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);
    const [outlet, setOutlet] = useState(filters.outlet?.toString() ?? '');
    const [filterError, setFilterError] = useState<string | null>(null);
    const maxDailyAmount = Math.max(
        ...dailySales.map((sale) => sale.amount),
        1,
    );
    const maxTopProductAmount = Math.max(
        ...topProducts.map((product) => product.amount),
        1,
    );
    const paymentTotal = paymentMethods.reduce(
        (total, payment) => total + payment.amount,
        0,
    );
    const paymentTones = [
        'bg-primary',
        'bg-accent',
        'bg-chart-3',
        'bg-chart-4',
        'bg-chart-5',
    ];

    const chartWidth = 720;
    const chartHeight = 280;
    const chartPadding = { top: 20, right: 18, bottom: 42, left: 66 };
    const chartPlotWidth = chartWidth - chartPadding.left - chartPadding.right;
    const chartPlotHeight =
        chartHeight - chartPadding.top - chartPadding.bottom;
    const chartPoints = dailySales.map((sale, index) => ({
        x:
            chartPadding.left +
            (dailySales.length === 1
                ? chartPlotWidth / 2
                : (index / (dailySales.length - 1)) * chartPlotWidth),
        y:
            chartPadding.top +
            (1 - sale.amount / maxDailyAmount) * chartPlotHeight,
    }));
    const chartLinePath = chartPoints
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`,
        )
        .join(' ');
    const firstChartPoint = chartPoints[0];
    const lastChartPoint = chartPoints[chartPoints.length - 1];
    const chartAreaPath =
        firstChartPoint && lastChartPoint
            ? `${chartLinePath} L ${lastChartPoint.x} ${chartHeight - chartPadding.bottom} L ${firstChartPoint.x} ${chartHeight - chartPadding.bottom} Z`
            : '';
    const chartLabelIndexes =
        dailySales.length > 0
            ? Array.from(
                  new Set([
                      0,
                      Math.floor((dailySales.length - 1) / 2),
                      dailySales.length - 1,
                  ]),
              )
            : [];
    const singleDailySale = dailySales.length === 1 ? dailySales[0] : null;

    function applyFilters(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (from && to && from > to) {
            setFilterError(
                'Tanggal mulai tidak boleh melewati tanggal selesai.',
            );

            return;
        }

        setFilterError(null);
        router.get(
            '/reports/sales',
            { from, to, outlet: outlet || undefined },
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
                onError: (errors) => {
                    setFilterError(
                        Object.values(errors)[0] ??
                            'Filter laporan tidak dapat diterapkan.',
                    );
                },
            },
        );
    }

    const snapshotMetrics = [
        {
            label: 'Order berbayar',
            value: formatNumber(summary.orders),
            detail: 'Pembayaran terverifikasi',
            icon: ReceiptText,
            tone: 'bg-primary/10 text-primary',
        },
        {
            label: 'Rata-rata order',
            value: formatMoney(summary.average_order),
            detail: 'Nilai per transaksi',
            icon: BarChart3,
            tone: 'bg-accent text-accent-foreground',
        },
        {
            label: 'Nilai refund',
            value: formatMoney(summary.refunded_amount),
            detail: `${formatNumber(summary.refunded_orders)} transaksi`,
            icon: CircleAlert,
            tone: 'bg-destructive/10 text-destructive',
        },
        {
            label: 'Kualitas data',
            value: 'Terverifikasi',
            detail: 'Berbasis payment sukses',
            icon: CircleCheck,
            tone: 'bg-secondary text-secondary-foreground',
        },
    ];

    return (
        <>
            <Head title="Laporan penjualan" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div className="max-w-2xl">
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Analitik outlet
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Laporan penjualan
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm sm:text-base">
                            Baca arah pendapatan, kebiasaan pembayaran, dan
                            produk yang paling banyak mendorong omzet.
                        </p>
                    </div>
                    <div className="border-border/70 bg-card flex min-h-12 items-center gap-3 rounded-xl border px-4 shadow-[0_14px_30px_-24px_rgba(53,44,31,0.8)]">
                        <span className="bg-primary/10 text-primary flex size-9 shrink-0 items-center justify-center rounded-lg">
                            <CalendarRange
                                className="size-4"
                                aria-hidden="true"
                            />
                        </span>
                        <div>
                            <p className="text-muted-foreground text-[11px] font-bold tracking-[0.12em] uppercase">
                                Periode aktif
                            </p>
                            <p className="text-sm font-bold">
                                {formatRange(filters.from, filters.to)}
                            </p>
                        </div>
                    </div>
                </header>

                <section
                    aria-labelledby="report-filter-title"
                    className="border-border/70 bg-card mt-8 rounded-[1.5rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] sm:p-6"
                >
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                Kontrol laporan
                            </p>
                            <h2
                                id="report-filter-title"
                                className="font-display mt-2 text-2xl font-bold tracking-tight"
                            >
                                Tentukan konteks angka.
                            </h2>
                        </div>
                        <p className="text-muted-foreground text-sm">
                            Data mengikuti tanggal payment berhasil.
                        </p>
                    </div>

                    <form
                        onSubmit={applyFilters}
                        className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1.25fr_auto] xl:items-end"
                    >
                        <div className="grid gap-2">
                            <label
                                htmlFor="sales-from"
                                className="text-muted-foreground text-xs font-bold"
                            >
                                Mulai
                            </label>
                            <input
                                id="sales-from"
                                type="date"
                                value={from}
                                onChange={(event) => {
                                    setFrom(event.target.value);
                                    setFilterError(null);
                                }}
                                aria-invalid={Boolean(filterError)}
                                aria-describedby={
                                    filterError
                                        ? 'sales-filter-error'
                                        : undefined
                                }
                                className="border-input bg-background text-foreground focus-visible:ring-ring min-h-12 rounded-xl border px-3 text-sm font-semibold transition-shadow outline-none focus-visible:ring-2"
                            />
                        </div>
                        <div className="grid gap-2">
                            <label
                                htmlFor="sales-to"
                                className="text-muted-foreground text-xs font-bold"
                            >
                                Sampai
                            </label>
                            <input
                                id="sales-to"
                                type="date"
                                value={to}
                                onChange={(event) => {
                                    setTo(event.target.value);
                                    setFilterError(null);
                                }}
                                aria-invalid={Boolean(filterError)}
                                aria-describedby={
                                    filterError
                                        ? 'sales-filter-error'
                                        : undefined
                                }
                                className="border-input bg-background text-foreground focus-visible:ring-ring min-h-12 rounded-xl border px-3 text-sm font-semibold transition-shadow outline-none focus-visible:ring-2"
                            />
                        </div>
                        <div className="grid gap-2">
                            <label
                                htmlFor="sales-outlet"
                                className="text-muted-foreground text-xs font-bold"
                            >
                                Outlet
                            </label>
                            <select
                                id="sales-outlet"
                                value={outlet}
                                onChange={(event) => {
                                    setOutlet(event.target.value);
                                    setFilterError(null);
                                }}
                                aria-invalid={Boolean(filterError)}
                                aria-describedby={
                                    filterError
                                        ? 'sales-filter-error'
                                        : undefined
                                }
                                className="border-input bg-background text-foreground focus-visible:ring-ring min-h-12 rounded-xl border px-3 text-sm font-semibold transition-shadow outline-none focus-visible:ring-2"
                            >
                                <option value="">Semua outlet</option>
                                {outlets.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} · {item.code}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            type="submit"
                            className="min-h-12 rounded-xl shadow-[0_14px_30px_-18px_var(--primary)]"
                        >
                            <CalendarRange aria-hidden="true" /> Terapkan
                        </Button>
                        <InputError
                            id="sales-filter-error"
                            message={filterError ?? undefined}
                            className="sm:col-span-full"
                        />
                    </form>
                </section>

                <section
                    aria-labelledby="sales-snapshot-title"
                    className="mt-5 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]"
                >
                    <article className="bg-foreground text-background relative isolate overflow-hidden rounded-[1.75rem] p-6 shadow-[0_28px_70px_-44px_rgba(53,44,31,0.8)] sm:p-8">
                        <div
                            className="border-primary/20 absolute -right-20 -bottom-32 size-80 rounded-full border-[46px]"
                            aria-hidden="true"
                        />
                        <div
                            className="border-primary/15 absolute -top-20 -left-24 size-56 rounded-full border-[30px]"
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                        Snapshot performa
                                    </p>
                                    <h2
                                        id="sales-snapshot-title"
                                        className="font-display mt-2 text-2xl font-bold tracking-tight sm:text-3xl"
                                    >
                                        Pendapatan, terlihat jelas.
                                    </h2>
                                </div>
                                <span className="bg-background/10 text-background/75 inline-flex min-h-9 items-center rounded-full px-3 text-xs font-bold">
                                    {formatRange(filters.from, filters.to)}
                                </span>
                            </div>
                            <p className="text-background/65 mt-3 max-w-md text-sm leading-6">
                                Angka utama dihitung dari order dengan payment
                                berhasil, sehingga snapshot ini siap dipakai
                                untuk mengambil keputusan.
                            </p>

                            <div className="mt-8">
                                <p className="text-background/65 text-xs font-bold tracking-[0.14em] uppercase">
                                    Penjualan kotor
                                </p>
                                <p className="font-display mt-1 text-4xl font-bold tracking-tight sm:text-5xl">
                                    {formatMoney(summary.gross_sales)}
                                </p>
                                <p className="text-background/65 mt-3 flex items-center gap-2 text-sm">
                                    <CircleCheck
                                        className="text-accent size-4"
                                        aria-hidden="true"
                                    />
                                    Berdasarkan pembayaran terverifikasi
                                </p>
                            </div>

                            <dl className="border-background/15 mt-8 grid max-w-xl grid-cols-2 gap-5 border-t pt-5">
                                <div>
                                    <dt className="text-background/65 text-xs font-semibold">
                                        Order berbayar
                                    </dt>
                                    <dd className="mt-1 text-2xl font-bold tabular-nums">
                                        {formatNumber(summary.orders)}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-background/65 text-xs font-semibold">
                                        Rata-rata order
                                    </dt>
                                    <dd className="mt-1 text-2xl font-bold tabular-nums">
                                        {formatMoney(summary.average_order)}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </article>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {snapshotMetrics.map((metric) => (
                            <article
                                key={metric.label}
                                className="border-border/70 bg-card flex min-h-[9.5rem] flex-col justify-between rounded-[1.3rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)]"
                            >
                                <span
                                    className={`inline-flex size-11 items-center justify-center rounded-xl ${metric.tone}`}
                                >
                                    <metric.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <p className="text-muted-foreground text-xs">
                                        {metric.label}
                                    </p>
                                    <p className="font-display mt-1 text-xl font-bold tracking-tight sm:text-2xl">
                                        {metric.value}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {metric.detail}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(18rem,0.55fr)]">
                    <section
                        aria-labelledby="sales-trend-title"
                        className="border-border/70 bg-card rounded-[1.5rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] sm:p-7"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Ritme pendapatan
                                </p>
                                <h2
                                    id="sales-trend-title"
                                    className="font-display mt-2 text-xl font-bold sm:text-2xl"
                                >
                                    Tren penjualan
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Perubahan omzet berdasarkan tanggal payment
                                    berhasil.
                                </p>
                            </div>
                            <span className="bg-secondary text-secondary-foreground inline-flex min-h-9 items-center gap-2 rounded-full px-3 text-xs font-bold">
                                <TrendingUp
                                    className="size-3.5"
                                    aria-hidden="true"
                                />
                                {dailySales.length} hari terukur
                            </span>
                        </div>

                        <div className="bg-muted/40 mt-7 rounded-[1.25rem] p-3 sm:p-5">
                            <div className="text-muted-foreground flex items-center justify-between gap-3 px-1 text-[11px] font-bold tracking-[0.12em] uppercase">
                                <span>Omzet harian</span>
                                <span>IDR</span>
                            </div>

                            {dailySales.length === 0 ? (
                                <div className="border-border/70 bg-card mt-3 flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-5 text-center">
                                    <BarChart3
                                        className="text-muted-foreground size-8"
                                        aria-hidden="true"
                                    />
                                    <p className="mt-3 font-semibold">
                                        Belum ada transaksi pada rentang ini.
                                    </p>
                                    <p className="text-muted-foreground mt-1 max-w-xs text-sm">
                                        Coba pilih rentang tanggal atau outlet
                                        lain untuk melihat pola penjualan.
                                    </p>
                                </div>
                            ) : singleDailySale ? (
                                <div className="border-border/70 bg-card mt-3 flex min-h-64 items-center rounded-xl border px-6 sm:px-10">
                                    <div>
                                        <p className="text-muted-foreground text-xs font-bold tracking-[0.12em] uppercase">
                                            Satu hari dengan penjualan
                                        </p>
                                        <p className="font-display mt-2 text-4xl font-bold tracking-tight">
                                            {formatMoney(
                                                singleDailySale.amount,
                                            )}
                                        </p>
                                        <p className="text-muted-foreground mt-2 text-sm">
                                            {formatDay(singleDailySale.date)} ·{' '}
                                            {formatNumber(
                                                singleDailySale.orders,
                                            )}{' '}
                                            order
                                        </p>
                                    </div>
                                </div>
                            ) : (
                                <svg
                                    className="text-foreground mt-3 h-auto w-full overflow-visible"
                                    viewBox={`0 0 ${chartWidth} ${chartHeight}`}
                                    role="img"
                                    aria-labelledby="sales-chart-title sales-chart-description"
                                >
                                    <title id="sales-chart-title">
                                        Tren penjualan harian
                                    </title>
                                    <desc id="sales-chart-description">
                                        Grafik omzet harian dari{' '}
                                        {formatDay(dailySales[0].date)} sampai{' '}
                                        {formatDay(
                                            dailySales[dailySales.length - 1]
                                                .date,
                                        )}
                                        .
                                    </desc>
                                    {[0, 0.5, 1].map((ratio) => {
                                        const y =
                                            chartPadding.top +
                                            ratio * chartPlotHeight;

                                        return (
                                            <g key={ratio}>
                                                <line
                                                    x1={chartPadding.left}
                                                    x2={
                                                        chartWidth -
                                                        chartPadding.right
                                                    }
                                                    y1={y}
                                                    y2={y}
                                                    stroke="var(--border)"
                                                    strokeOpacity="0.7"
                                                    strokeDasharray="4 6"
                                                />
                                                <text
                                                    x={chartPadding.left - 10}
                                                    y={y + 4}
                                                    textAnchor="end"
                                                    fill="var(--muted-foreground)"
                                                    fontSize="11"
                                                >
                                                    {formatCompactMoney(
                                                        maxDailyAmount *
                                                            (1 - ratio),
                                                    )}
                                                </text>
                                            </g>
                                        );
                                    })}
                                    <path
                                        d={chartAreaPath}
                                        fill="var(--primary)"
                                        fillOpacity="0.12"
                                    />
                                    <path
                                        d={chartLinePath}
                                        fill="none"
                                        stroke="var(--primary)"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="3"
                                    />
                                    {chartPoints.map((point, index) => (
                                        <circle
                                            key={dailySales[index].date}
                                            cx={point.x}
                                            cy={point.y}
                                            r="4"
                                            fill="var(--card)"
                                            stroke="var(--primary)"
                                            strokeWidth="2"
                                        />
                                    ))}
                                    {chartLabelIndexes.map((index) => {
                                        const point = chartPoints[index];
                                        const sale = dailySales[index];

                                        if (!point || !sale) {
                                            return null;
                                        }

                                        return (
                                            <text
                                                key={sale.date}
                                                x={point.x}
                                                y={chartHeight - 10}
                                                textAnchor={
                                                    index === 0
                                                        ? 'start'
                                                        : index ===
                                                            dailySales.length -
                                                                1
                                                          ? 'end'
                                                          : 'middle'
                                                }
                                                fill="var(--muted-foreground)"
                                                fontSize="11"
                                            >
                                                {formatDay(sale.date)}
                                            </text>
                                        );
                                    })}
                                </svg>
                            )}
                        </div>

                        {dailySales.length > 0 && (
                            <div className="border-border/70 mt-6 border-t pt-5">
                                <div className="flex items-end justify-between gap-4">
                                    <div>
                                        <p className="text-primary text-xs font-bold tracking-[0.14em] uppercase">
                                            Data pendukung
                                        </p>
                                        <h3 className="font-display mt-1 text-lg font-bold">
                                            Rincian harian
                                        </h3>
                                    </div>
                                    <span className="text-muted-foreground text-xs font-semibold">
                                        {dailySales.length} baris
                                    </span>
                                </div>
                                <div className="border-border/70 mt-3 max-h-64 overflow-auto rounded-xl border">
                                    <table className="w-full min-w-[420px] text-left text-sm">
                                        <caption className="sr-only">
                                            Rincian tanggal, order, dan omzet
                                            harian.
                                        </caption>
                                        <thead className="bg-card text-muted-foreground sticky top-0 z-10 border-b text-xs">
                                            <tr>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 font-semibold"
                                                >
                                                    Tanggal
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 font-semibold"
                                                >
                                                    Order
                                                </th>
                                                <th
                                                    scope="col"
                                                    className="px-4 py-3 text-right font-semibold"
                                                >
                                                    Omzet
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-border/70 divide-y">
                                            {dailySales.map((sale) => (
                                                <tr key={sale.date}>
                                                    <td className="px-4 py-3 font-semibold">
                                                        {formatDay(sale.date)}
                                                    </td>
                                                    <td className="text-muted-foreground px-4 py-3">
                                                        {formatNumber(
                                                            sale.orders,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-3 text-right font-bold">
                                                        {formatMoney(
                                                            sale.amount,
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="payment-method-title"
                        className="border-border/70 bg-card rounded-[1.5rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] sm:p-7"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Cara bayar
                                </p>
                                <h2
                                    id="payment-method-title"
                                    className="font-display mt-2 text-xl font-bold sm:text-2xl"
                                >
                                    Metode pembayaran
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Kontribusi payment berhasil pada periode
                                    aktif.
                                </p>
                            </div>
                            <span className="bg-secondary text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                                <CreditCard
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                        </div>

                        <div className="bg-secondary/60 mt-6 rounded-2xl p-4">
                            <p className="text-muted-foreground text-xs font-bold tracking-[0.12em] uppercase">
                                Total payment tercatat
                            </p>
                            <p className="font-display mt-1 text-2xl font-bold tracking-tight">
                                {formatMoney(paymentTotal)}
                            </p>
                        </div>

                        {paymentMethods.length === 0 ? (
                            <p className="border-border/70 text-muted-foreground mt-5 rounded-xl border border-dashed p-5 text-sm">
                                Belum ada data payment pada periode ini.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-5">
                                {paymentMethods.map((payment, index) => {
                                    const share =
                                        paymentTotal > 0
                                            ? Math.round(
                                                  (payment.amount /
                                                      paymentTotal) *
                                                      100,
                                              )
                                            : 0;

                                    return (
                                        <div key={payment.method}>
                                            <div className="flex items-start justify-between gap-4">
                                                <div>
                                                    <p className="font-semibold capitalize">
                                                        {formatPaymentMethod(
                                                            payment.method,
                                                        )}
                                                    </p>
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        {formatNumber(
                                                            payment.orders,
                                                        )}{' '}
                                                        order ·{' '}
                                                        {formatMoney(
                                                            payment.amount,
                                                        )}
                                                    </p>
                                                </div>
                                                <p className="text-sm font-bold tabular-nums">
                                                    {share}%
                                                </p>
                                            </div>
                                            <div
                                                className="bg-muted mt-3 h-2 overflow-hidden rounded-full"
                                                role="progressbar"
                                                aria-label={`${formatPaymentMethod(payment.method)} ${share}% dari total payment`}
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                aria-valuenow={share}
                                            >
                                                <div
                                                    className={`h-full rounded-full ${paymentTones[index % paymentTones.length]}`}
                                                    style={{
                                                        width: `${share}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </section>
                </div>

                <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(18rem,0.7fr)_minmax(0,1.3fr)]">
                    <section
                        aria-labelledby="top-products-title"
                        className="border-border/70 bg-card rounded-[1.5rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] sm:p-7"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Kontributor omzet
                                </p>
                                <h2
                                    id="top-products-title"
                                    className="font-display mt-2 text-xl font-bold sm:text-2xl"
                                >
                                    Produk terlaris
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Berdasarkan detail item order yang dibayar.
                                </p>
                            </div>
                            <span className="bg-primary/10 text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                                <Trophy className="size-5" aria-hidden="true" />
                            </span>
                        </div>

                        {topProducts.length === 0 ? (
                            <p className="border-border/70 text-muted-foreground mt-7 rounded-xl border border-dashed p-5 text-sm">
                                Belum ada detail produk pada periode ini.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-5">
                                {topProducts.map((product, index) => (
                                    <div
                                        key={product.name}
                                        className="flex items-start gap-3"
                                    >
                                        <span
                                            className={`flex size-8 shrink-0 items-center justify-center rounded-xl text-xs font-bold ${index === 0 ? 'bg-primary text-primary-foreground' : 'bg-secondary text-primary'}`}
                                        >
                                            {String(index + 1).padStart(2, '0')}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="min-w-0 truncate font-semibold">
                                                    {product.name}
                                                </p>
                                                <p className="shrink-0 text-sm font-bold">
                                                    {formatMoney(
                                                        product.amount,
                                                    )}
                                                </p>
                                            </div>
                                            <p className="text-muted-foreground mt-1 text-xs">
                                                {formatNumber(product.quantity)}{' '}
                                                item terjual
                                            </p>
                                            <div
                                                className="bg-muted mt-3 h-1.5 overflow-hidden rounded-full"
                                                role="progressbar"
                                                aria-label={`${product.name} menyumbang ${formatMoney(product.amount)}`}
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                aria-valuenow={Math.round(
                                                    (product.amount /
                                                        maxTopProductAmount) *
                                                        100,
                                                )}
                                            >
                                                <div
                                                    className="bg-primary h-full rounded-full"
                                                    style={{
                                                        width: `${(product.amount / maxTopProductAmount) * 100}%`,
                                                    }}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section
                        aria-labelledby="transactions-title"
                        className="border-border/70 bg-card rounded-[1.5rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)] sm:p-7"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Audit transaksi
                                </p>
                                <h2
                                    id="transactions-title"
                                    className="font-display mt-2 text-xl font-bold sm:text-2xl"
                                >
                                    Transaksi terbaru
                                </h2>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Maksimal 100 transaksi pada filter aktif.
                                </p>
                            </div>
                            <span className="bg-secondary text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                                <ReceiptText
                                    className="size-5"
                                    aria-hidden="true"
                                />
                            </span>
                        </div>

                        {transactions.length === 0 ? (
                            <p className="border-border/70 text-muted-foreground mt-7 rounded-xl border border-dashed p-5 text-sm">
                                Belum ada transaksi pada periode ini.
                            </p>
                        ) : (
                            <div className="border-border/70 mt-7 overflow-x-auto rounded-xl border">
                                <table className="w-full min-w-[680px] text-left text-sm">
                                    <caption className="sr-only">
                                        Daftar transaksi penjualan terbaru pada
                                        periode aktif.
                                    </caption>
                                    <thead className="bg-muted/50 border-border/70 text-muted-foreground border-b text-xs">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                Order
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                Outlet
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 font-semibold"
                                            >
                                                Payment
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-4 py-3 text-right font-semibold"
                                            >
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-border/70 divide-y">
                                        {transactions.map((transaction) => (
                                            <tr key={transaction.order_number}>
                                                <td className="px-4 py-4 align-top">
                                                    <p className="font-bold">
                                                        {
                                                            transaction.order_number
                                                        }
                                                    </p>
                                                    <div className="mt-2 flex flex-wrap items-center gap-2">
                                                        <span
                                                            className={`inline-flex min-h-6 items-center rounded-full px-2 py-0.5 text-[11px] font-bold ${statusTone(transaction.status)}`}
                                                        >
                                                            {transaction.status}
                                                        </span>
                                                        <span className="text-muted-foreground text-xs">
                                                            {formatDate(
                                                                transaction.paid_at,
                                                            )}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="text-muted-foreground px-4 py-4 align-top">
                                                    {transaction.outlet ?? '-'}
                                                </td>
                                                <td className="text-muted-foreground px-4 py-4 align-top capitalize">
                                                    {transaction.payment_method
                                                        ? formatPaymentMethod(
                                                              transaction.payment_method,
                                                          )
                                                        : '-'}
                                                </td>
                                                <td className="px-4 py-4 text-right align-top font-bold">
                                                    {formatMoney(
                                                        transaction.amount,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

SalesReport.layout = {
    breadcrumbs: [{ title: 'Laporan penjualan', href: '/reports/sales' }],
};
