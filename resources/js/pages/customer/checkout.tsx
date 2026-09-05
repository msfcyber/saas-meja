import { Head, Link } from '@inertiajs/react';
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
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { CustomerHeader } from '@/components/customer-header';
import { trackAnalytics } from '@/hooks/use-analytics';
import { formatCurrency, menuItems } from '@/data/demo';
import {
    clearCustomerCart,
    itemUnitPrice,
    loadCustomerCart,
    saveCustomerCart,
} from '@/lib/customer-cart';
import type { CustomerCartItem } from '@/types/customer';

type Props = {
    access?: { valid: boolean; message: string | null };
    qr_token?: string;
    analytics_token?: string;
    outlet?: { name: string; currency: string } | null;
    table?: { name: string; code: string } | null;
    tax?: {
        enabled: boolean;
        name: string | null;
        rate_basis_points: number;
        inclusive: boolean;
    };
};

type PaymentMethodId = (typeof paymentMethods)[number]['id'];

type ServerQuoteItem = {
    product_id: number;
    variant_id: number | null;
    modifier_option_ids: number[];
    quantity: number;
    note: string | null;
    product_name: string;
    variant_name: string | null;
    unit_price: number;
    line_total: number;
};

type ServerQuote = {
    items: ServerQuoteItem[];
    subtotal: number;
    discount_amount: number;
    tax_name: string | null;
    tax_rate_basis_points: number;
    tax_inclusive: boolean;
    tax_amount: number;
    fee_amount: number;
    grand_total: number;
    currency: string;
    fingerprint: string;
};

const paymentMethods = [
    {
        id: 'qris',
        label: 'QRIS',
        detail: 'Scan dari semua aplikasi pembayaran',
        icon: QrCode,
    },
    {
        id: 'ewallet',
        label: 'E-wallet',
        detail: 'GoPay atau ShopeePay',
        icon: Smartphone,
    },
    {
        id: 'va',
        label: 'Virtual account',
        detail: 'BCA, Mandiri, BNI, dan bank lainnya',
        icon: WalletCards,
    },
] as const;

const demoCartItems: CustomerCartItem[] = [
    {
        key: 'demo-nasi',
        product_id: menuItems[0].id,
        variant_id: null,
        modifier_option_ids: [],
        quantity: 1,
        note: 'Tanpa bawang',
        product: menuItems[0],
    },
    {
        key: 'demo-kopi',
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

const CHECKOUT_REQUEST_TIMEOUT_MS = 15_000;

async function fetchWithTimeout(
    input: RequestInfo | URL,
    init: RequestInit = {},
): Promise<Response> {
    const controller = new AbortController();
    const timeout = window.setTimeout(
        () => controller.abort(),
        CHECKOUT_REQUEST_TIMEOUT_MS,
    );

    try {
        return await fetch(input, { ...init, signal: controller.signal });
    } finally {
        window.clearTimeout(timeout);
    }
}

function cartPayload(cart: CustomerCartItem[]) {
    return cart.map((item) => ({
        product_id: item.product_id,
        variant_id: item.variant_id,
        modifier_option_ids: item.modifier_option_ids,
        quantity: item.quantity,
        note: item.note,
    }));
}

function quoteMatchesCart(
    quote: ServerQuote,
    cart: CustomerCartItem[],
    subtotal: number,
    taxAmount: number,
    total: number,
): boolean {
    if (
        quote.items.length !== cart.length ||
        quote.subtotal !== subtotal ||
        quote.tax_amount !== taxAmount ||
        quote.grand_total !== total
    ) {
        return false;
    }

    return quote.items.every((serverItem, index) => {
        const localItem = cart[index];

        if (!localItem) {
            return false;
        }

        const localVariant = localItem.product.variants?.find(
            (variant) => variant.id === localItem.variant_id,
        );

        return (
            serverItem.product_id === localItem.product_id &&
            serverItem.variant_id === localItem.variant_id &&
            [...serverItem.modifier_option_ids]
                .sort((a, b) => a - b)
                .join(',') ===
                [...localItem.modifier_option_ids]
                    .sort((a, b) => a - b)
                    .join(',') &&
            serverItem.quantity === localItem.quantity &&
            (serverItem.note ?? '').trim() === (localItem.note ?? '').trim() &&
            serverItem.product_name === localItem.product.name &&
            serverItem.variant_name === (localVariant?.name ?? null) &&
            serverItem.unit_price === itemUnitPrice(localItem) &&
            serverItem.line_total ===
                itemUnitPrice(localItem) * localItem.quantity
        );
    });
}

export default function Checkout({
    access,
    qr_token,
    analytics_token,
    outlet,
    table,
    tax,
}: Props) {
    const isPublicCheckout = access !== undefined;
    const [cart, setCart] = useState<CustomerCartItem[]>(() =>
        qr_token ? loadCustomerCart(qr_token) : [],
    );
    const [customerName, setCustomerName] = useState('');
    const [payment, setPayment] = useState<PaymentMethodId>('qris');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [serverQuote, setServerQuote] = useState<ServerQuote | null>(null);
    const [priceConfirmationRequired, setPriceConfirmationRequired] =
        useState(false);
    const [idempotencyKey] = useState(makeIdempotencyKey);

    useEffect(() => {
        if (qr_token) {
            saveCustomerCart(qr_token, cart);
        }
    }, [cart, qr_token]);

    useEffect(() => {
        if (isPublicCheckout && qr_token && analytics_token) {
            trackAnalytics('checkout_started', {
                qrToken: qr_token,
                analyticsToken: analytics_token,
            });
        }
    }, [analytics_token, isPublicCheckout, qr_token]);

    const activeCart = isPublicCheckout ? cart : demoCartItems;
    const subtotal = activeCart.reduce(
        (sum, item) => sum + itemUnitPrice(item) * item.quantity,
        0,
    );
    const taxEnabled = isPublicCheckout ? tax?.enabled === true : true;
    const taxRate = isPublicCheckout ? (tax?.rate_basis_points ?? 0) : 1000;
    const taxInclusive = isPublicCheckout ? tax?.inclusive === true : false;
    const taxDenominator = taxInclusive ? 10000 + taxRate : 10000;
    const taxAmount = taxEnabled
        ? Math.floor(
              (subtotal * taxRate + Math.floor(taxDenominator / 2)) /
                  taxDenominator,
          )
        : 0;
    const total = subtotal + (taxInclusive ? 0 : taxAmount);
    const itemCount = activeCart.reduce((sum, item) => sum + item.quantity, 0);
    const displaySubtotal = serverQuote?.subtotal ?? subtotal;
    const displayTaxEnabled = serverQuote
        ? serverQuote.tax_rate_basis_points > 0
        : taxEnabled;
    const displayTaxRate = serverQuote?.tax_rate_basis_points ?? taxRate;
    const displayTaxAmount = serverQuote?.tax_amount ?? taxAmount;
    const displayTotal = serverQuote?.grand_total ?? total;

    const updateQuantity = (key: string, amount: number) => {
        setServerQuote(null);
        setPriceConfirmationRequired(false);
        setCart((current) =>
            current
                .map((item) =>
                    item.key === key
                        ? {
                              ...item,
                              quantity: Math.max(
                                  0,
                                  Math.min(50, item.quantity + amount),
                              ),
                          }
                        : item,
                )
                .filter((item) => item.quantity > 0),
        );
    };

    const removeItem = (key: string) => {
        setServerQuote(null);
        setPriceConfirmationRequired(false);
        setCart((current) => current.filter((item) => item.key !== key));
    };

    const submitOrder = async (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!isPublicCheckout || !qr_token || cart.length === 0) {
            return;
        }

        if (typeof navigator !== 'undefined' && !navigator.onLine) {
            setError(
                'Tidak ada koneksi internet. Periksa jaringanmu lalu coba lagi.',
            );
            return;
        }

        setProcessing(true);
        setError(null);

        try {
            const quoteResponse = await fetchWithTimeout(
                '/api/public/carts/validate',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        qr_token,
                        items: cartPayload(cart),
                    }),
                },
            );
            const quoteBody: {
                quote?: ServerQuote;
                message?: string;
                errors?: Record<string, string[]>;
            } = await quoteResponse.json();

            if (!quoteResponse.ok || !quoteBody.quote) {
                const validationMessage = quoteBody.errors
                    ? Object.values(quoteBody.errors)[0]?.[0]
                    : null;
                throw new Error(
                    validationMessage ??
                        quoteBody.message ??
                        'Pesanan belum dapat divalidasi.',
                );
            }

            const quote = quoteBody.quote;
            const changed = !quoteMatchesCart(
                quote,
                cart,
                subtotal,
                taxAmount,
                total,
            );
            const confirmingExistingQuote =
                priceConfirmationRequired &&
                serverQuote?.fingerprint === quote.fingerprint;

            setServerQuote(quote);

            if (changed && !confirmingExistingQuote) {
                setPriceConfirmationRequired(true);
                setProcessing(false);
                return;
            }

            setPriceConfirmationRequired(false);

            const response = await fetchWithTimeout('/api/public/orders', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'Idempotency-Key': idempotencyKey,
                },
                body: JSON.stringify({
                    qr_token,
                    customer_name: customerName.trim() || null,
                    payment_method: payment,
                    quote_fingerprint: quote.fingerprint,
                    items: cartPayload(cart),
                }),
            });
            const body: {
                access_token?: string;
                tracking_url?: string;
                message?: string;
                errors?: Record<string, string[]>;
            } = await response.json();

            if (!response.ok || !body.access_token || !body.tracking_url) {
                const validationMessage = body.errors
                    ? Object.values(body.errors)[0]?.[0]
                    : null;
                throw new Error(
                    validationMessage ??
                        body.message ??
                        'Checkout belum dapat diproses.',
                );
            }

            const paymentResponse = await fetchWithTimeout(
                `/api/public/orders/${body.access_token}/payment`,
                {
                    method: 'POST',
                    headers: { Accept: 'application/json' },
                },
            );
            const paymentBody: { redirect_url?: string; message?: string } =
                await paymentResponse.json();

            if (!paymentResponse.ok || !paymentBody.redirect_url) {
                throw new Error(
                    paymentBody.message ??
                        'Sesi pembayaran belum dapat dibuat.',
                );
            }

            clearCustomerCart(qr_token);
            window.location.assign(paymentBody.redirect_url);
        } catch (exception) {
            const isTimeout =
                exception instanceof Error && exception.name === 'AbortError';
            const isOffline =
                typeof navigator !== 'undefined' && !navigator.onLine;

            setError(
                isTimeout
                    ? 'Koneksi terlalu lama. Periksa jaringanmu lalu tekan coba lagi.'
                    : isOffline
                      ? 'Koneksi internet terputus. Periksa jaringanmu lalu coba lagi.'
                      : exception instanceof Error
                        ? exception.message
                        : 'Checkout belum dapat diproses.',
            );
            setProcessing(false);
        }
    };

    return (
        <>
            <Head title="Konfirmasi pesanan" />
            <div className="bg-background min-h-screen pb-28">
                <CustomerHeader
                    minimal
                    outletName={outlet?.name}
                    tableName={table?.name}
                />
                <main className="mx-auto max-w-5xl px-4 py-7 sm:px-6 sm:py-12">
                    <Link
                        href={
                            isPublicCheckout && qr_token
                                ? `/q/${qr_token}`
                                : '/demo/menu'
                        }
                        className="text-muted-foreground hover:text-foreground inline-flex min-h-11 items-center gap-2 text-sm font-bold"
                    >
                        <ArrowLeft className="size-4" aria-hidden="true" />{' '}
                        Kembali ke menu
                    </Link>
                    <div className="mt-5 grid gap-8 lg:grid-cols-[1fr_0.72fr] lg:items-start">
                        <form
                            id="checkout-form"
                            onSubmit={submitOrder}
                            aria-busy={processing}
                        >
                            <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                Langkah terakhir
                            </p>
                            <h1 className="font-display mt-2 text-4xl font-bold tracking-tight sm:text-5xl">
                                Periksa pesananmu.
                            </h1>
                            <p className="text-muted-foreground mt-3">
                                {outlet?.name ?? 'Kedai Sore'} ·{' '}
                                {table?.name ?? 'Meja 08'}
                            </p>

                            <section className="bg-card mt-8 overflow-hidden rounded-[1.5rem] border">
                                <div className="flex items-center justify-between border-b px-5 py-4">
                                    <h2 className="font-bold">Pesanan kamu</h2>
                                    <span className="text-muted-foreground text-xs">
                                        {itemCount} item
                                    </span>
                                </div>
                                {activeCart.map((item, index) => (
                                    <div
                                        key={item.key}
                                        className="flex gap-4 border-b p-5 last:border-b-0"
                                    >
                                        <div className="bg-muted size-20 shrink-0 overflow-hidden rounded-2xl sm:size-24">
                                            {item.product.image ? (
                                                <img
                                                    src={item.product.image}
                                                    srcSet={
                                                        item.product
                                                            .image_srcset ??
                                                        undefined
                                                    }
                                                    sizes="96px"
                                                    alt=""
                                                    className="size-full object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="text-primary flex size-full items-center justify-center">
                                                    <QrCode
                                                        className="size-7"
                                                        aria-hidden="true"
                                                    />
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
                                                        (variant) =>
                                                            variant.id ===
                                                            item.variant_id,
                                                    )?.name ||
                                                        item.note) && (
                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                            {
                                                                item.product.variants?.find(
                                                                    (variant) =>
                                                                        variant.id ===
                                                                        item.variant_id,
                                                                )?.name
                                                            }
                                                            {item.note &&
                                                                ` · ${item.note}`}
                                                        </p>
                                                    )}
                                                </div>
                                                <p className="shrink-0 text-sm font-bold">
                                                    {formatCurrency(
                                                        serverQuote?.items[
                                                            index
                                                        ]?.line_total ??
                                                            itemUnitPrice(
                                                                item,
                                                            ) * item.quantity,
                                                    )}
                                                </p>
                                            </div>
                                            {isPublicCheckout && (
                                                <div className="mt-4 flex items-center justify-between gap-3">
                                                    <div className="flex w-fit items-center rounded-full border p-0.5">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                updateQuantity(
                                                                    item.key,
                                                                    -1,
                                                                )
                                                            }
                                                            className="hover:bg-secondary flex size-9 items-center justify-center rounded-full"
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
                                                                updateQuantity(
                                                                    item.key,
                                                                    1,
                                                                )
                                                            }
                                                            className="hover:bg-secondary flex size-9 items-center justify-center rounded-full"
                                                            aria-label={`Tambah ${item.product.name}`}
                                                        >
                                                            <Plus className="size-3.5" />
                                                        </button>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeItem(item.key)
                                                        }
                                                        className="text-muted-foreground hover:bg-destructive/10 hover:text-destructive inline-flex min-h-9 items-center gap-1.5 rounded-full px-3 text-xs font-bold"
                                                    >
                                                        <Trash2
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />{' '}
                                                        Hapus
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {isPublicCheckout &&
                                    activeCart.length === 0 && (
                                        <div className="text-muted-foreground p-8 text-center text-sm">
                                            Keranjang masih kosong. Kembali ke
                                            menu untuk memilih hidangan.
                                        </div>
                                    )}
                            </section>

                            <section className="bg-card mt-6 rounded-[1.5rem] border p-5 sm:p-6">
                                <label
                                    className="font-bold"
                                    htmlFor="customer-name"
                                >
                                    Nama pemesan{' '}
                                    <span className="text-muted-foreground font-normal">
                                        (opsional)
                                    </span>
                                </label>
                                <input
                                    id="customer-name"
                                    value={customerName}
                                    onChange={(event) =>
                                        setCustomerName(event.target.value)
                                    }
                                    className="bg-background focus:ring-ring mt-4 min-h-12 w-full rounded-xl border px-4 text-sm outline-none focus:ring-2"
                                    placeholder="Agar staf mudah memanggilmu"
                                    autoComplete="name"
                                    maxLength={120}
                                />
                            </section>

                            <section className="bg-card mt-6 rounded-[1.5rem] border p-5 sm:p-6">
                                <h2 className="font-bold">Pilih pembayaran</h2>
                                <div className="mt-4 grid gap-3">
                                    {paymentMethods.map((method) => (
                                        <button
                                            key={method.id}
                                            type="button"
                                            onClick={() =>
                                                setPayment(method.id)
                                            }
                                            aria-pressed={payment === method.id}
                                            className={`flex min-h-16 items-center gap-3 rounded-2xl border p-3 text-left transition-colors ${payment === method.id ? 'border-primary bg-primary/6' : 'hover:bg-secondary/60'}`}
                                        >
                                            <span
                                                className={`flex size-11 items-center justify-center rounded-xl ${payment === method.id ? 'bg-primary text-primary-foreground' : 'bg-secondary'}`}
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
                                                <span className="text-muted-foreground mt-1 block text-xs">
                                                    {method.detail}
                                                </span>
                                            </span>
                                            <span
                                                className={`flex size-5 items-center justify-center rounded-full border ${payment === method.id ? 'border-primary bg-primary text-white' : ''}`}
                                            >
                                                {payment === method.id && (
                                                    <Check
                                                        className="size-3"
                                                        aria-hidden="true"
                                                    />
                                                )}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {isPublicCheckout && (
                                    <p className="bg-secondary/60 text-muted-foreground mt-4 rounded-xl p-3 text-xs leading-5">
                                        Pembayaran akan dibuat sebagai transaksi
                                        tertunda. Order baru masuk ke antrean
                                        setelah pembayaran diverifikasi server.
                                    </p>
                                )}
                            </section>
                        </form>

                        <aside className="rounded-[1.75rem] bg-[#283025] p-6 text-[#fffaf0] shadow-[0_30px_80px_-50px_rgba(40,48,37,0.8)] sm:p-7 lg:sticky lg:top-24">
                            <p className="text-xs font-bold tracking-[0.16em] text-[#dfa281] uppercase">
                                Ringkasan pembayaran
                            </p>
                            <dl className="mt-6 space-y-4 text-sm">
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>Subtotal</dt>
                                    <dd>{formatCurrency(displaySubtotal)}</dd>
                                </div>
                                <div className="flex justify-between text-[#cbd1c3]">
                                    <dt>
                                        {displayTaxEnabled
                                            ? (serverQuote?.tax_name ??
                                              tax?.name ??
                                              'Pajak restoran')
                                            : 'Pajak'}
                                        {displayTaxEnabled &&
                                            ` (${displayTaxRate / 100}%)`}
                                    </dt>
                                    <dd>{formatCurrency(displayTaxAmount)}</dd>
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
                                    {formatCurrency(displayTotal)}
                                </span>
                            </div>
                            {priceConfirmationRequired && serverQuote && (
                                <p
                                    className="mt-5 rounded-xl bg-amber-400/20 p-3 text-sm font-semibold text-amber-50"
                                    role="alert"
                                >
                                    Harga atau ketersediaan menu berubah.
                                    Periksa ringkasan terbaru, lalu tekan tombol
                                    di bawah untuk mengonfirmasi.
                                </p>
                            )}
                            {error && (
                                <p
                                    id="checkout-error"
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
                                    disabled={
                                        processing || activeCart.length === 0
                                    }
                                    aria-busy={processing}
                                    aria-describedby={
                                        error ? 'checkout-error' : undefined
                                    }
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 mt-7 flex min-h-13 w-full items-center justify-between rounded-full px-5 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {processing
                                        ? 'Memproses...'
                                        : priceConfirmationRequired
                                          ? 'Konfirmasi & lanjutkan'
                                          : error
                                            ? 'Coba lagi'
                                            : 'Lanjutkan pembayaran'}{' '}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </button>
                            ) : (
                                <Link
                                    href="/demo/tracking"
                                    className="bg-primary text-primary-foreground hover:bg-primary/90 mt-7 flex min-h-13 items-center justify-between rounded-full px-5 text-sm font-bold transition-colors"
                                >
                                    Bayar sekarang{' '}
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </Link>
                            )}
                            <p className="mt-5 flex items-center justify-center gap-2 text-center text-xs text-[#bfc5b7]">
                                <LockKeyhole
                                    className="size-3.5"
                                    aria-hidden="true"
                                />{' '}
                                Pembayaran aman dan terenkripsi
                            </p>
                        </aside>
                    </div>
                </main>
            </div>
        </>
    );
}
