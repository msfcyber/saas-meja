import { Head } from "@inertiajs/react";
import {
    BellRing,
    ChevronRight,
    Clock3,
    Filter,
    Search,
    UtensilsCrossed,
    Volume2,
} from "lucide-react";
import { useState } from "react";
import { formatCurrency, orders as initialOrders } from "@/data/demo";

const statusConfig = {
    paid: {
        label: "Order baru",
        next: "accepted",
        action: "Terima order",
        color: "bg-amber-50 text-amber-800 border-amber-200",
    },
    accepted: {
        label: "Diterima",
        next: "preparing",
        action: "Mulai siapkan",
        color: "bg-blue-50 text-blue-800 border-blue-200",
    },
    preparing: {
        label: "Disiapkan",
        next: "ready",
        action: "Tandai siap",
        color: "bg-violet-50 text-violet-800 border-violet-200",
    },
    ready: {
        label: "Siap disajikan",
        next: "served",
        action: "Sudah disajikan",
        color: "bg-emerald-50 text-emerald-800 border-emerald-200",
    },
    served: {
        label: "Disajikan",
        next: "completed",
        action: "Selesaikan",
        color: "bg-slate-50 text-slate-700 border-slate-200",
    },
    completed: {
        label: "Selesai",
        next: "completed",
        action: "Selesai",
        color: "bg-slate-100 text-slate-500 border-slate-200",
    },
} as const;

type OrderStatus = keyof typeof statusConfig;

export default function Orders() {
    const [items, setItems] = useState(() =>
        initialOrders.map((order) => ({ ...order, status: order.status as OrderStatus })),
    );
    const [filter, setFilter] = useState<"active" | OrderStatus>("active");
    const visibleOrders = items.filter((order) =>
        filter === "active" ? order.status !== "completed" : order.status === filter,
    );

    const advance = (number: string) =>
        setItems((current) =>
            current.map((order) =>
                order.number === number
                    ? { ...order, status: statusConfig[order.status].next as OrderStatus }
                    : order,
            ),
        );

    return (
        <>
            <Head title="Live order" />
            <div className="flex min-h-0 flex-1 flex-col">
                <div className="border-b bg-card px-4 py-5 sm:px-6 lg:px-8">
                    <div className="mx-auto flex max-w-[1600px] flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="font-display text-3xl font-bold tracking-tight">
                                    Live order
                                </h1>
                                <span className="flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[11px] font-bold text-emerald-700">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    Live
                                </span>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Kedai Sore · 4 order aktif
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                className="flex min-h-11 items-center gap-2 rounded-full border bg-background px-4 text-sm font-bold"
                            >
                                <Volume2 className="size-4 text-primary" aria-hidden="true" /> Suara
                                aktif
                            </button>
                            <button
                                type="button"
                                className="flex min-h-11 items-center gap-2 rounded-full border bg-background px-4 text-sm font-bold"
                            >
                                <Filter className="size-4" aria-hidden="true" /> Filter
                            </button>
                            <label className="relative min-w-52 flex-1">
                                <Search className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground" />
                                <span className="sr-only">Cari order atau meja</span>
                                <input
                                    className="min-h-11 w-full rounded-full border bg-background pr-4 pl-10 text-sm outline-none focus:ring-2 focus:ring-ring"
                                    placeholder="Cari order..."
                                />
                            </label>
                        </div>
                    </div>
                </div>

                <main className="mx-auto w-full max-w-[1600px] flex-1 p-4 sm:p-6 lg:p-8">
                    <div className="flex gap-2 overflow-x-auto pb-2">
                        {[
                            { id: "active", label: "Semua aktif" },
                            { id: "paid", label: "Baru" },
                            { id: "accepted", label: "Diterima" },
                            { id: "preparing", label: "Disiapkan" },
                            { id: "ready", label: "Siap" },
                            { id: "completed", label: "Selesai" },
                        ].map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => setFilter(option.id as "active" | OrderStatus)}
                                className={`min-h-11 shrink-0 rounded-full px-4 text-sm font-bold ${filter === option.id ? "bg-foreground text-background" : "border bg-card hover:bg-secondary"}`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>

                    <div className="mt-5 grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        {visibleOrders.map((order) => {
                            const config = statusConfig[order.status];
                            return (
                                <article
                                    key={order.number}
                                    className={`overflow-hidden rounded-[1.4rem] border bg-card shadow-[0_16px_50px_-40px_rgba(48,39,28,0.7)] ${order.status === "paid" ? "ring-2 ring-amber-300/50" : ""}`}
                                >
                                    <div className="flex items-center justify-between border-b p-5">
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-lg font-bold text-primary">
                                                {order.table.replace("Meja ", "")}
                                            </span>
                                            <div>
                                                <h2 className="font-bold">{order.number}</h2>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {order.customer} · {order.items} item
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            className={`rounded-full border px-2.5 py-1 text-[10px] font-bold ${config.color}`}
                                        >
                                            {config.label}
                                        </span>
                                    </div>
                                    <div className="p-5">
                                        <div className="flex items-center justify-between rounded-xl bg-muted/70 px-3 py-2 text-xs">
                                            <span className="flex items-center gap-2 font-bold">
                                                <Clock3
                                                    className="size-3.5 text-primary"
                                                    aria-hidden="true"
                                                />{" "}
                                                Menunggu
                                            </span>
                                            <strong>{order.age}</strong>
                                        </div>
                                        <div className="mt-5 space-y-4 text-sm">
                                            <div className="flex gap-3">
                                                <strong className="w-5">1x</strong>
                                                <div>
                                                    <p className="font-bold">
                                                        Nasi Ayam Kecombrang
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Pedas sedang
                                                    </p>
                                                    <p className="mt-1 text-xs font-semibold text-primary">
                                                        Catatan: tanpa bawang
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex gap-3">
                                                <strong className="w-5">2x</strong>
                                                <div>
                                                    <p className="font-bold">Es Kopi Aren</p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Es normal
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="my-5 border-t" />
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Total dibayar
                                            </span>
                                            <strong>{formatCurrency(order.total)}</strong>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => advance(order.number)}
                                            disabled={order.status === "completed"}
                                            className="mt-5 flex min-h-12 w-full items-center justify-between rounded-full bg-foreground px-5 text-sm font-bold text-background transition-colors hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span className="flex items-center gap-2">
                                                {order.status === "paid" && (
                                                    <BellRing
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {config.action}
                                            </span>
                                            <ChevronRight className="size-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </article>
                            );
                        })}
                        {visibleOrders.length === 0 && (
                            <div className="col-span-full rounded-[1.5rem] border border-dashed bg-card p-16 text-center">
                                <UtensilsCrossed className="mx-auto size-8 text-muted-foreground" />
                                <p className="mt-4 font-bold">Belum ada order di status ini</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Order baru akan muncul otomatis.
                                </p>
                            </div>
                        )}
                    </div>
                </main>
            </div>
        </>
    );
}

Orders.layout = { breadcrumbs: [{ title: "Live order", href: "/orders" }] };
