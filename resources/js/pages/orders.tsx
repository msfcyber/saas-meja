import { Head, router } from '@inertiajs/react';
import {
    Activity,
    BellOff,
    BellRing,
    CheckCircle2,
    ChevronRight,
    Clock3,
    Filter,
    ReceiptText,
    Search,
    UtensilsCrossed,
    Undo2,
    Volume2,
    VolumeX,
} from 'lucide-react';
import { useEffect, useEffectEvent, useRef, useState } from 'react';
import { toast } from 'sonner';
import { Input } from '@/components/ui/input';
import { useRealtime } from '@/hooks/use-realtime';

type OrderStatus =
    | 'paid'
    | 'accepted'
    | 'preparing'
    | 'ready'
    | 'served'
    | 'completed';
type FilterStatus = 'active' | OrderStatus;

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
    { next: OrderStatus | null; action: string; color: string; accent: string }
> = {
    paid: {
        next: 'accepted',
        action: 'Terima order',
        color: 'border-primary/30 bg-primary/10 text-primary dark:border-primary/40 dark:bg-primary/15',
        accent: 'border-t-primary',
    },
    accepted: {
        next: 'preparing',
        action: 'Mulai siapkan',
        color: 'border-sky-500/25 bg-sky-500/10 text-sky-700 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-300',
        accent: 'border-t-sky-500',
    },
    preparing: {
        next: 'ready',
        action: 'Tandai siap',
        color: 'border-violet-500/25 bg-violet-500/10 text-violet-700 dark:border-violet-400/30 dark:bg-violet-400/10 dark:text-violet-300',
        accent: 'border-t-violet-500',
    },
    ready: {
        next: 'served',
        action: 'Sudah disajikan',
        color: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300',
        accent: 'border-t-emerald-500',
    },
    served: {
        next: 'completed',
        action: 'Selesaikan',
        color: 'border-border bg-secondary text-secondary-foreground',
        accent: 'border-t-secondary-foreground/40',
    },
    completed: {
        next: null,
        action: 'Selesai',
        color: 'border-border bg-muted text-muted-foreground',
        accent: 'border-t-border',
    },
};

const filterOptions: Array<{ id: FilterStatus; label: string }> = [
    { id: 'active', label: 'Semua aktif' },
    { id: 'paid', label: 'Baru' },
    { id: 'accepted', label: 'Diterima' },
    { id: 'preparing', label: 'Disiapkan' },
    { id: 'ready', label: 'Siap' },
    { id: 'served', label: 'Disajikan' },
    { id: 'completed', label: 'Selesai' },
];

function formatMoney(value: number, currency: string): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    }).format(value);
}

function formatCount(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value);
}

function formatAge(createdAt: string): string {
    const totalSeconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(createdAt).getTime()) / 1000),
    );
    const minutes = Math.floor(totalSeconds / 60);

    if (minutes < 60) {
        return `${String(minutes).padStart(2, '0')}:${String(totalSeconds % 60).padStart(2, '0')}`;
    }

    return `${Math.floor(minutes / 60)}j ${String(minutes % 60).padStart(2, '0')}m`;
}

function isNewOrderEvent(payload: unknown): payload is { order: StaffOrder } {
    if (
        typeof payload !== 'object' ||
        payload === null ||
        !('order' in payload)
    ) {
        return false;
    }

    const order = (payload as { order?: unknown }).order;

    return (
        typeof order === 'object' &&
        order !== null &&
        'id' in order &&
        'number' in order &&
        'status' in order
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
    const [pendingRefundOrderId, setPendingRefundOrderId] = useState<
        number | null
    >(null);
    const [notificationPreferences, setNotificationPreferences] =
        useState<NotificationPreferences>(notifications);
    const [liveAnnouncement, setLiveAnnouncement] = useState('');
    const audioContext = useRef<AudioContext | null>(null);
    const announcedOrderIds = useRef(new Set<number>());
    const hasLoadedNotificationOrders = useRef(false);

    const [, refreshAge] = useState(0);

    useEffect(() => {
        setNotificationPreferences(notifications);
    }, [notifications]);

    useEffect(() => {
        const timer = window.setInterval(() => refreshAge(Date.now()), 1_000);

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        return () => {
            const context = audioContext.current;
            audioContext.current = null;
            void context?.close();
        };
    }, []);

    function getAudioContext(): AudioContext | null {
        if (typeof window === 'undefined' || !window.AudioContext) {
            return null;
        }

        const context = audioContext.current ?? new window.AudioContext();
        audioContext.current = context;

        if (context.state === 'suspended') {
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

            oscillator.type = 'sine';
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

        document.addEventListener('pointerdown', onPointerDown, { once: true });

        return () => document.removeEventListener('pointerdown', onPointerDown);
    }, [notificationPreferences.sound_enabled]);

    const announceNewOrder = useEffectEvent((order: OrderNotification) => {
        if (announcedOrderIds.current.has(order.id)) {
            return;
        }

        announcedOrderIds.current.add(order.id);
        const table = order.table_name ? ` untuk ${order.table_name}` : '';
        const message = `Order baru #${order.number}${table}.`;

        setLiveAnnouncement(message);

        if (notificationPreferences.visual_enabled) {
            toast('Order baru masuk', {
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
            only: ['orders', 'counts', 'notification_orders'],
        });
    }

    const realtimeStatus = useRealtime({
        enabled: Boolean(realtime?.channel),
        channel: realtime?.channel ?? '',
        channelType: 'private',
        event: '.order.status.updated',
        onEvent: (payload) => {
            if (isNewOrderEvent(payload) && payload.order.status === 'paid') {
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
        realtimeStatus === 'connected'
            ? 'Realtime'
            : realtimeStatus === 'polling'
              ? 'Polling'
              : realtimeStatus === 'offline'
                ? 'Offline'
                : realtimeStatus === 'disabled'
                  ? 'Database'
                  : 'Menghubungkan';
    const realtimeTone =
        realtimeStatus === 'connected'
            ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300'
            : realtimeStatus === 'offline'
              ? 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200'
              : 'border-border bg-secondary text-muted-foreground';
    const realtimeDot =
        realtimeStatus === 'connected'
            ? 'bg-emerald-500'
            : realtimeStatus === 'offline'
              ? 'bg-amber-500'
              : 'bg-current';
    const queueMetrics = [
        {
            label: 'Aktif',
            value: counts.active,
            detail: 'Berjalan sekarang',
            icon: Activity,
            tone: 'bg-primary/10 text-primary',
        },
        {
            label: 'Order baru',
            value: counts.paid,
            detail: 'Perlu diterima',
            icon: BellRing,
            tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
        },
        {
            label: 'Siap disajikan',
            value: counts.ready,
            detail: 'Menunggu tamu',
            icon: CheckCircle2,
            tone: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        },
    ];
    const selectedFilterLabel =
        filterOptions.find((option) => option.id === filters.status)?.label ??
        'status ini';

    function applyFilters(status: FilterStatus = filters.status) {
        router.get(
            '/orders',
            {
                search: search || undefined,
                status: status === 'active' ? undefined : status,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    function updateNotificationPreferences(next: NotificationPreferences) {
        const previous = notificationPreferences;

        setNotificationPreferences(next);
        router.put('/orders/notifications', next, {
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
                onError: (errors) => {
                    const message = Object.values(errors)[0];

                    toast.error('Status order gagal diperbarui', {
                        description:
                            message ?? 'Coba lagi dalam beberapa saat.',
                    });
                },
            },
        );
    }

    function refundOrder(order: StaffOrder) {
        const reason = window
            .prompt(
                `Alasan refund penuh untuk order ${order.number}`,
                'Permintaan refund manual',
            )
            ?.trim();

        if (!reason) {
            return;
        }

        setPendingRefundOrderId(order.id);
        router.post(
            `/orders/${order.id}/refund`,
            { reason },
            {
                headers: { 'Idempotency-Key': crypto.randomUUID() },
                preserveScroll: true,
                onFinish: () => setPendingRefundOrderId(null),
                onError: (errors) => {
                    const message = Object.values(errors)[0];

                    toast.error('Refund gagal diproses', {
                        description:
                            message ?? 'Coba lagi dalam beberapa saat.',
                    });
                },
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
                <header className="border-border/70 bg-card/85 border-b px-4 py-5 backdrop-blur-xl sm:px-6 lg:px-8">
                    <div className="mx-auto max-w-[1600px]">
                        <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-3">
                                    <span className="bg-primary/10 text-primary inline-flex size-10 shrink-0 items-center justify-center rounded-2xl">
                                        <UtensilsCrossed
                                            className="size-5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                    <div>
                                        <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                                            Operasional outlet
                                        </p>
                                        <h1 className="font-display mt-1 text-3xl font-bold tracking-tight sm:text-4xl">
                                            Live order
                                        </h1>
                                    </div>
                                    <span
                                        role="status"
                                        aria-live="polite"
                                        aria-atomic="true"
                                        className={`inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1.5 text-[11px] font-bold ${realtimeTone}`}
                                    >
                                        <span
                                            className={`size-1.5 rounded-full ${realtimeDot}`}
                                            aria-hidden="true"
                                        />
                                        {realtimeLabel}
                                    </span>
                                </div>
                                <p className="text-muted-foreground mt-3 max-w-2xl text-sm">
                                    Pantau order masuk, jaga ritme dapur, dan
                                    selesaikan antrean tanpa kehilangan konteks
                                    meja.
                                </p>
                                <p className="text-muted-foreground mt-2 text-xs font-semibold">
                                    <span className="text-foreground">
                                        {outlet.name}
                                    </span>
                                    <span className="mx-2" aria-hidden="true">
                                        ·
                                    </span>
                                    {counts.active} order aktif
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 xl:max-w-[31rem] xl:justify-end">
                                <button
                                    type="button"
                                    onClick={() =>
                                        updateNotificationPreferences({
                                            ...notificationPreferences,
                                            visual_enabled:
                                                !notificationPreferences.visual_enabled,
                                        })
                                    }
                                    className="border-border/80 bg-background text-foreground hover:bg-secondary inline-flex min-h-11 items-center gap-2 rounded-xl border px-3.5 text-xs font-bold transition-colors sm:px-4"
                                    aria-pressed={
                                        notificationPreferences.visual_enabled
                                    }
                                >
                                    {notificationPreferences.visual_enabled ? (
                                        <BellRing
                                            className="text-primary size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <BellOff
                                            className="text-muted-foreground size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Visual{' '}
                                    {notificationPreferences.visual_enabled
                                        ? 'aktif'
                                        : 'mati'}
                                </button>
                                <button
                                    type="button"
                                    onClick={toggleSound}
                                    className="border-border/80 bg-background text-foreground hover:bg-secondary inline-flex min-h-11 items-center gap-2 rounded-xl border px-3.5 text-xs font-bold transition-colors sm:px-4"
                                    aria-pressed={
                                        notificationPreferences.sound_enabled
                                    }
                                >
                                    {notificationPreferences.sound_enabled ? (
                                        <Volume2
                                            className="text-primary size-4"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <VolumeX
                                            className="text-muted-foreground size-4"
                                            aria-hidden="true"
                                        />
                                    )}
                                    Suara{' '}
                                    {notificationPreferences.sound_enabled
                                        ? 'aktif'
                                        : 'mati'}
                                </button>
                            </div>
                        </div>

                        <div className="border-border/70 mt-5 flex flex-col gap-3 border-t pt-4 lg:flex-row lg:items-center">
                            <form
                                className="relative min-w-0 flex-1"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    applyFilters();
                                }}
                            >
                                <Search
                                    className="text-muted-foreground absolute top-1/2 left-4 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <label
                                    className="sr-only"
                                    htmlFor="order-search"
                                >
                                    Cari order atau meja
                                </label>
                                <Input
                                    id="order-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="border-border/80 bg-background min-h-11 rounded-xl pr-4 pl-10"
                                    placeholder="Cari order atau meja..."
                                />
                            </form>
                            <button
                                type="button"
                                onClick={() => applyFilters()}
                                className="border-border/80 bg-background text-foreground hover:bg-secondary inline-flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4 text-xs font-bold transition-colors"
                            >
                                <Filter className="size-4" aria-hidden="true" />
                                Terapkan filter
                            </button>
                        </div>
                    </div>
                </header>

                <div className="mx-auto w-full max-w-[1600px] flex-1 p-4 sm:p-6 lg:p-8">
                    <section
                        aria-labelledby="queue-snapshot-title"
                        className="border-border/70 bg-card rounded-[1.5rem] border p-4 shadow-[0_18px_60px_-45px_rgba(48,39,28,0.75)] sm:p-5"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                                    Snapshot shift
                                </p>
                                <h2
                                    id="queue-snapshot-title"
                                    className="font-display mt-1 text-xl font-bold tracking-tight"
                                >
                                    Antrean hari ini
                                </h2>
                            </div>
                            <span className="bg-secondary text-secondary-foreground inline-flex min-h-8 items-center rounded-full px-3 text-xs font-bold">
                                {formatCount(orders.length)} ditampilkan
                            </span>
                        </div>

                        <div className="mt-5 grid grid-cols-3 gap-2 sm:gap-3">
                            {queueMetrics.map((metric) => (
                                <div
                                    key={metric.label}
                                    className="border-border/60 bg-background/60 min-w-0 rounded-2xl border p-3 sm:p-4"
                                >
                                    <div className="flex items-center gap-2">
                                        <span
                                            className={`inline-flex size-8 shrink-0 items-center justify-center rounded-xl ${metric.tone}`}
                                        >
                                            <metric.icon
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        </span>
                                        <span className="text-muted-foreground truncate text-[10px] font-bold tracking-[0.08em] uppercase sm:text-xs">
                                            {metric.label}
                                        </span>
                                    </div>
                                    <p className="font-display mt-3 text-2xl font-bold tabular-nums sm:text-3xl">
                                        {formatCount(metric.value)}
                                    </p>
                                    <p className="text-muted-foreground mt-1 hidden text-xs sm:block">
                                        {metric.detail}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section aria-label="Filter status order" className="mt-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                                    Antrean kerja
                                </p>
                                <p className="text-muted-foreground mt-1 text-sm">
                                    Pilih tahap untuk fokus pada order yang
                                    perlu ditangani.
                                </p>
                            </div>
                            <fieldset className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                                <legend className="sr-only">
                                    Filter berdasarkan status
                                </legend>
                                {filterOptions.map((option) => (
                                    <button
                                        key={option.id}
                                        type="button"
                                        onClick={() => applyFilters(option.id)}
                                        aria-pressed={
                                            filters.status === option.id
                                        }
                                        className={`inline-flex min-h-11 shrink-0 items-center gap-2 rounded-xl px-3.5 text-xs font-bold transition-colors ${filters.status === option.id ? 'bg-foreground text-background shadow-sm' : 'border-border/80 bg-card text-foreground hover:bg-secondary border'}`}
                                    >
                                        {option.label}
                                        <span className="bg-background/20 min-w-5 rounded-full px-1.5 py-0.5 text-center text-[11px] tabular-nums opacity-80">
                                            {counts[option.id]}
                                        </span>
                                    </button>
                                ))}
                            </fieldset>
                        </div>
                    </section>

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
                                    className={`bg-card overflow-hidden rounded-[1.5rem] border border-t-4 shadow-[0_16px_50px_-40px_rgba(48,39,28,0.7)] transition-shadow duration-200 hover:shadow-[0_20px_58px_-38px_rgba(48,39,28,0.8)] ${config.accent} ${order.status === 'paid' ? 'ring-primary/20 ring-1' : ''}`}
                                >
                                    <header className="border-border/70 flex items-start justify-between gap-3 border-b px-5 py-4">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span
                                                role="img"
                                                className="bg-secondary text-primary inline-flex size-12 shrink-0 items-center justify-center rounded-2xl text-lg font-bold"
                                                title={
                                                    order.table
                                                        ? `Meja ${order.table.name}`
                                                        : 'Tanpa meja'
                                                }
                                                aria-label={
                                                    order.table
                                                        ? `Meja ${order.table.name}`
                                                        : 'Order tanpa meja'
                                                }
                                            >
                                                {order.table?.name.replace(
                                                    /^Meja\s+/i,
                                                    '',
                                                ) ?? '-'}
                                            </span>
                                            <div className="min-w-0">
                                                <h2 className="truncate font-bold">
                                                    {order.number}
                                                </h2>
                                                <p className="text-muted-foreground mt-1 truncate text-xs">
                                                    {order.customer_name ??
                                                        'Guest'}{' '}
                                                    · {itemCount} item
                                                </p>
                                                <p
                                                    className={`mt-1 truncate text-[11px] font-semibold ${order.payment_status === 'paid' ? 'text-emerald-700 dark:text-emerald-300' : 'text-muted-foreground'}`}
                                                >
                                                    Pembayaran{' '}
                                                    {order.payment_status ===
                                                    'paid'
                                                        ? 'lunas'
                                                        : (order.payment_status ??
                                                          'belum diverifikasi')}
                                                </p>
                                            </div>
                                        </div>
                                        <span
                                            className={`shrink-0 rounded-full border px-2.5 py-1 text-[10px] font-bold ${config.color}`}
                                        >
                                            {order.status_label}
                                        </span>
                                    </header>
                                    <div className="p-5">
                                        <div className="bg-muted/55 border-border/50 flex items-center justify-between rounded-2xl border px-3.5 py-3 text-xs">
                                            <span className="text-muted-foreground flex items-center gap-2 font-bold">
                                                <Clock3
                                                    className="text-primary size-3.5"
                                                    aria-hidden="true"
                                                />
                                                Usia order
                                            </span>
                                            <strong className="tabular-nums">
                                                {formatAge(order.created_at)}
                                            </strong>
                                        </div>
                                        <div className="mt-5">
                                            <p className="text-muted-foreground text-[10px] font-bold tracking-[0.16em] uppercase">
                                                Isi order
                                            </p>
                                        </div>
                                        <div className="mt-3 space-y-3 text-sm">
                                            {order.items.map((item) => (
                                                <div
                                                    key={item.id}
                                                    className="border-border/60 flex gap-3 border-b pb-3 last:border-b-0 last:pb-0"
                                                >
                                                    <span className="bg-secondary text-primary inline-flex size-7 shrink-0 items-center justify-center rounded-lg text-xs font-bold">
                                                        {item.quantity}x
                                                    </span>
                                                    <div className="min-w-0">
                                                        <p className="font-bold">
                                                            {item.product_name}
                                                        </p>
                                                        {item.variant_name && (
                                                            <p className="text-muted-foreground mt-1 text-xs">
                                                                {
                                                                    item.variant_name
                                                                }
                                                            </p>
                                                        )}
                                                        {item.modifiers.map(
                                                            (modifier) => (
                                                                <p
                                                                    key={`${item.id}-${modifier.modifier_name}-${modifier.option_name}`}
                                                                    className="text-muted-foreground mt-1 text-xs"
                                                                >
                                                                    {
                                                                        modifier.modifier_name
                                                                    }
                                                                    :{' '}
                                                                    {
                                                                        modifier.option_name
                                                                    }
                                                                </p>
                                                            ),
                                                        )}
                                                        {item.note && (
                                                            <p className="text-primary mt-1 text-xs font-semibold">
                                                                Catatan:{' '}
                                                                {item.note}
                                                            </p>
                                                        )}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="border-border/70 my-5 border-t" />
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground text-xs">
                                                {order.payment_status === 'paid'
                                                    ? 'Total dibayar'
                                                    : 'Total order'}
                                            </span>
                                            <strong className="text-base tabular-nums">
                                                {formatMoney(
                                                    order.grand_total,
                                                    order.currency,
                                                )}
                                            </strong>
                                        </div>
                                        <div className="mt-5 grid gap-2">
                                            {order.payment_status ===
                                                'paid' && (
                                                <a
                                                    href={`/orders/${order.id}/receipt`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    aria-label={`Cetak struk order ${order.number}`}
                                                    className="border-border/80 bg-background text-foreground hover:bg-secondary inline-flex min-h-10 items-center justify-center gap-2 rounded-xl border text-xs font-bold transition-colors"
                                                >
                                                    <ReceiptText
                                                        className="size-3.5"
                                                        aria-hidden="true"
                                                    />
                                                    Cetak struk
                                                </a>
                                            )}
                                            {order.payment_status === 'paid' &&
                                                order.status === 'paid' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            refundOrder(order)
                                                        }
                                                        disabled={
                                                            pendingRefundOrderId ===
                                                            order.id
                                                        }
                                                        aria-busy={
                                                            pendingRefundOrderId ===
                                                            order.id
                                                        }
                                                        className="border-destructive/30 bg-destructive/5 text-destructive hover:bg-destructive/10 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border text-xs font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                                    >
                                                        <Undo2
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        {pendingRefundOrderId ===
                                                        order.id
                                                            ? 'Mengirim refund...'
                                                            : 'Refund penuh'}
                                                    </button>
                                                )}
                                            <button
                                                type="button"
                                                onClick={() => advance(order)}
                                                disabled={
                                                    config.next === null ||
                                                    pendingOrderId === order.id
                                                }
                                                aria-busy={
                                                    pendingOrderId === order.id
                                                }
                                                aria-label={`${pendingOrderId === order.id ? 'Menyimpan order' : config.action} ${order.number}`}
                                                className={`inline-flex min-h-12 w-full items-center justify-between rounded-xl px-5 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${config.next === null ? 'bg-muted text-muted-foreground' : 'bg-foreground text-background hover:bg-primary'}`}
                                            >
                                                <span className="flex items-center gap-2">
                                                    {order.status ===
                                                        'paid' && (
                                                        <BellRing
                                                            className="size-4"
                                                            aria-hidden="true"
                                                        />
                                                    )}
                                                    {pendingOrderId === order.id
                                                        ? 'Menyimpan...'
                                                        : config.action}
                                                </span>
                                                <ChevronRight
                                                    className="size-4"
                                                    aria-hidden="true"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            );
                        })}
                        {orders.length === 0 && (
                            <div className="border-border/80 bg-card col-span-full rounded-[1.5rem] border border-dashed p-12 text-center sm:p-16">
                                <span className="bg-primary/10 text-primary inline-flex size-12 items-center justify-center rounded-2xl">
                                    <UtensilsCrossed
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p className="mt-4 font-bold">
                                    {filters.status === 'active'
                                        ? 'Antrean aktif sedang kosong'
                                        : `Belum ada order ${selectedFilterLabel.toLowerCase()}.`}
                                </p>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    {filters.status === 'active'
                                        ? 'Semua order yang berjalan sudah tertangani.'
                                        : 'Coba pilih status lain untuk melihat antrean yang berbeda.'}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Orders.layout = { breadcrumbs: [{ title: 'Live order', href: '/orders' }] };
