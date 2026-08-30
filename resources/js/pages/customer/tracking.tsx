import { Head, Link } from "@inertiajs/react";
import {
    Check,
    ChevronRight,
    Clock3,
    Download,
    MapPin,
    ReceiptText,
    ShoppingBag,
    UtensilsCrossed,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { CustomerHeader } from "@/components/customer-header";
import { formatCurrency, menuItems } from "@/data/demo";
import { useRealtime } from "@/hooks/use-realtime";
import type { CustomerOrder } from "@/types/customer";

type Props = {
    access?: { valid: boolean; message: string | null };
    order?: CustomerOrder | null;
    realtime?: {
        channel: string;
        poll_url: string;
        payment_start_url?: string;
        receipt_url?: string;
    } | null;
};

const statusFlow = [
    { status: "awaiting_payment", label: "Menunggu pembayaran" },
    { status: "paid", label: "Pembayaran diterima" },
    { status: "accepted", label: "Pesanan diterima dapur" },
    { status: "preparing", label: "Sedang disiapkan" },
    { status: "ready", label: "Siap disajikan" },
    { status: "served", label: "Sudah disajikan" },
    { status: "completed", label: "Selesai" },
] as const;

const demoOrder: CustomerOrder = {
    id: 0,
    number: "A-1048",
    status: "preparing",
    status_label: "Sedang disiapkan",
    payment_status: "paid",
    payment_method: "qris",
    customer_name: "Raka",
    outlet: { name: "Kedai Sore", currency: "IDR" },
    table: { name: "Meja 08", code: "TBL-008" },
    subtotal: 104000,
    discount_amount: 0,
    tax_name: "Pajak restoran",
    tax_rate_basis_points: 1000,
    tax_inclusive: false,
    tax_amount: 10400,
    fee_amount: 0,
    grand_total: 114400,
    currency: "IDR",
    paid_at: "2026-08-29T12:42:00.000Z",
    completed_at: null,
    created_at: "2026-08-29T12:40:00.000Z",
    items: [
        {
            id: 1,
            product_name: menuItems[0].name,
            variant_name: null,
            quantity: 1,
            unit_price: menuItems[0].price,
            line_total: menuItems[0].price,
            note: "Tanpa bawang",
            modifiers: [{ modifier_name: "Level pedas", option_name: "Sedang", price_delta: 0 }],
        },
        {
            id: 2,
            product_name: menuItems[4].name,
            variant_name: null,
            quantity: 2,
            unit_price: menuItems[4].price,
            line_total: menuItems[4].price * 2,
            note: null,
            modifiers: [],
        },
    ],
    status_history: [],
};

const statusCopy: Record<string, { label: string; headline: string; description: string }> = {
    awaiting_payment: {
        label: "Menunggu pembayaran",
        headline: "Selesaikan pembayaran untuk mengirim pesananmu.",
        description:
            "Order sudah dicatat, tetapi belum masuk ke antrean dapur sampai pembayaran diverifikasi.",
    },
    paid: {
        label: "Pembayaran diterima",
        headline: "Pesananmu sudah diterima.",
        description: "Staf akan segera meneruskan pesanan ke dapur.",
    },
    accepted: {
        label: "Diterima dapur",
        headline: "Pesananmu sedang masuk antrean dapur.",
        description: "Staf sudah menerima order dan akan mulai menyiapkannya.",
    },
    preparing: {
        label: "Sedang disiapkan",
        headline: "Dapur sedang meracik pesananmu.",
        description:
            "Duduk santai, ya. Pesanan akan diantar langsung ke meja setelah semuanya siap.",
    },
    ready: {
        label: "Siap disajikan",
        headline: "Pesananmu sudah siap.",
        description: "Staf akan segera mengantarkan pesanan ke meja.",
    },
    served: {
        label: "Sudah disajikan",
        headline: "Selamat menikmati pesananmu.",
        description: "Pesanan sudah disajikan di meja.",
    },
    completed: {
        label: "Selesai",
        headline: "Terima kasih sudah memesan.",
        description: "Pesanan ini sudah selesai.",
    },
};

const formatTime = (value: string | null | undefined) => {
    if (!value) {
        return "-";
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime())
        ? "-"
        : new Intl.DateTimeFormat("id-ID", { hour: "2-digit", minute: "2-digit" }).format(date);
};

const TRACKING_REQUEST_TIMEOUT_MS = 15_000;

function isOrderEvent(payload: unknown): payload is { order: CustomerOrder } {
    if (typeof payload !== "object" || payload === null || !("order" in payload)) {
        return false;
    }

    const nextOrder = (payload as { order?: unknown }).order;

    return (
        typeof nextOrder === "object" &&
        nextOrder !== null &&
        "number" in nextOrder &&
        "status" in nextOrder
    );
}

export default function Tracking({ access, order, realtime }: Props) {
    const [liveOrder, setLiveOrder] = useState<CustomerOrder | null>(order ?? null);
    const [paymentStarting, setPaymentStarting] = useState(false);
    const [paymentError, setPaymentError] = useState<string | null>(null);
    const [trackingError, setTrackingError] = useState<string | null>(null);
    const [trackingRetrying, setTrackingRetrying] = useState(false);
    const [statusAnnouncement, setStatusAnnouncement] = useState("");
    const announcedStatus = useRef<string | null>(order?.status ?? null);

    useEffect(() => {
        setLiveOrder(order ?? null);
    }, [order]);

    useEffect(() => {
        if (!liveOrder || liveOrder.status === announcedStatus.current) {
            return;
        }

        announcedStatus.current = liveOrder.status;
        setStatusAnnouncement(
            `Status pesanan #${liveOrder.number}: ${statusCopy[liveOrder.status]?.label ?? liveOrder.status_label}.`,
        );
    }, [liveOrder]);

    async function refreshOrder(): Promise<void> {
        if (!realtime?.poll_url) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), TRACKING_REQUEST_TIMEOUT_MS);

        try {
            const response = await fetch(realtime.poll_url, {
                headers: { Accept: "application/json" },
                credentials: "same-origin",
                cache: "no-store",
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error("Order tracking tidak dapat diperbarui.");
            }

            const body = (await response.json()) as { order?: CustomerOrder };

            if (!body.order) {
                throw new Error("Data order belum tersedia.");
            }

            setLiveOrder(body.order);
            setTrackingError(null);
        } catch (exception) {
            const message =
                exception instanceof Error && exception.name === "AbortError"
                    ? "Koneksi terlalu lama. Coba perbarui status lagi."
                    : exception instanceof Error
                      ? exception.message
                      : "Order tracking tidak dapat diperbarui.";

            setTrackingError(message);
            throw exception;
        } finally {
            window.clearTimeout(timeout);
        }
    }

    async function retryTracking(): Promise<void> {
        setTrackingRetrying(true);

        try {
            await refreshOrder();
        } catch {
            // The error state already contains the actionable message.
        } finally {
            setTrackingRetrying(false);
        }
    }

    async function continuePayment(): Promise<void> {
        if (!realtime?.payment_start_url) {
            return;
        }

        setPaymentStarting(true);
        setPaymentError(null);

        try {
            const response = await fetch(realtime.payment_start_url, {
                method: "POST",
                headers: { Accept: "application/json" },
                credentials: "same-origin",
            });
            const body = (await response.json()) as { redirect_url?: string; message?: string };

            if (!response.ok || !body.redirect_url) {
                throw new Error(body.message ?? "Sesi pembayaran belum dapat dibuat.");
            }

            window.location.assign(body.redirect_url);
        } catch (exception) {
            setPaymentError(
                exception instanceof Error
                    ? exception.message
                    : "Sesi pembayaran belum dapat dibuat.",
            );
            setPaymentStarting(false);
        }
    }

    const realtimeStatus = useRealtime({
        enabled: Boolean(realtime?.poll_url),
        channel: realtime?.channel ?? "",
        channelType: "public",
        event: ".order.status.updated",
        onEvent: (payload) => {
            if (isOrderEvent(payload)) {
                setLiveOrder(payload.order);

                return;
            }

            void refreshOrder().catch(() => undefined);
        },
        onRefresh: refreshOrder,
    });

    if (access && !access.valid) {
        return (
            <>
                <Head title="Order tidak ditemukan" />
                <div className="min-h-screen bg-background">
                    <CustomerHeader minimal />
                    <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-xl items-center px-4 py-10 sm:px-6">
                        <section className="w-full rounded-[1.75rem] border bg-card p-7 text-center shadow-sm sm:p-10">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
                                <ShoppingBag className="size-8" aria-hidden="true" />
                            </div>
                            <p className="mt-6 text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                Tracking order
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight">
                                Order tidak ditemukan
                            </h1>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                {access.message ?? "Tautan tracking ini tidak dapat digunakan."}
                            </p>
                            <Link
                                href="/"
                                className="mt-7 inline-flex min-h-11 items-center justify-center rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground"
                            >
                                Kembali ke beranda
                            </Link>
                        </section>
                    </main>
                </div>
            </>
        );
    }

    if (access?.valid && !liveOrder) {
        return (
            <>
                <Head title="Tracking belum tersedia" />
                <div className="min-h-screen bg-background">
                    <CustomerHeader minimal />
                    <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-xl items-center px-4 py-10 sm:px-6">
                        <section className="w-full rounded-[1.75rem] border bg-card p-7 text-center shadow-sm sm:p-10">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-700">
                                <Clock3 className="size-8" aria-hidden="true" />
                            </div>
                            <p className="mt-6 text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                Tracking order
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight">
                                Status belum tersedia
                            </h1>
                            <p
                                className="mt-3 text-sm leading-6 text-muted-foreground"
                                role="alert"
                            >
                                {trackingError ??
                                    "Data order belum tersedia. Coba perbarui halaman ini."}
                            </p>
                            <button
                                type="button"
                                onClick={() => void retryTracking()}
                                disabled={trackingRetrying || !realtime?.poll_url}
                                className="mt-7 inline-flex min-h-11 items-center justify-center rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {trackingRetrying ? "Memperbarui..." : "Coba lagi"}
                            </button>
                        </section>
                    </main>
                </div>
            </>
        );
    }

    const displayOrder = access === undefined ? (liveOrder ?? demoOrder) : liveOrder;

    if (!displayOrder) {
        return null;
    }
    const copy = statusCopy[displayOrder.status] ?? {
        label: displayOrder.status_label,
        headline: "Status pesanan diperbarui.",
        description: "Simpan halaman ini untuk memantau pesanan.",
    };
    const currentIndex = statusFlow.findIndex((step) => step.status === displayOrder.status);
    const historyByStatus = new Map(
        displayOrder.status_history.map((entry) => [entry.to_status, entry]),
    );

    return (
        <>
            <Head title={`Pesanan #${displayOrder.number}`} />
            <div className="min-h-screen bg-background">
                <CustomerHeader minimal />
                <main className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-14">
                    <section className="relative overflow-hidden rounded-[2rem] bg-[#283025] p-7 text-[#fffaf0] sm:p-10">
                        <div
                            className="absolute -top-16 -right-12 size-52 rounded-full border-[30px] border-white/5"
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <div className="flex items-center justify-between gap-4">
                                <span className="rounded-full bg-[#d87655]/20 px-3 py-1.5 text-xs font-bold text-[#eda98f]">
                                    #{displayOrder.number}
                                </span>
                                <span className="flex items-center gap-2 text-xs text-[#cbd1c3]">
                                    <MapPin className="size-3.5" aria-hidden="true" />{" "}
                                    {displayOrder.table?.name ?? "Meja"}
                                </span>
                            </div>
                            <span className="mt-10 flex size-14 items-center justify-center rounded-2xl bg-[#d87655] text-white">
                                <UtensilsCrossed className="size-6" aria-hidden="true" />
                            </span>
                            <p className="mt-6 text-sm font-bold tracking-[0.14em] text-[#eda98f] uppercase">
                                {copy.label}
                            </p>
                            <h1 className="font-display mt-2 text-4xl font-bold tracking-tight sm:text-5xl">
                                {copy.headline}
                            </h1>
                            <p className="mt-4 max-w-lg leading-7 text-[#cbd1c3]">
                                {copy.description}
                            </p>
                            <div className="mt-8 flex w-fit items-center gap-3 rounded-full bg-white/8 px-4 py-3 text-sm">
                                <Clock3 className="size-4 text-[#eda98f]" aria-hidden="true" />
                                <span>
                                    {displayOrder.payment_status === "pending"
                                        ? "Pembayaran menunggu verifikasi server"
                                        : !realtime?.poll_url
                                          ? "Status tidak diperbarui otomatis"
                                          : !realtime.channel
                                            ? "Status diperbarui berkala"
                                            : realtimeStatus === "connected"
                                              ? "Status diperbarui realtime"
                                              : realtimeStatus === "offline"
                                                ? "Koneksi realtime terputus, mencoba lagi"
                                                : "Status diperbarui berkala"}
                                </span>
                            </div>
                        </div>
                    </section>
                    <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
                        {statusAnnouncement}
                    </div>
                    {trackingError && realtime?.poll_url && (
                        <div className="mt-4 flex flex-col gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 sm:flex-row sm:items-center sm:justify-between">
                            <p role="alert">{trackingError}</p>
                            <button
                                type="button"
                                onClick={() => void retryTracking()}
                                disabled={trackingRetrying}
                                className="min-h-10 shrink-0 rounded-full border border-amber-400 px-4 text-xs font-bold disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {trackingRetrying ? "Memperbarui..." : "Coba lagi"}
                            </button>
                        </div>
                    )}

                    <section className="mt-6 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <h2 className="font-display text-2xl font-bold">Perjalanan pesanan</h2>
                        <ol className="mt-7">
                            {statusFlow.map((step, index) => {
                                const history = historyByStatus.get(step.status);
                                const done = currentIndex >= 0 && index <= currentIndex;
                                const active = step.status === displayOrder.status;

                                return (
                                    <li
                                        key={step.status}
                                        aria-current={active ? "step" : undefined}
                                        className="relative flex gap-4 pb-8 last:pb-0"
                                    >
                                        {index < statusFlow.length - 1 && (
                                            <span
                                                className={`absolute top-7 bottom-0 left-[13px] w-px ${done ? "bg-primary" : "bg-border"}`}
                                                aria-hidden="true"
                                            />
                                        )}
                                        <span
                                            className={`relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full border ${done ? "border-primary bg-primary text-white" : "bg-card text-transparent"}`}
                                        >
                                            {done && (
                                                <Check className="size-3.5" aria-hidden="true" />
                                            )}
                                        </span>
                                        <div className="flex flex-1 items-start justify-between gap-4">
                                            <div>
                                                <p
                                                    className={`text-sm font-bold ${!done ? "text-muted-foreground" : ""}`}
                                                >
                                                    {step.label}
                                                    <span className="sr-only">
                                                        {active
                                                            ? ": Sedang berlangsung"
                                                            : done
                                                              ? ": Selesai"
                                                              : ": Belum dimulai"}
                                                    </span>
                                                </p>
                                                {active && (
                                                    <p className="mt-1 text-xs text-primary">
                                                        {realtime?.poll_url
                                                            ? "Diperbarui otomatis"
                                                            : "Periksa kembali nanti"}
                                                    </p>
                                                )}
                                            </div>
                                            <time className="text-xs text-muted-foreground">
                                                {formatTime(history?.created_at)}
                                            </time>
                                        </div>
                                    </li>
                                );
                            })}
                        </ol>
                    </section>

                    <section className="mt-6 rounded-[1.5rem] border bg-card p-6 sm:p-8">
                        <div className="flex items-center justify-between">
                            <h2 className="font-display text-2xl font-bold">Detail pesanan</h2>
                            <span className="text-xs text-muted-foreground">
                                {displayOrder.items.reduce((sum, item) => sum + item.quantity, 0)}{" "}
                                item
                            </span>
                        </div>
                        <div className="mt-6 space-y-5">
                            {displayOrder.items.map((item) => (
                                <div key={item.id} className="flex items-start gap-4">
                                    <div className="flex size-14 shrink-0 items-center justify-center rounded-xl bg-secondary text-primary">
                                        <ShoppingBag className="size-5" aria-hidden="true" />
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-sm font-bold">
                                            {item.quantity}x {item.product_name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {[
                                                item.variant_name,
                                                ...item.modifiers.map(
                                                    (modifier) => modifier.option_name,
                                                ),
                                                item.note,
                                            ]
                                                .filter(Boolean)
                                                .join(" · ") || "Tanpa catatan tambahan"}
                                        </p>
                                    </div>
                                    <p className="text-sm font-bold">
                                        {formatCurrency(item.line_total)}
                                    </p>
                                </div>
                            ))}
                        </div>
                        <div className="my-6 border-t" />
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between text-muted-foreground">
                                <dt>Subtotal</dt>
                                <dd>{formatCurrency(displayOrder.subtotal)}</dd>
                            </div>
                            {displayOrder.tax_amount > 0 && (
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>{displayOrder.tax_name ?? "Pajak"}</dt>
                                    <dd>{formatCurrency(displayOrder.tax_amount)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between font-bold">
                                <dt>
                                    Total{" "}
                                    {displayOrder.payment_status === "paid" ? "dibayar" : "pesanan"}
                                </dt>
                                <dd>{formatCurrency(displayOrder.grand_total)}</dd>
                            </div>
                        </dl>
                    </section>

                    {displayOrder.payment_status === "pending" && realtime?.payment_start_url && (
                        <section
                            className="mt-6 rounded-[1.5rem] border border-primary/25 bg-primary/5 p-6 sm:p-8"
                            aria-live="polite"
                        >
                            <p className="text-xs font-bold tracking-[0.14em] text-primary uppercase">
                                Pembayaran belum selesai
                            </p>
                            <h2 className="font-display mt-2 text-2xl font-bold">
                                Lanjutkan pembayaranmu.
                            </h2>
                            <p className="mt-2 max-w-xl text-sm leading-6 text-muted-foreground">
                                Order belum masuk ke antrean dapur sampai pembayaran diverifikasi.
                            </p>
                            <button
                                type="button"
                                onClick={() => void continuePayment()}
                                disabled={paymentStarting}
                                className="mt-5 inline-flex min-h-11 items-center justify-center gap-2 rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {paymentStarting ? "Membuka pembayaran..." : "Bayar sekarang"}
                                <ChevronRight className="size-4" aria-hidden="true" />
                            </button>
                            {paymentError && (
                                <p
                                    className="mt-3 text-sm font-semibold text-destructive"
                                    role="alert"
                                >
                                    {paymentError}
                                </p>
                            )}
                        </section>
                    )}

                    <div className="mt-6 grid gap-3 sm:grid-cols-2">
                        {displayOrder.payment_status !== "pending" && realtime?.receipt_url ? (
                            <a
                                href={realtime.receipt_url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex min-h-12 items-center justify-between rounded-full border bg-card px-5 text-sm font-bold hover:bg-secondary"
                            >
                                <span className="flex items-center gap-2">
                                    <ReceiptText className="size-4" aria-hidden="true" /> Lihat
                                    struk digital
                                </span>
                                <ChevronRight className="size-4" aria-hidden="true" />
                            </a>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="flex min-h-12 items-center justify-between rounded-full border bg-card px-5 text-sm font-bold opacity-60"
                            >
                                <span className="flex items-center gap-2">
                                    <ReceiptText className="size-4" aria-hidden="true" /> Lihat
                                    struk digital
                                </span>
                                <ChevronRight className="size-4" aria-hidden="true" />
                            </button>
                        )}
                        {displayOrder.payment_status !== "pending" && realtime?.receipt_url ? (
                            <a
                                href={realtime.receipt_url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex min-h-12 items-center justify-center gap-2 rounded-full border bg-card px-5 text-sm font-bold hover:bg-secondary"
                            >
                                <Download className="size-4" aria-hidden="true" /> Cetak / simpan
                                struk
                            </a>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="flex min-h-12 items-center justify-center gap-2 rounded-full border bg-card px-5 text-sm font-bold opacity-60"
                            >
                                <Download className="size-4" aria-hidden="true" /> Simpan detail
                                pesanan
                            </button>
                        )}
                    </div>
                    <p className="mt-8 text-center text-xs leading-5 text-muted-foreground">
                        Simpan halaman ini untuk kembali melihat status pesanan.{" "}
                        <Link href="/" className="font-bold text-primary">
                            Kembali ke beranda
                        </Link>
                    </p>
                </main>
            </div>
        </>
    );
}
