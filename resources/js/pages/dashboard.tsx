import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    CheckCircle2,
    CircleAlert,
    ClipboardList,
    Clock3,
    PackageOpen,
    QrCode,
    ReceiptText,
    Table2,
    TrendingUp,
    UtensilsCrossed,
} from 'lucide-react';
import { dashboard } from '@/routes';

type Props = {
    outlet: {
        name: string;
        timezone: string;
        currency: string;
        accepts_orders: boolean;
    };
    today: string;
    viewerName: string;
    catalogSummary: {
        products: number;
        available_products: number;
        active_tables: number;
        total_tables: number;
    };
    orderSummary: {
        orders_today: number;
        gross_sales_today: number;
        active_orders: number;
    };
    canViewReports: boolean;
};

function formatMoney(amount: number, currency: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatNumber(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function percentOf(current: number, total: number): number {
    if (total <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((current / total) * 100)));
}

export default function Dashboard({
    outlet,
    today,
    viewerName,
    catalogSummary,
    orderSummary,
    canViewReports,
}: Props) {
    const averageOrder =
        orderSummary.orders_today > 0
            ? orderSummary.gross_sales_today / orderSummary.orders_today
            : 0;
    const menuReadiness = percentOf(
        catalogSummary.available_products,
        catalogSummary.products,
    );
    const tableReadiness = percentOf(
        catalogSummary.active_tables,
        catalogSummary.total_tables,
    );

    const snapshotMetrics = [
        {
            label: 'Order hari ini',
            value: formatNumber(orderSummary.orders_today),
            detail: 'Pembayaran terverifikasi',
            icon: ClipboardList,
        },
        {
            label: 'Menu tersedia',
            value: `${formatNumber(catalogSummary.available_products)} / ${formatNumber(catalogSummary.products)}`,
            detail: 'Produk siap dipesan',
            icon: PackageOpen,
        },
        {
            label: 'Meja aktif',
            value: `${formatNumber(catalogSummary.active_tables)} / ${formatNumber(catalogSummary.total_tables)}`,
            detail: 'Meja yang dapat digunakan',
            icon: Table2,
        },
    ];

    const readiness = [
        {
            label: 'Menu siap dipesan',
            current: catalogSummary.available_products,
            total: catalogSummary.products,
            percent: menuReadiness,
            detail:
                catalogSummary.products > 0
                    ? `${menuReadiness}% produk aktif dan tersedia di menu QR.`
                    : 'Belum ada produk yang tercatat di katalog.',
            icon: PackageOpen,
            fillClass: 'bg-primary',
        },
        {
            label: 'Meja aktif',
            current: catalogSummary.active_tables,
            total: catalogSummary.total_tables,
            percent: tableReadiness,
            detail:
                catalogSummary.total_tables > 0
                    ? `${tableReadiness}% meja aktif dan siap dipakai pelanggan.`
                    : 'Belum ada meja yang tercatat di outlet.',
            icon: Table2,
            fillClass: 'bg-[#66784b] dark:bg-accent',
        },
    ];

    return (
        <>
            <Head title="Ringkasan outlet" />
            <div className="bg-background flex flex-1 flex-col">
                <main className="mx-auto w-full max-w-[1500px] p-4 sm:p-6 lg:p-8">
                    <header className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                Ringkasan outlet
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                                Selamat datang, {viewerName}.
                            </h1>
                            <p className="text-muted-foreground mt-2 flex flex-wrap gap-x-2 text-sm">
                                <span className="text-foreground font-semibold">
                                    {outlet.name}
                                </span>
                                <span aria-hidden="true">·</span>
                                <span>{today}</span>
                                <span aria-hidden="true">·</span>
                                <span>{outlet.timezone}</span>
                            </p>
                        </div>

                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <div
                                role="status"
                                className={`bg-card flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-bold ${outlet.accepts_orders ? 'border-emerald-600/20 bg-emerald-500/10 text-emerald-800 dark:text-emerald-300' : 'border-amber-600/20 bg-amber-500/10 text-amber-900 dark:text-amber-300'}`}
                            >
                                <span
                                    className={`size-2 rounded-full ${outlet.accepts_orders ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-amber-500'}`}
                                    aria-hidden="true"
                                />
                                {outlet.accepts_orders
                                    ? 'Order online aktif'
                                    : 'Order online dijeda'}
                            </div>
                            <Link
                                href="/orders"
                                className="bg-foreground text-background hover:bg-primary inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-full px-5 text-sm font-bold transition-colors"
                            >
                                Buka live order
                                <ArrowUpRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </div>
                    </header>

                    <section
                        aria-labelledby="performance-title"
                        className="mt-8 grid gap-5 xl:grid-cols-[1.2fr_0.8fr]"
                    >
                        <article className="bg-foreground text-background relative isolate overflow-hidden rounded-[1.75rem] p-6 shadow-[0_28px_70px_-44px_rgba(53,44,31,0.8)] sm:p-8">
                            <div
                                className="border-primary/20 absolute -right-20 -bottom-32 size-80 rounded-full border-[46px]"
                                aria-hidden="true"
                            />
                            <div className="relative">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                            Performa hari ini
                                        </p>
                                        <h2
                                            id="performance-title"
                                            className="font-display mt-2 text-2xl font-bold tracking-tight sm:text-3xl"
                                        >
                                            Uang masuk, terlihat jelas.
                                        </h2>
                                    </div>
                                    <span className="bg-background/10 text-background/75 inline-flex min-h-9 items-center rounded-full px-3 text-xs font-bold">
                                        Snapshot outlet aktif
                                    </span>
                                </div>

                                <div className="mt-8">
                                    <p className="text-background/65 text-xs font-bold tracking-[0.14em] uppercase">
                                        Penjualan kotor
                                    </p>
                                    <p className="font-display mt-1 text-4xl font-bold tracking-tight sm:text-5xl">
                                        {formatMoney(
                                            orderSummary.gross_sales_today,
                                            outlet.currency,
                                        )}
                                    </p>
                                    <p className="text-background/65 mt-3 flex items-center gap-2 text-sm">
                                        <CheckCircle2
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
                                        <dd className="mt-1 text-2xl font-bold">
                                            {formatNumber(
                                                orderSummary.orders_today,
                                            )}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-background/65 text-xs font-semibold">
                                            Rata-rata order
                                        </dt>
                                        <dd className="mt-1 text-2xl font-bold">
                                            {formatMoney(
                                                averageOrder,
                                                outlet.currency,
                                            )}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </article>

                        <article
                            aria-labelledby="operations-title"
                            className="bg-card rounded-[1.75rem] border p-6 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.65)] sm:p-8"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                        Operasional sekarang
                                    </p>
                                    <h2
                                        id="operations-title"
                                        className="font-display mt-2 text-2xl font-bold tracking-tight"
                                    >
                                        Antrean yang perlu diperhatikan.
                                    </h2>
                                </div>
                                <span className="bg-secondary text-primary flex size-11 shrink-0 items-center justify-center rounded-2xl">
                                    <Clock3
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                            </div>

                            <div className="mt-8 flex items-baseline gap-3">
                                <span className="font-display text-6xl leading-none font-bold tracking-tight">
                                    {formatNumber(orderSummary.active_orders)}
                                </span>
                                <span className="text-muted-foreground text-sm font-semibold">
                                    order aktif
                                </span>
                            </div>
                            <p className="text-muted-foreground mt-3 max-w-sm text-sm leading-6">
                                {orderSummary.active_orders > 0
                                    ? 'Pesanan sedang berjalan dan menunggu tindakan tim outlet.'
                                    : 'Belum ada pesanan aktif yang menunggu tindakan tim outlet.'}
                            </p>

                            <div className="bg-secondary/60 mt-6 rounded-2xl p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="flex items-center gap-2 text-sm font-bold">
                                        <span
                                            className={`size-2 rounded-full ${outlet.accepts_orders ? 'bg-emerald-600' : 'bg-amber-500'}`}
                                            aria-hidden="true"
                                        />
                                        {outlet.accepts_orders
                                            ? 'Menu QR menerima order'
                                            : 'Menu QR tidak menerima order'}
                                    </span>
                                    <Clock3
                                        className="text-muted-foreground size-4"
                                        aria-hidden="true"
                                    />
                                </div>
                                <p className="text-muted-foreground mt-2 text-xs leading-5">
                                    Status mengikuti outlet aktif dan zona waktu{' '}
                                    {outlet.timezone}.
                                </p>
                            </div>

                            <Link
                                href="/orders"
                                className="border-input bg-background hover:bg-secondary mt-6 flex min-h-12 cursor-pointer items-center justify-between rounded-full border px-5 text-sm font-bold transition-colors"
                            >
                                Tinjau semua order
                                <ArrowRight
                                    className="size-4"
                                    aria-hidden="true"
                                />
                            </Link>
                        </article>
                    </section>

                    <section className="mt-5" aria-labelledby="snapshot-title">
                        <div className="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Snapshot katalog
                                </p>
                                <h2
                                    id="snapshot-title"
                                    className="font-display mt-2 text-2xl font-bold tracking-tight"
                                >
                                    Kesiapan di balik layar.
                                </h2>
                            </div>
                            <p className="text-muted-foreground text-sm">
                                Angka mengikuti outlet aktif.
                            </p>
                        </div>

                        <div className="mt-4 grid gap-4 md:grid-cols-3">
                            {snapshotMetrics.map((metric) => (
                                <article
                                    key={metric.label}
                                    className="bg-card rounded-[1.4rem] border p-5 shadow-[0_14px_40px_-35px_rgba(53,44,31,0.65)]"
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
                        </div>
                    </section>

                    <div className="mt-5 grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                        <section
                            aria-labelledby="readiness-title"
                            className="bg-card rounded-[1.5rem] border p-6 sm:p-8"
                        >
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                        Kesiapan outlet
                                    </p>
                                    <h2
                                        id="readiness-title"
                                        className="font-display mt-2 text-2xl font-bold tracking-tight"
                                    >
                                        Pastikan fondasinya siap.
                                    </h2>
                                </div>
                                <UtensilsCrossed
                                    className="text-primary size-5"
                                    aria-hidden="true"
                                />
                            </div>

                            <div className="mt-7 space-y-6">
                                {readiness.map((item) => {
                                    const isReady =
                                        item.total > 0 && item.percent === 100;

                                    return (
                                        <div key={item.label}>
                                            <div className="flex items-center justify-between gap-4">
                                                <span className="flex min-w-0 items-center gap-2 text-sm font-bold">
                                                    <item.icon
                                                        className="text-primary size-4 shrink-0"
                                                        aria-hidden="true"
                                                    />
                                                    <span className="truncate">
                                                        {item.label}
                                                    </span>
                                                </span>
                                                <span className="shrink-0 text-sm font-bold">
                                                    {formatNumber(item.current)}{' '}
                                                    / {formatNumber(item.total)}
                                                </span>
                                            </div>
                                            <div
                                                className="bg-secondary mt-3 h-2 overflow-hidden rounded-full"
                                                role="progressbar"
                                                aria-label={item.label}
                                                aria-valuemin={0}
                                                aria-valuemax={100}
                                                aria-valuenow={item.percent}
                                                aria-valuetext={`${formatNumber(item.current)} dari ${formatNumber(item.total)}`}
                                            >
                                                <span
                                                    className={`block h-full rounded-full transition-[width] duration-500 ${item.fillClass}`}
                                                    style={{
                                                        width: `${item.percent}%`,
                                                    }}
                                                />
                                            </div>
                                            <div className="text-muted-foreground mt-2 flex items-start justify-between gap-3 text-xs leading-5">
                                                <p>{item.detail}</p>
                                                <span
                                                    className={`flex shrink-0 items-center gap-1 font-bold ${isReady ? 'text-emerald-700 dark:text-emerald-400' : 'text-amber-700 dark:text-amber-400'}`}
                                                >
                                                    {isReady ? (
                                                        <CheckCircle2
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    ) : (
                                                        <CircleAlert
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {isReady
                                                        ? 'Siap'
                                                        : 'Perlu ditinjau'}
                                                </span>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </section>

                        <section
                            aria-labelledby="shortcuts-title"
                            className="bg-card rounded-[1.5rem] border p-6 sm:p-8"
                        >
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Akses cepat
                                </p>
                                <h2
                                    id="shortcuts-title"
                                    className="font-display mt-2 text-2xl font-bold tracking-tight"
                                >
                                    Lanjutkan pekerjaanmu.
                                </h2>
                            </div>

                            <div className="mt-6 space-y-3">
                                <Link
                                    href="/orders"
                                    className="group hover:bg-secondary/60 flex min-h-16 cursor-pointer items-center gap-3 rounded-2xl border p-3 transition-colors"
                                >
                                    <span className="bg-secondary text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                        <ClipboardList
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-sm font-bold">
                                            Live order board
                                        </span>
                                        <span className="text-muted-foreground mt-1 block truncate text-xs">
                                            Terima dan proses antrean pesanan.
                                        </span>
                                    </span>
                                    <ArrowUpRight
                                        className="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                        aria-hidden="true"
                                    />
                                </Link>
                                {canViewReports && (
                                    <Link
                                        href="/reports/sales"
                                        className="group hover:bg-secondary/60 flex min-h-16 cursor-pointer items-center gap-3 rounded-2xl border p-3 transition-colors"
                                    >
                                        <span className="bg-secondary text-primary flex size-10 shrink-0 items-center justify-center rounded-xl">
                                            <TrendingUp
                                                className="size-5"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="block text-sm font-bold">
                                                Laporan penjualan
                                            </span>
                                            <span className="text-muted-foreground mt-1 block truncate text-xs">
                                                Baca tren transaksi dan produk
                                                terlaris.
                                            </span>
                                        </span>
                                        <ArrowUpRight
                                            className="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                )}
                            </div>

                            <div className="border-border/70 mt-6 flex items-start gap-3 border-t pt-5">
                                <QrCode
                                    className="text-primary mt-0.5 size-4 shrink-0"
                                    aria-hidden="true"
                                />
                                <p className="text-muted-foreground text-xs leading-5">
                                    Pelanggan masuk melalui QR meja. Jaga menu
                                    dan meja tetap siap agar antrean berjalan
                                    lancar.
                                </p>
                            </div>
                        </section>
                    </div>

                    <p className="text-muted-foreground mt-6 flex items-center justify-center gap-2 text-center text-xs">
                        <ReceiptText className="size-3.5" aria-hidden="true" />
                        Ringkasan dihitung dari data outlet aktif dan pembayaran
                        yang terverifikasi.
                    </p>
                </main>
            </div>
        </>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Ringkasan', href: dashboard() }] };
