import { Head, router } from "@inertiajs/react";
import { BarChart3, CalendarRange, ReceiptText, TrendingUp } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";

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
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency: "IDR",
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

function formatDay(value: string): string {
    return new Intl.DateTimeFormat("id-ID", { day: "numeric", month: "short" }).format(
        new Date(`${value}T00:00:00`),
    );
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
    const [outlet, setOutlet] = useState(filters.outlet?.toString() ?? "");
    const maxDailyAmount = Math.max(...dailySales.map((sale) => sale.amount), 1);

    function applyFilters(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            "/reports/sales",
            { from, to, outlet: outlet || undefined },
            { preserveState: true, replace: true, preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Laporan penjualan" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                            Data bisnis
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Laporan penjualan
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Ringkasan transaksi berbayar berdasarkan snapshot order dan payment yang
                            terverifikasi.
                        </p>
                    </div>
                    <div className="flex min-h-11 items-center gap-2 rounded-full border bg-card px-4 text-sm font-bold">
                        <TrendingUp className="size-4 text-primary" aria-hidden="true" />
                        {summary.orders} order berbayar
                    </div>
                </div>

                <form
                    onSubmit={applyFilters}
                    className="mt-8 grid gap-3 rounded-[1.5rem] border bg-card p-4 sm:grid-cols-[1fr_1fr_1.3fr_auto] sm:items-end sm:p-5"
                >
                    <label className="grid gap-2 text-xs font-bold text-muted-foreground">
                        Mulai
                        <input
                            type="date"
                            value={from}
                            onChange={(event) => setFrom(event.target.value)}
                            className="min-h-11 rounded-xl border bg-background px-3 text-sm font-semibold text-foreground"
                        />
                    </label>
                    <label className="grid gap-2 text-xs font-bold text-muted-foreground">
                        Sampai
                        <input
                            type="date"
                            value={to}
                            onChange={(event) => setTo(event.target.value)}
                            className="min-h-11 rounded-xl border bg-background px-3 text-sm font-semibold text-foreground"
                        />
                    </label>
                    <label className="grid gap-2 text-xs font-bold text-muted-foreground">
                        Outlet
                        <select
                            value={outlet}
                            onChange={(event) => setOutlet(event.target.value)}
                            className="min-h-11 rounded-xl border bg-background px-3 text-sm font-semibold text-foreground"
                        >
                            <option value="">Semua outlet</option>
                            {outlets.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name} · {item.code}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button type="submit" className="min-h-11 rounded-xl">
                        <CalendarRange aria-hidden="true" /> Terapkan
                    </Button>
                </form>

                <section className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {[
                        {
                            label: "Penjualan kotor",
                            value: formatMoney(summary.gross_sales),
                            icon: TrendingUp,
                        },
                        {
                            label: "Order berbayar",
                            value: summary.orders.toString(),
                            icon: ReceiptText,
                        },
                        {
                            label: "Rata-rata order",
                            value: formatMoney(summary.average_order),
                            icon: BarChart3,
                        },
                        {
                            label: "Refund",
                            value: formatMoney(summary.refunded_amount),
                            icon: ReceiptText,
                        },
                    ].map((metric) => (
                        <article key={metric.label} className="rounded-[1.3rem] border bg-card p-5">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-secondary text-primary">
                                <metric.icon className="size-5" aria-hidden="true" />
                            </span>
                            <p className="mt-5 text-sm text-muted-foreground">{metric.label}</p>
                            <p className="font-display mt-1 text-2xl font-bold tracking-tight">
                                {metric.value}
                            </p>
                            {metric.label === "Refund" && (
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {summary.refunded_orders} transaksi dikembalikan
                                </p>
                            )}
                        </article>
                    ))}
                </section>

                <div className="mt-5 grid gap-5 xl:grid-cols-[1.35fr_0.65fr]">
                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div>
                            <h2 className="font-display text-xl font-bold">Tren penjualan</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Penjualan berdasarkan tanggal payment berhasil.
                            </p>
                        </div>
                        {dailySales.length === 0 ? (
                            <p className="mt-8 rounded-2xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                                Belum ada transaksi pada rentang ini.
                            </p>
                        ) : (
                            <div className="mt-8 grid gap-4">
                                {dailySales.map((sale) => (
                                    <div
                                        key={sale.date}
                                        className="grid grid-cols-[4.5rem_1fr_auto] items-center gap-3 text-sm"
                                    >
                                        <span className="font-semibold text-muted-foreground">
                                            {formatDay(sale.date)}
                                        </span>
                                        <div className="h-3 overflow-hidden rounded-full bg-secondary">
                                            <div
                                                className="h-full rounded-full bg-primary"
                                                style={{
                                                    width: `${(sale.amount / maxDailyAmount) * 100}%`,
                                                }}
                                            />
                                        </div>
                                        <span className="text-right font-bold">
                                            {formatMoney(sale.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <h2 className="font-display text-xl font-bold">Metode pembayaran</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Kontribusi payment berhasil.
                        </p>
                        {paymentMethods.length === 0 ? (
                            <p className="mt-8 text-sm text-muted-foreground">
                                Belum ada data payment.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4">
                                {paymentMethods.map((payment) => (
                                    <div
                                        key={payment.method}
                                        className="flex items-center justify-between gap-4"
                                    >
                                        <div>
                                            <p className="font-semibold capitalize">
                                                {payment.method}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {payment.orders} order
                                            </p>
                                        </div>
                                        <p className="text-sm font-bold">
                                            {formatMoney(payment.amount)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                </div>

                <div className="mt-5 grid gap-5 xl:grid-cols-[0.7fr_1.3fr]">
                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <h2 className="font-display text-xl font-bold">Produk terlaris</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Dari item order yang dibayar.
                        </p>
                        {topProducts.length === 0 ? (
                            <p className="mt-8 text-sm text-muted-foreground">
                                Belum ada detail produk.
                            </p>
                        ) : (
                            <div className="mt-7 grid gap-4">
                                {topProducts.map((product, index) => (
                                    <div key={product.name} className="flex items-start gap-3">
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-xs font-bold text-primary">
                                            {index + 1}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold">{product.name}</p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {product.quantity} item ·{" "}
                                                {formatMoney(product.amount)}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>

                    <section className="rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl font-bold">
                                    Transaksi terbaru
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Maksimal 100 transaksi pada filter aktif.
                                </p>
                            </div>
                            <ReceiptText className="size-6 text-primary" aria-hidden="true" />
                        </div>
                        {transactions.length === 0 ? (
                            <p className="mt-8 text-sm text-muted-foreground">
                                Belum ada transaksi.
                            </p>
                        ) : (
                            <div className="mt-7 overflow-x-auto">
                                <table className="w-full min-w-[620px] text-left text-sm">
                                    <thead className="border-b text-xs text-muted-foreground">
                                        <tr>
                                            <th scope="col" className="pb-3 font-semibold">
                                                Order
                                            </th>
                                            <th scope="col" className="pb-3 font-semibold">
                                                Outlet
                                            </th>
                                            <th scope="col" className="pb-3 font-semibold">
                                                Payment
                                            </th>
                                            <th scope="col" className="pb-3 text-right font-semibold">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {transactions.map((transaction) => (
                                            <tr key={transaction.order_number}>
                                                <td className="py-4">
                                                    <p className="font-bold">
                                                        {transaction.order_number}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {formatDate(transaction.paid_at)} ·{" "}
                                                        {transaction.status}
                                                    </p>
                                                </td>
                                                <td className="py-4 text-muted-foreground">
                                                    {transaction.outlet ?? "-"}
                                                </td>
                                                <td className="py-4 capitalize text-muted-foreground">
                                                    {transaction.payment_method ?? "-"}
                                                </td>
                                                <td className="py-4 text-right font-bold">
                                                    {formatMoney(transaction.amount)}
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

SalesReport.layout = { breadcrumbs: [{ title: "Laporan penjualan", href: "/reports/sales" }] };
