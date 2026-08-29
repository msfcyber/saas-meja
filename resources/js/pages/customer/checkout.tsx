import { Head, Link } from "@inertiajs/react";
import {
    ArrowLeft,
    ArrowRight,
    Check,
    LockKeyhole,
    Minus,
    Plus,
    QrCode,
    Smartphone,
    Trash2,
    WalletCards,
} from "lucide-react";
import { useEffect, useState } from "react";
import { CustomerHeader } from "@/components/customer-header";
import { formatCurrency, menuItems } from "@/data/demo";
import {
    clearCustomerCart,
    itemUnitPrice,
    loadCustomerCart,
    saveCustomerCart,
} from "@/lib/customer-cart";
import type { CustomerCartItem } from "@/types/customer";

type Props = {
    access?: { valid: boolean; message: string | null };
    qr_token?: string;
    outlet?: { name: string; currency: string } | null;
    table?: { name: string; code: string } | null;
    tax?: {
        enabled: boolean;
        name: string | null;
        rate_basis_points: number;
        inclusive: boolean;
    };
};

const paymentMethods = [
    {
        id: "qris",
        label: "QRIS",
        detail: "Scan dari semua aplikasi pembayaran",
        icon: QrCode,
    },
    {
        id: "ewallet",
        label: "E-wallet",
        detail: "GoPay, ShopeePay, atau DANA",
        icon: Smartphone,
    },
    {
        id: "va",
        label: "Virtual account",
        detail: "BCA, Mandiri, BNI, dan bank lainnya",
        icon: WalletCards,
    },
] as const;

const demoCartItems: CustomerCartItem[] = [
    {
        key: "demo-nasi",
        product_id: menuItems[0].id,
        variant_id: null,
        modifier_option_ids: [],
        quantity: 1,
        note: "Tanpa bawang",
        product: menuItems[0],
    },
    {
        key: "demo-kopi",
        product_id: menuItems[4].id,
        variant_id: null,
        modifier_option_ids: [],
        quantity: 2,
        note: null,
        product: menuItems[4],
    },
];

const makeIdempotencyKey = () =>
    `checkout-${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;

export default function Checkout({ access, qr_token, outlet, table, tax }: Props) {
    const isPublicCheckout = access !== undefined;
    const [cart, setCart] = useState<CustomerCartItem[]>(() =>
        qr_token ? loadCustomerCart(qr_token) : [],
    );
    const [customerName, setCustomerName] = useState("");
    const [payment, setPayment] = useState<(typeof paymentMethods)[number]["id"]>("qris");
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [idempotencyKey] = useState(makeIdempotencyKey);

    useEffect(() => {
        if (qr_token) {
            saveCustomerCart(qr_token, cart);
        }
    }, [cart, qr_token]);

    const activeCart = isPublicCheckout ? cart : demoCartItems;
    const subtotal = activeCart.reduce((sum, item) => sum + itemUnitPrice(item) * item.quantity, 0);
    const taxEnabled = isPublicCheckout ? tax?.enabled === true : true;
    const taxRate = isPublicCheckout ? (tax?.rate_basis_points ?? 0) : 1000;
    const taxInclusive = isPublicCheckout ? tax?.inclusive === true : false;
    const taxDenominator = taxInclusive ? 10000 + taxRate : 10000;
    const taxAmount = taxEnabled
        ? Math.floor((subtotal * taxRate + Math.floor(taxDenominator / 2)) / taxDenominator)
        : 0;
    const total = subtotal + (taxInclusive ? 0 : taxAmount);
    const itemCount = activeCart.reduce((sum, item) => sum + item.quantity, 0);

    const updateQuantity = (key: string, amount: number) => {
        setCart((current) =>
            current
                .map((item) =>
                    item.key === key
                        ? { ...item, quantity: Math.max(0, Math.min(50, item.quantity + amount)) }
                        : item,
                )
                .filter((item) => item.quantity > 0),
        );
    };

    const submitOrder = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!isPublicCheckout || !qr_token || cart.length === 0) {
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const response = await fetch("/api/public/orders", {
                method: "POST",
                headers: {
                    Accept: "application/json",
                    "Content-Type": "application/json",
                    "Idempotency-Key": idempotencyKey,
                },
                body: JSON.stringify({
                    qr_token,
                    customer_name: customerName.trim() || null,
                    payment_method: payment,
                    items: cart.map((item) => ({
                        product_id: item.product_id,
                        variant_id: item.variant_id,
                        modifier_option_ids: item.modifier_option_ids,
                        quantity: item.quantity,
                        note: item.note,
                    })),
                }),
            });
            const body: {
                tracking_url?: string;
                message?: string;
                errors?: Record<string, string[]>;
            } = await response.json();

            if (!response.ok || !body.tracking_url) {
                const validationMessage = body.errors ? Object.values(body.errors)[0]?.[0] : null;
                throw new Error(
                    validationMessage ?? body.message ?? "Checkout belum dapat diproses.",
                );
            }

            clearCustomerCart(qr_token);
            window.location.assign(body.tracking_url);
        } catch (exception) {
            setError(
                exception instanceof Error ? exception.message : "Checkout belum dapat diproses.",
            );
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Konfirmasi pesanan" />
            <div className="min-h-screen bg-background pb-28">
                <CustomerHeader minimal outletName={outlet?.name} tableName={table?.name} />
                <main className="mx-auto max-w-5xl px-4 py-7 sm:px-6 sm:py-12">
                    <Link
                        href={isPublicCheckout && qr_token ? `/q/${qr_token}` : "/demo/menu"}
                        className="inline-flex min-h-11 items-center gap-2 text-sm font-bold text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" /> Kembali ke menu
                    </Link>
                    <div className="mt-5 grid gap-8 lg:grid-cols-[1fr_0.72fr] lg:items-start">
                        <form id="checkout-form" onSubmit={submitOrder}>
                            <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                Langkah terakhir
                            </p>
                            <h1 className="font-display mt-2 text-4xl font-bold tracking-tight sm:text-5xl">
                                Periksa pesananmu.
                            </h1>
                            <p className="mt-3 text-muted-foreground">
                                {outlet?.name ?? "Kedai Sore"} · {table?.name ?? "Meja 08"}
                            </p>

                            <section className="mt-8 overflow-hidden rounded-[1.5rem] border bg-card">
                                <div className="flex items-center justify-between border-b px-5 py-4">
                                    <h2 className="font-bold">Pesanan kamu</h2>
                                    <span className="text-xs text-muted-foreground">
                                        {itemCount} item
                                    </span>
                                </div>
                                {activeCart.map((item) => (
                                    <div
                                        key={item.key}
                                        className="flex gap-4 border-b p-5 last:border-b-0"
                                    >
                                        <div className="size-20 shrink-0 overflow-hidden rounded-2xl bg-muted sm:size-24">
                                            {item.product.image ? (
                                                <img
                                                    src={item.product.image}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center text-primary">
                                                    <QrCode className="size-7" aria-hidden="true" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <div className="flex justify-between gap-3">
                                                <div>
                                                    <h3 className="font-bold">
                                                        {item.product.name}
                                                    </h3>
                                                    {(item.product.variants?.find(
                                                        (variant) => variant.id === item.variant_id,
                                                    )?.name ||
                                                        item.note) && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {
                                                                item.product.variants?.find(
                                                                    (variant) =>
                                                                        variant.id ===
                                                                        item.variant_id,
                                                                )?.name
                                                            }
                                                            {item.note && ` · ${item.note}`}
                                                        </p>
                                                    )}
                                                </div>
                                                <p className="shrink-0 text-sm font-bold">
                                                    {formatCurrency(
                                                        itemUnitPrice(item) * item.quantity,
                                                    )}
                                                </p>
                                            </div>
                                            {isPublicCheckout && (
                                                <div className="mt-4 flex items-center justify-between gap-3">
                                                    <div className="flex w-fit items-center rounded-full border p-0.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                updateQuantity(item.key, -1)
                                                            }
                                                            className="flex size-9 items-center justify-center rounded-full hover:bg-secondary"
                                                            aria-label={`Kurangi ${item.product.name}`}
                                                        >
                                                            <Minus className="size-3.5" />
                                                        </button>
                                                        <span className="w-7 text-center text-sm font-bold">
                                                            {item.quantity}
                                                        </span>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                updateQuantity(item.key, 1)
                                                            }
                                                            className="flex size-9 items-center justify-center rounded-full hover:bg-secondary"
                                                            aria-label={`Tambah ${item.product.name}`}
                                                        >
                                                            <Plus className="size-3.5" />
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setCart((current) =>
                                                                current.filter(
                                                                    (entry) =>
                                                                        entry.key !== item.key,
                                                                ),
                                                            )
                                                        }
                                                        className="inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-bold text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                                    >
                                                        <Trash2
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />{" "}
                                                        Hapus
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {isPublicCheckout && activeCart.length === 0 && (
                                    <div className="p-8 text-center text-sm text-muted-foreground">
                                        Keranjang masih kosong. Kembali ke menu untuk memilih
                                        hidangan.
                                    </div>
                                )}
                            </section>

                            <section className="mt-6 rounded-[1.5rem] border bg-card p-5 sm:p-6">
                                <h2 className="font-bold">
                                    Nama pemesan{" "}
                                    <span className="font-normal text-muted-foreground">
                                        (opsional)
                                    </span>
                                </h2>
                                <input
                                    value={customerName}
                                    onChange={(event) => setCustomerName(event.target.value)}
                                    className="mt-4 min-h-12 w-full rounded-xl border bg-background px-4 text-sm outline-none focus:ring-2 focus:ring-ring"
                                    placeholder="Agar staf mudah memanggilmu"
                                    maxLength={120}
                                />
                            </section>

                            <section className="mt-6 rounded-[1.5rem] border bg-card p-5 sm:p-6">
                                <h2 className="font-bold">Pilih pembayaran</h2>
                                <div className="mt-4 grid gap-3">
                                    {paymentMethods.map((method) => (
                                        <button
                                            key={method.id}
                                            type="button"
                                            onClick={() => setPayment(method.id)}
                                            aria-pressed={payment === method.id}
                                            className={`flex min-h-16 items-center gap-3 rounded-2xl border p-3 text-left transition-colors ${payment === method.id ? "border-primary bg-primary/6" : "hover:bg-secondary/60"}`}
                                        >
                                            <span
                                                className={`flex size-11 items-center justify-center rounded-xl ${payment === method.id ? "bg-primary text-primary-foreground" : "bg-secondary"}`}
                                            >
                                                <method.icon
                                                    className="size-5"
                                                    aria-hidden="true"
                                                />
                                            </span>
                                            <span className="flex-1">
                                                <span className="block text-sm font-bold">
                                                    {method.label}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {method.detail}
                                                </span>
                                            </span>
                                            <span
                                                className={`flex size-5 items-center justify-center rounded-full border ${payment === method.id ? "border-primary bg-primary text-white" : ""}`}
                                            >
                                                {payment === method.id && (
                                                    <Check className="size-3" aria-hidden="true" />
                                                )}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {isPublicCheckout && (
                                    <p className="mt-4 rounded-xl bg-secondary/60 p-3 text-xs leading-5 text-muted-foreground">
                                        Pembayaran akan dibuat sebagai transaksi tertunda. Order
                                        baru masuk ke antrean setelah pembayaran diverifikasi
                                        server.
                                    </p>
                                )}
                            </section>
                        </form>

                        <aside className="rounded-[1.75rem] bg-[#283025] p-6 text-[#fffaf0] shadow-[0_30px_80px_-50px_rgba(40,48,37,0.8)] lg:sticky lg:top-24 sm:p-7">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#dfa281] uppercase">
                                Ringkasan pembayaran
                            </p>
                            <dl className="mt-6 space-y-4 text-sm">
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Subtotal</dt>
                                    <dd>{formatCurrency(subtotal)}</dd>
                                </div>
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>
                                        {taxEnabled ? (tax?.name ?? "Pajak restoran") : "Pajak"}
                                        {taxEnabled && ` (${taxRate / 100}%)`}
                                    </dt>
                                    <dd>{formatCurrency(taxAmount)}</dd>
                                </div>
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Biaya layanan</dt>
                                    <dd>Rp0</dd>
                                </div>
                            </dl>
                            <div className="my-6 border-t border-white/15" />
                            <div className="flex items-end justify-between gap-4">
                                <span className="font-bold">Total</span>
                                <span className="font-display text-3xl font-bold">
                                    {formatCurrency(total)}
                                </span>
                            </div>
                            {error && (
                                <p
                                    className="mt-5 rounded-xl bg-red-400/15 p-3 text-sm font-semibold text-red-100"
                                    role="alert"
                                >
                                    {error}
                                </p>
                            )}
                            {isPublicCheckout ? (
                                <button
                                    type="submit"
                                    form="checkout-form"
                                    disabled={processing || activeCart.length === 0}
                                    className="mt-7 flex min-h-13 w-full items-center justify-between rounded-full bg-[#d87655] px-5 text-sm font-bold text-white transition-colors hover:bg-[#c96546] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing ? "Memproses..." : "Lanjutkan pembayaran"}{" "}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </button>
                            ) : (
                                <Link
                                    href="/demo/tracking"
                                    className="mt-7 flex min-h-13 items-center justify-between rounded-full bg-[#d87655] px-5 text-sm font-bold text-white transition-colors hover:bg-[#c96546]"
                                >
                                    Bayar sekarang{" "}
                                    <ArrowRight className="size-4" aria-hidden="true" />
                                </Link>
                            )}
                            <p className="mt-5 flex items-center justify-center gap-2 text-center text-xs text-[#bfc5b7]">
                                <LockKeyhole className="size-3.5" aria-hidden="true" /> Pembayaran
                                aman dan terenkripsi
                            </p>
                        </aside>
                    </div>
                </main>
            </div>
        </>
    );
}
