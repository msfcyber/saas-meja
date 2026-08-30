import { Head, router } from "@inertiajs/react";
import {
    BellOff,
    BellRing,
    ChevronRight,
    Clock3,
    Filter,
    Search,
    UtensilsCrossed,
    Volume2,
    VolumeX,
} from "lucide-react";
import { useEffect, useEffectEvent, useRef, useState } from "react";
import { toast } from "sonner";
import { Input } from "@/components/ui/input";
import { useRealtime } from "@/hooks/use-realtime";

type OrderStatus = "paid" | "accepted" | "preparing" | "ready" | "served" | "completed";
type FilterStatus = "active" | OrderStatus;

type OrderItem = {
    id: number;
    product_name: string;
    variant_name: string | null;
    quantity: number;
    note: string | null;
    modifiers: Array<{
        modifier_name: string;
        option_name: string;
    }>;
};

type StaffOrder = {
    id: number;
    number: string;
    status: OrderStatus;
    status_label: string;
    payment_status: string | null;
    customer_name: string | null;
    table: { name: string; code: string } | null;
    grand_total: number;
    currency: string;
    created_at: string;
    items: OrderItem[];
};

type OrderNotification = {
    id: number;
    number: string;
    table_name: string | null;
};

type NotificationPreferences = {
    visual_enabled: boolean;
    sound_enabled: boolean;
};

type Props = {
    outlet: { name: string; timezone: string };
    realtime?: { channel: string } | null;
    notifications: NotificationPreferences;
    notification_orders: OrderNotification[];
    filters: { search: string; status: FilterStatus };
    counts: Record<FilterStatus, number>;
    orders: StaffOrder[];
};

const statusConfig: Record<
    OrderStatus,
    { next: OrderStatus | null; action: string; color: string }
> = {
    paid: {
        next: "accepted",
        action: "Terima order",
        color: "bg-amber-50 text-amber-800 border-amber-200",
    },
    accepted: {
        next: "preparing",
        action: "Mulai siapkan",
        color: "bg-blue-50 text-blue-800 border-blue-200",
    },
    preparing: {
        next: "ready",
        action: "Tandai siap",
        color: "bg-violet-50 text-violet-800 border-violet-200",
    },
    ready: {
        next: "served",
        action: "Sudah disajikan",
        color: "bg-emerald-50 text-emerald-800 border-emerald-200",
    },
    served: {
        next: "completed",
        action: "Selesaikan",
        color: "bg-slate-50 text-slate-700 border-slate-200",
    },
    completed: {
        next: null,
        action: "Selesai",
        color: "bg-slate-100 text-slate-500 border-slate-200",
    },
};

const filterOptions: Array<{ id: FilterStatus; label: string }> = [
    { id: "active", label: "Semua aktif" },
    { id: "paid", label: "Baru" },
    { id: "accepted", label: "Diterima" },
    { id: "preparing", label: "Disiapkan" },
    { id: "ready", label: "Siap" },
    { id: "served", label: "Disajikan" },
    { id: "completed", label: "Selesai" },
];

function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat("id-ID", {
        style: "currency",
        currency,
        maximumFractionDigits: 0,
    }).format(value);
}

function formatAge(createdAt: string): string {
    const totalSeconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(createdAt).getTime()) / 1000),
    );
    const minutes = Math.floor(totalSeconds / 60);

    if (minutes < 60) {
        return `${String(minutes).padStart(2, "0")}:${String(totalSeconds % 60).padStart(2, "0")}`;
    }

    return `${Math.floor(minutes / 60)}j ${String(minutes % 60).padStart(2, "0")}m`;
}

function isNewOrderEvent(payload: unknown): payload is { order: StaffOrder } {
    if (typeof payload !== "object" || payload === null || !("order" in payload)) {
        return false;
    }

    const order = (payload as { order?: unknown }).order;

    return (
        typeof order === "object" &&
        order !== null &&
        "id" in order &&
        "number" in order &&
        "status" in order
    );
}

export default function Orders({
    outlet,
    realtime,
    notifications,
    notification_orders: notificationOrders,
    filters,
    counts,
    orders,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [pendingOrderId, setPendingOrderId] = useState<number | null>(null);
    const [notificationPreferences, setNotificationPreferences] =
        useState<NotificationPreferences>(notifications);
    const [liveAnnouncement, setLiveAnnouncement] = useState("");
    const audioContext = useRef<AudioContext | null>(null);
    const announcedOrderIds = useRef(new Set<number>());
    const hasLoadedNotificationOrders = useRef(false);

    useEffect(() => {
        setNotificationPreferences(notifications);
    }, [notifications]);

    useEffect(() => {
        return () => {
            const context = audioContext.current;
            audioContext.current = null;
            void context?.close();
        };
    }, []);

    function getAudioContext(): AudioContext | null {
        if (typeof window === "undefined" || !window.AudioContext) {
            return null;
        }

        const context = audioContext.current ?? new window.AudioContext();
        audioContext.current = context;

        if (context.state === "suspended") {
            void context.resume();
        }

        return context;
    }

    function playNewOrderTone() {
        const context = getAudioContext();

        if (context === null) {
            return;
        }

        [880, 1175].forEach((frequency, index) => {
            const oscillator = context.createOscillator();
            const gain = context.createGain();
            const start = context.currentTime + index * 0.16;

            oscillator.type = "sine";
            oscillator.frequency.setValueAtTime(frequency, start);
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.12, start + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.13);
            oscillator.connect(gain).connect(context.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.14);
        });
    }

    const unlockAudio = useEffectEvent(() => {
        getAudioContext();
    });

    useEffect(() => {
        if (!notificationPreferences.sound_enabled) {
            return;
        }

        const onPointerDown = () => {
            unlockAudio();
        };

        document.addEventListener("pointerdown", onPointerDown, { once: true });

        return () => document.removeEventListener("pointerdown", onPointerDown);
    }, [notificationPreferences.sound_enabled]);

    const announceNewOrder = useEffectEvent((order: OrderNotification) => {
        if (announcedOrderIds.current.has(order.id)) {
            return;
        }

        announcedOrderIds.current.add(order.id);
        const table = order.table_name ? ` untuk ${order.table_name}` : "";
        const message = `Order baru #${order.number}${table}.`;

        setLiveAnnouncement(message);

        if (notificationPreferences.visual_enabled) {
            toast("Order baru masuk", {
                description: message,
                duration: 5_000,
            });
        }

        if (notificationPreferences.sound_enabled) {
            playNewOrderTone();
        }
    });

    useEffect(() => {
        if (!hasLoadedNotificationOrders.current) {
            notificationOrders.forEach((order) => {
                announcedOrderIds.current.add(order.id);
            });
            hasLoadedNotificationOrders.current = true;

            return;
        }

        notificationOrders.forEach((order) => {
            announceNewOrder(order);
        });
    }, [notificationOrders]);

    function reloadBoard() {
        router.reload({
            only: ["orders", "counts", "notification_orders"],
        });
    }

    const realtimeStatus = useRealtime({
        enabled: Boolean(realtime?.channel),
        channel: realtime?.channel ?? "",
        channelType: "private",
        event: ".order.status.updated",
        onEvent: (payload) => {
            if (isNewOrderEvent(payload) && payload.order.status === "paid") {
                announceNewOrder({
                    id: payload.order.id,
                    number: payload.order.number,
                    table_name: payload.order.table?.name ?? null,
                });
            }

            reloadBoard();
        },
        onRefresh: reloadBoard,
    });

    const realtimeLabel =
        realtimeStatus === "connected"
            ? "Realtime"
            : realtimeStatus === "polling"
              ? "Polling"
              : realtimeStatus === "offline"
                ? "Offline"
                : realtimeStatus === "disabled"
                  ? "Database"
                  : "Menghubungkan";
    const realtimeTone =
        realtimeStatus === "connected"
            ? "bg-emerald-50 text-emerald-700"
            : realtimeStatus === "offline"
              ? "bg-amber-50 text-amber-700"
              : "bg-secondary text-muted-foreground";

    function applyFilters(status: FilterStatus = filters.status) {
        router.get(
            "/orders",
            { search: search || undefined, status: status === "active" ? undefined : status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function updateNotificationPreferences(next: NotificationPreferences) {
        const previous = notificationPreferences;

        setNotificationPreferences(next);
        router.put("/orders/notifications", next, {
            preserveScroll: true,
            preserveState: true,
            onError: () => setNotificationPreferences(previous),
        });
    }

    function toggleSound() {
        const next = {
            ...notificationPreferences,
            sound_enabled: !notificationPreferences.sound_enabled,
        };

        if (next.sound_enabled) {
            playNewOrderTone();
        }

        updateNotificationPreferences(next);
    }

    function advance(order: StaffOrder) {
        const next = statusConfig[order.status].next;

        if (next === null) {
            return;
        }

        setPendingOrderId(order.id);
        router.patch(
            `/orders/${order.id}/status`,
            { status: next },
            {
                preserveScroll: true,
                onFinish: () => setPendingOrderId(null),
            },
        );
    }

    return (
        <>
            <Head title="Live order" />
            <div className="flex min-h-0 flex-1 flex-col">
                <p className="sr-only" aria-live="polite">
                    {liveAnnouncement}
                </p>
                <div className="border-b bg-card px-4 py-5 sm:px-6 lg:px-8">
                    <div className="mx-auto flex max-w-[1600px] flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="font-display text-3xl font-bold tracking-tight">
                                    Live order
                                </h1>
                                <span
                                    className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ${realtimeTone}`}
                                >
                                    <span
                                        className={`size-1.5 rounded-full ${realtimeStatus === "connected" ? "bg-emerald-500" : "bg-current"}`}
                                    />
                                    {realtimeLabel}
                                </span>
                            </div>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {outlet.name} · {counts.active} order aktif
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    updateNotificationPreferences({
                                        ...notificationPreferences,
                                        visual_enabled:
                                            !notificationPreferences.visual_enabled,
                                    })
                                }
                                className="flex min-h-11 items-center gap-2 rounded-full border bg-background px-4 text-sm font-bold"
                                aria-pressed={notificationPreferences.visual_enabled}
                            >
                                {notificationPreferences.visual_enabled ? (
                                    <BellRing className="size-4 text-primary" aria-hidden="true" />
                                ) : (
                                    <BellOff
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                                Visual {notificationPreferences.visual_enabled ? "aktif" : "mati"}
                            </button>
                            <button
                                type="button"
                                onClick={toggleSound}
                                className="flex min-h-11 items-center gap-2 rounded-full border bg-background px-4 text-sm font-bold"
                                aria-pressed={notificationPreferences.sound_enabled}
                            >
                                {notificationPreferences.sound_enabled ? (
                                    <Volume2 className="size-4 text-primary" aria-hidden="true" />
                                ) : (
                                    <VolumeX
                                        className="size-4 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                )}
                                Suara {notificationPreferences.sound_enabled ? "aktif" : "mati"}
                            </button>
                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                className="flex min-h-11 items-center gap-2 rounded-full border bg-background px-4 text-sm font-bold"
                            >
                                <Filter className="size-4" aria-hidden="true" /> Filter
                            </button>
                            <form
                                className="relative min-w-52 flex-1"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    applyFilters();
                                }}
                            >
                                <Search
                                    className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <label className="sr-only" htmlFor="order-search">
                                    Cari order atau meja
                                </label>
                                <Input
                                    id="order-search"
                                    value={search}
                                    onChange={(event) => setSearch(event.target.value)}
                                    className="min-h-11 rounded-full pr-4 pl-10"
                                    placeholder="Cari order atau meja..."
                                />
                            </form>
                        </div>
                    </div>
                </div>

                <main className="mx-auto w-full max-w-[1600px] flex-1 p-4 sm:p-6 lg:p-8">
                    <div className="flex gap-2 overflow-x-auto pb-2">
                        {filterOptions.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => applyFilters(option.id)}
                                className={`flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-sm font-bold ${filters.status === option.id ? "bg-foreground text-background" : "border bg-card hover:bg-secondary"}`}
                            >
                                {option.label}
                                <span className="text-xs opacity-70">{counts[option.id]}</span>
                            </button>
                        ))}
                    </div>

                    <div className="mt-5 grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        {orders.map((order) => {
                            const config = statusConfig[order.status];
                            const itemCount = order.items.reduce(
                                (total, item) => total + item.quantity,
                                0,
                            );

                            return (
                                <article
                                    key={order.id}
                                    className={`overflow-hidden rounded-[1.4rem] border bg-card shadow-[0_16px_50px_-40px_rgba(48,39,28,0.7)] ${order.status === "paid" ? "ring-2 ring-amber-300/50" : ""}`}
                                >
                                    <div className="flex items-center justify-between border-b p-5">
                                        <div className="flex items-center gap-3">
                                            <span className="flex size-12 items-center justify-center rounded-2xl bg-secondary text-lg font-bold text-primary">
                                                {order.table?.name.replace(/^Meja\s+/i, "") ?? "-"}
                                            </span>
                                            <div>
                                                <h2 className="font-bold">{order.number}</h2>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {order.customer_name ?? "Guest"} · {itemCount}{" "}
                                                    item
                                                </p>
                                                <p
                                                    className={`mt-1 text-[11px] font-semibold ${order.payment_status === "paid" ? "text-emerald-700" : "text-muted-foreground"}`}
                                                >
                                                    Pembayaran{" "}
                                                    {order.payment_status === "paid"
                                                        ? "lunas"
                                                        : (order.payment_status ??
                                                          "belum diverifikasi")}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            className={`rounded-full border px-2.5 py-1 text-[10px] font-bold ${config.color}`}
                                        >
                                            {order.status_label}
                                        </span>
                                    </div>
                                    <div className="p-5">
                                        <div className="flex items-center justify-between rounded-xl bg-muted/70 px-3 py-2 text-xs">
                                            <span className="flex items-center gap-2 font-bold">
                                                <Clock3
                                                    className="size-3.5 text-primary"
                                                    aria-hidden="true"
                                                />
                                                Menunggu
                                            </span>
                                            <strong>{formatAge(order.created_at)}</strong>
                                        </div>
                                        <div className="mt-5 space-y-4 text-sm">
                                            {order.items.map((item) => (
                                                <div key={item.id} className="flex gap-3">
                                                    <strong className="w-5 shrink-0">
                                                        {item.quantity}x
                                                    </strong>
                                                    <div className="min-w-0">
                                                        <p className="font-bold">
                                                            {item.product_name}
                                                        </p>
                                                        {item.variant_name && (
                                                            <p className="mt-1 text-xs text-muted-foreground">
                                                                {item.variant_name}
                                                            </p>
                                                        )}
                                                        {item.modifiers.map((modifier) => (
                                                            <p
                                                                key={`${item.id}-${modifier.modifier_name}-${modifier.option_name}`}
                                                                className="mt-1 text-xs text-muted-foreground"
                                                            >
                                                                {modifier.modifier_name}:{" "}
                                                                {modifier.option_name}
                                                            </p>
                                                        ))}
                                                        {item.note && (
                                                            <p className="mt-1 text-xs font-semibold text-primary">
                                                                Catatan: {item.note}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="my-5 border-t" />
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                {order.payment_status === "paid"
                                                    ? "Total dibayar"
                                                    : "Total order"}
                                            </span>
                                            <strong>
                                                {formatMoney(order.grand_total, order.currency)}
                                            </strong>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => advance(order)}
                                            disabled={
                                                config.next === null || pendingOrderId === order.id
                                            }
                                            aria-busy={pendingOrderId === order.id}
                                            className="mt-5 flex min-h-12 w-full items-center justify-between rounded-full bg-foreground px-5 text-sm font-bold text-background transition-colors hover:bg-primary disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <span className="flex items-center gap-2">
                                                {order.status === "paid" && (
                                                    <BellRing
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                                {pendingOrderId === order.id
                                                    ? "Menyimpan..."
                                                    : config.action}
                                            </span>
                                            <ChevronRight className="size-4" aria-hidden="true" />
                                        </button>
                                    </div>
                                </article>
                            );
                        })}
                        {orders.length === 0 && (
                            <div className="col-span-full rounded-[1.5rem] border border-dashed bg-card p-16 text-center">
                                <UtensilsCrossed className="mx-auto size-8 text-muted-foreground" />
                                <p className="mt-4 font-bold">Belum ada order di status ini</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Order paid akan muncul setelah pembayaran terverifikasi.
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
