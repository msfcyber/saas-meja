import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ChevronRight,
    Clock3,
    Flame,
    MapPin,
    Minus,
    Plus,
    QrCode,
    Search,
    ShoppingBag,
    Sparkles,
    X,
} from 'lucide-react';
import { useDeferredValue, useEffect, useState } from 'react';
import { CustomerHeader } from '@/components/customer-header';
import { trackAnalytics } from '@/hooks/use-analytics';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatCurrency, menuItems } from '@/data/demo';
import {
    cartItemKey,
    itemUnitPrice,
    loadCustomerCart,
    saveCustomerCart,
} from '@/lib/customer-cart';
import type { CustomerCartItem, CustomerMenuProduct } from '@/types/customer';

type CustomerMenuItem = CustomerMenuProduct;
type Props = {
    access?: {
        valid: boolean;
        message: string | null;
        qr_token?: string | null;
    };
    outlet?: { name: string; address: string | null; currency: string } | null;
    table?: { name: string; code: string } | null;
    categories?: string[];
    products?: CustomerMenuItem[];
};

const demoCategories = [
    'Semua',
    'Makanan utama',
    'Camilan',
    'Minuman',
    'Pencuci mulut',
];

const defaultVariantId = (item: CustomerMenuItem): number | null =>
    item.variants?.find((variant) => variant.is_default)?.id ??
    item.variants?.[0]?.id ??
    null;

const isAvailable = (item: CustomerMenuItem): boolean =>
    item.is_available !== false;

const configuredPrice = (
    item: CustomerMenuItem,
    variantId: number | null,
    optionIds: number[],
) => {
    const variant = item.variants?.find((entry) => entry.id === variantId);
    const options =
        item.modifiers?.flatMap((modifier) => modifier.options) ?? [];

    return (
        item.price +
        (variant?.price_delta ?? 0) +
        optionIds.reduce(
            (total, optionId) =>
                total +
                (options.find((option) => option.id === optionId)
                    ?.price_delta ?? 0),
            0,
        )
    );
};

export default function Menu({
    access,
    outlet,
    table,
    categories: providedCategories,
    products,
}: Props) {
    const isPublicMenu = access !== undefined;
    const qrToken = access?.qr_token ?? null;
    const items: CustomerMenuItem[] = products ?? menuItems;
    const categories = providedCategories
        ? ['Semua', ...providedCategories]
        : demoCategories;
    const [category, setCategory] = useState('Semua');
    const [query, setQuery] = useState('');
    const [selected, setSelected] = useState<CustomerMenuItem | null>(null);
    const [demoCart, setDemoCart] = useState<Record<number, number>>(
        (): Record<number, number> => (isPublicMenu ? {} : { 1: 1 }),
    );
    const [publicCart, setPublicCart] = useState<CustomerCartItem[]>(() =>
        qrToken ? loadCustomerCart(qrToken) : [],
    );
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
        null,
    );
    const [selectedOptionIds, setSelectedOptionIds] = useState<number[]>([]);
    const [selectedQuantity, setSelectedQuantity] = useState(1);
    const [selectedNote, setSelectedNote] = useState('');
    const [dialogError, setDialogError] = useState<string | null>(null);
    const deferredQuery = useDeferredValue(query);

    useEffect(() => {
        if (qrToken) {
            saveCustomerCart(qrToken, publicCart);
        }
    }, [publicCart, qrToken]);

    const filteredItems = items.filter((item) => {
        const matchesCategory =
            category === 'Semua' || item.category === category;
        const matchesSearch = item.name
            .toLowerCase()
            .includes(deferredQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });
    const itemCount = isPublicMenu
        ? publicCart.reduce((sum, item) => sum + item.quantity, 0)
        : Object.values(demoCart).reduce((sum, quantity) => sum + quantity, 0);
    const cartTotal = isPublicMenu
        ? publicCart.reduce(
              (sum, item) => sum + itemUnitPrice(item) * item.quantity,
              0,
          )
        : items.reduce(
              (sum, item) => sum + item.price * (demoCart[item.id] ?? 0),
              0,
          );
    const availableItemCount = items.filter(isAvailable).length;
    const featuredItem =
        items.find((item) => item.popular && isAvailable(item)) ??
        items.find(isAvailable) ??
        null;

    const openProduct = (item: CustomerMenuItem) => {
        if (isPublicMenu && !isAvailable(item)) {
            return;
        }

        setSelected(item);
        setSelectedVariantId(defaultVariantId(item));
        setSelectedOptionIds([]);
        setSelectedQuantity(1);
        setSelectedNote('');
        setDialogError(null);

        if (isPublicMenu && qrToken) {
            trackAnalytics('product_viewed', {
                qrToken,
                productId: item.id,
            });
        }
    };

    const addItem = (item: CustomerMenuItem) => {
        setDemoCart((current) => ({
            ...current,
            [item.id]: (current[item.id] ?? 0) + 1,
        }));
        setSelected(null);
    };

    const toggleOption = (
        modifierId: number,
        optionId: number,
        selectionType: 'single' | 'multiple',
        maximum: number,
    ) => {
        const modifier = selected?.modifiers?.find(
            (item) => item.id === modifierId,
        );
        const modifierOptionIds =
            modifier?.options.map((option) => option.id) ?? [];

        setSelectedOptionIds((current) => {
            const withoutModifier = current.filter(
                (id) => !modifierOptionIds.includes(id),
            );

            if (selectionType === 'single') {
                return current.includes(optionId)
                    ? withoutModifier
                    : [...withoutModifier, optionId];
            }

            if (current.includes(optionId)) {
                return current.filter((id) => id !== optionId);
            }

            const selectedCount = current.filter((id) =>
                modifierOptionIds.includes(id),
            ).length;

            if (selectedCount >= maximum) {
                setDialogError(
                    `Maksimal ${maximum} pilihan untuk modifier ini.`,
                );
                return current;
            }

            setDialogError(null);
            return [...current, optionId];
        });
    };

    const addPublicItem = () => {
        if (!selected || !qrToken || !isAvailable(selected)) {
            return;
        }

        const missingModifier = selected.modifiers?.find((modifier) => {
            const count = selectedOptionIds.filter((id) =>
                modifier.options.some((option) => option.id === id),
            ).length;
            return (
                modifier.is_required &&
                count < Math.max(modifier.minimum_selections, 1)
            );
        });

        if (missingModifier) {
            setDialogError(`Pilih ${missingModifier.name} terlebih dahulu.`);
            return;
        }

        const note = selectedNote.trim() || null;
        const key = cartItemKey(
            selected.id,
            selectedVariantId,
            selectedOptionIds,
            note,
        );
        setPublicCart((current) => {
            const existing = current.find((item) => item.key === key);

            if (existing) {
                return current.map((item) =>
                    item.key === key
                        ? {
                              ...item,
                              quantity: Math.min(
                                  item.quantity + selectedQuantity,
                                  50,
                              ),
                          }
                        : item,
                );
            }

            return [
                ...current,
                {
                    key,
                    product_id: selected.id,
                    variant_id: selectedVariantId,
                    modifier_option_ids: selectedOptionIds,
                    quantity: selectedQuantity,
                    note,
                    product: selected,
                },
            ];
        });
        trackAnalytics('add_to_cart', {
            qrToken,
            productId: selected.id,
        });
        setSelected(null);
    };

    if (access && !access.valid) {
        return (
            <>
                <Head title="QR tidak tersedia" />
                <div className="bg-background min-h-screen">
                    <CustomerHeader minimal />
                    <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-xl items-center px-4 py-10 sm:px-6">
                        <section className="bg-card w-full rounded-[1.75rem] border p-7 text-center shadow-sm sm:p-10">
                            <div className="bg-destructive/10 text-destructive mx-auto flex size-16 items-center justify-center rounded-2xl">
                                <QrCode className="size-8" aria-hidden="true" />
                            </div>
                            <p className="text-primary mt-6 text-xs font-bold tracking-[0.16em] uppercase">
                                Akses menu
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight">
                                QR tidak tersedia
                            </h1>
                            <p className="text-muted-foreground mt-3 text-sm leading-6">
                                {access.message ??
                                    'QR meja ini tidak dapat digunakan saat ini.'}
                            </p>
                            <Link
                                href="/"
                                className="bg-primary text-primary-foreground mt-7 inline-flex min-h-11 cursor-pointer items-center justify-center rounded-full px-5 text-sm font-bold"
                            >
                                Kembali ke beranda
                            </Link>
                        </section>
                    </main>
                </div>
            </>
        );
    }

    const pageTitle = isPublicMenu
        ? `${outlet?.name ?? 'Menu outlet'} · ${table?.name ?? 'Meja'}`
        : 'Menu Kedai Sore';

    return (
        <>
            <Head title={pageTitle} />
            <div className="bg-background min-h-screen pb-32">
                <CustomerHeader
                    outletName={outlet?.name}
                    tableName={table?.name}
                    homeHref={isPublicMenu ? '/' : '/demo/menu'}
                />

                <main className="mx-auto max-w-6xl scroll-pb-36 px-4 py-6 sm:px-6 sm:py-10">
                    <section className="relative overflow-hidden rounded-[2rem] bg-[#283025] px-6 py-8 text-[#fffaf0] sm:px-10 sm:py-10">
                        <div
                            className="absolute -right-14 -bottom-28 size-72 rounded-full border-[44px] border-[#d89a77]/20"
                            aria-hidden="true"
                        />
                        <div className="relative grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-center lg:gap-10">
                            <div className="max-w-2xl">
                                <div className="flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-[#e0a483] uppercase">
                                    <Sparkles
                                        className="size-3.5"
                                        aria-hidden="true"
                                    />
                                    {isPublicMenu
                                        ? 'Pesan langsung dari meja'
                                        : 'Koleksi menu demo'}
                                </div>
                                <h1 className="font-display mt-3 max-w-xl text-4xl leading-[1.04] font-bold tracking-tight sm:text-5xl">
                                    {isPublicMenu
                                        ? `Selamat datang di ${outlet?.name ?? 'outlet kami'}.`
                                        : 'Makan enak, tak perlu menunggu lama.'}
                                </h1>
                                <p className="mt-5 max-w-xl text-sm leading-6 text-[#ccd2c4] sm:text-base">
                                    {isPublicMenu
                                        ? 'Pilih hidangan favoritmu, sesuaikan pesanan, lalu kirim langsung ke dapur.'
                                        : 'Jelajahi menu Kedai Sore dan rasakan alur pesan yang sederhana dari meja ke dapur.'}
                                </p>
                                <div className="mt-6 flex flex-wrap gap-x-5 gap-y-3 text-xs font-semibold text-[#ccd2c4] sm:text-sm">
                                    <span className="flex items-center gap-2">
                                        <span
                                            className="size-2 rounded-full bg-[#a9b888]"
                                            aria-hidden="true"
                                        />
                                        {isPublicMenu
                                            ? `${availableItemCount} hidangan siap dipesan`
                                            : `${items.length} hidangan tersedia`}
                                    </span>
                                    {isPublicMenu ? (
                                        <span className="flex items-center gap-2">
                                            <MapPin
                                                className="size-4 text-[#e0a483]"
                                                aria-hidden="true"
                                            />
                                            {table?.name ?? 'Meja'} ·{' '}
                                            {table?.code ?? ''}
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2">
                                            <Clock3
                                                className="size-4 text-[#e0a483]"
                                                aria-hidden="true"
                                            />
                                            Disiapkan 10-15 menit
                                        </span>
                                    )}
                                </div>
                                <a
                                    href="#menu-list"
                                    className="mt-7 inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full bg-[#fffaf0] px-5 text-sm font-bold text-[#283025] transition-transform duration-200 hover:-translate-y-0.5"
                                >
                                    Jelajahi menu
                                    <ArrowRight
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                </a>
                            </div>

                            {featuredItem && (
                                <button
                                    type="button"
                                    onClick={() => openProduct(featuredItem)}
                                    aria-label={`Lihat detail ${featuredItem.name}`}
                                    className="group/featured cursor-pointer overflow-hidden rounded-[1.5rem] border border-white/10 bg-white/8 text-left shadow-[0_24px_60px_-32px_rgba(0,0,0,0.7)] transition-transform duration-200 hover:-translate-y-1"
                                >
                                    <div className="relative aspect-[4/3] overflow-hidden bg-[#414b3d]">
                                        {featuredItem.image ? (
                                            <img
                                                src={featuredItem.image}
                                                alt=""
                                                className="size-full object-cover transition-transform duration-500 group-hover/featured:scale-105"
                                            />
                                        ) : (
                                            <div className="flex size-full items-center justify-center text-[#e0a483]">
                                                <ShoppingBag
                                                    className="size-10"
                                                    aria-hidden="true"
                                                />
                                            </div>
                                        )}
                                        <span className="absolute top-3 left-3 rounded-full bg-[#fff8e8] px-3 py-1.5 text-[10px] font-bold tracking-[0.12em] text-[#7d5c18] uppercase">
                                            Pilihan favorit
                                        </span>
                                    </div>
                                    <div className="p-4 sm:p-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <p className="text-[10px] font-bold tracking-[0.14em] text-[#e0a483] uppercase">
                                                    {featuredItem.category}
                                                </p>
                                                <h2 className="mt-1.5 text-lg font-bold tracking-tight text-[#fffaf0]">
                                                    {featuredItem.name}
                                                </h2>
                                            </div>
                                            <ChevronRight
                                                className="mt-1 size-4 shrink-0 text-[#e0a483] transition-transform group-hover/featured:translate-x-1"
                                                aria-hidden="true"
                                            />
                                        </div>
                                        <p className="mt-2 line-clamp-2 text-xs leading-5 text-[#cbd1c3]">
                                            {featuredItem.description}
                                        </p>
                                        <p className="mt-4 text-sm font-bold text-[#fffaf0]">
                                            {formatCurrency(featuredItem.price)}
                                        </p>
                                    </div>
                                </button>
                            )}
                        </div>
                    </section>

                    <section
                        aria-label="Filter menu"
                        className="border-border/60 bg-background/94 sticky top-16 z-30 -mx-4 mt-7 border-y px-4 py-4 backdrop-blur-xl sm:mx-0 sm:rounded-2xl sm:border sm:px-5"
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <label className="relative block sm:w-72">
                                <Search
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Cari menu</span>
                                <input
                                    value={query}
                                    onChange={(event) =>
                                        setQuery(event.target.value)
                                    }
                                    placeholder="Cari yang kamu suka..."
                                    className="bg-card focus:ring-ring min-h-12 w-full rounded-full border pr-11 pl-11 text-sm transition-shadow outline-none focus:ring-2"
                                />
                                {query && (
                                    <button
                                        type="button"
                                        onClick={() => setQuery('')}
                                        aria-label="Hapus pencarian"
                                        className="text-muted-foreground hover:bg-secondary hover:text-foreground absolute top-1/2 right-1.5 flex size-9 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full transition-colors"
                                    >
                                        <X
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </button>
                                )}
                            </label>
                            <fieldset className="flex gap-2 overflow-x-auto pb-1 sm:pb-0">
                                <legend className="sr-only">
                                    Kategori menu
                                </legend>
                                {categories.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        onClick={() => setCategory(item)}
                                        aria-pressed={category === item}
                                        className={`min-h-11 shrink-0 cursor-pointer rounded-full px-4 text-sm font-bold transition-colors ${category === item ? 'bg-foreground text-background' : 'bg-card hover:bg-secondary border'}`}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </fieldset>
                        </div>
                    </section>

                    <section
                        id="menu-list"
                        aria-labelledby="menu-list-title"
                        className="mt-9 scroll-mt-36"
                    >
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                                    Menu untukmu
                                </p>
                                <h2
                                    id="menu-list-title"
                                    className="font-display mt-2 text-3xl font-bold tracking-tight"
                                >
                                    Pilih hidangan favorit
                                </h2>
                            </div>
                            <span
                                className="text-muted-foreground text-right text-sm"
                                aria-live="polite"
                            >
                                {filteredItems.length} dari {items.length} menu
                            </span>
                        </div>

                        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredItems.map((item) => {
                                const unavailable =
                                    isPublicMenu && !isAvailable(item);

                                return (
                                    <article
                                        key={item.id}
                                        className={`group bg-card overflow-hidden rounded-[1.5rem] border shadow-[0_16px_50px_-38px_rgba(54,45,31,0.55)] transition-all ${unavailable ? 'opacity-75' : 'hover:-translate-y-1 hover:shadow-[0_24px_60px_-38px_rgba(54,45,31,0.75)]'}`}
                                    >
                                        <button
                                            type="button"
                                            onClick={() => openProduct(item)}
                                            disabled={unavailable}
                                            aria-label={
                                                unavailable
                                                    ? `${item.name} sedang tidak tersedia`
                                                    : `Lihat detail ${item.name}`
                                            }
                                            className={`block w-full text-left disabled:cursor-not-allowed ${unavailable ? '' : 'cursor-pointer'}`}
                                        >
                                            <div className="bg-muted relative aspect-[4/3] overflow-hidden">
                                                {item.image ? (
                                                    <img
                                                        src={item.image}
                                                        alt=""
                                                        className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <div className="bg-secondary text-primary flex size-full items-center justify-center">
                                                        <ShoppingBag
                                                            className="size-10"
                                                            aria-hidden="true"
                                                        />
                                                    </div>
                                                )}
                                                <div className="absolute top-3 left-3 flex flex-wrap gap-2">
                                                    {item.popular && (
                                                        <span className="rounded-full bg-[#fff8e8] px-3 py-1.5 text-[10px] font-bold tracking-wider text-[#7d5c18] uppercase shadow-sm">
                                                            Favorit
                                                        </span>
                                                    )}
                                                    {item.spicy && (
                                                        <span className="flex size-7 items-center justify-center rounded-full bg-[#b64a2e] text-white shadow-sm">
                                                            <Flame
                                                                className="size-3.5"
                                                                aria-hidden="true"
                                                            />
                                                            <span className="sr-only">
                                                                Pedas
                                                            </span>
                                                        </span>
                                                    )}
                                                    {unavailable && (
                                                        <span className="bg-foreground text-background rounded-full px-3 py-1.5 text-[10px] font-bold tracking-wider uppercase shadow-sm">
                                                            Habis
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="p-5">
                                                <p className="text-primary text-[11px] font-bold tracking-[0.12em] uppercase">
                                                    {item.category}
                                                </p>
                                                <h3 className="mt-2 text-lg font-bold tracking-tight">
                                                    {item.name}
                                                </h3>
                                                <p className="text-muted-foreground mt-2 line-clamp-2 min-h-10 text-sm leading-5">
                                                    {item.description}
                                                </p>
                                                <div className="mt-5 flex items-center justify-between gap-4">
                                                    <span className="font-bold">
                                                        {formatCurrency(
                                                            item.price,
                                                        )}
                                                    </span>
                                                    <span
                                                        className={`flex min-h-11 items-center justify-center rounded-full px-4 text-xs font-bold transition-colors ${unavailable ? 'bg-muted text-muted-foreground' : 'bg-foreground text-background group-hover:bg-primary'}`}
                                                    >
                                                        {unavailable ? (
                                                            'Habis'
                                                        ) : isPublicMenu ? (
                                                            'Detail'
                                                        ) : (
                                                            <Plus
                                                                className="size-4"
                                                                aria-hidden="true"
                                                            />
                                                        )}
                                                    </span>
                                                </div>
                                            </div>
                                        </button>
                                    </article>
                                );
                            })}
                        </div>
                        {filteredItems.length === 0 && (
                            <div className="mt-6 rounded-3xl border border-dashed p-8 text-center sm:p-12">
                                <span className="bg-secondary text-primary mx-auto flex size-12 items-center justify-center rounded-2xl">
                                    <Search
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <p className="mt-4 font-bold">
                                    Menu tidak ditemukan
                                </p>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    Coba kata kunci atau kategori lain.
                                </p>
                                {(query || category !== 'Semua') && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setQuery('');
                                            setCategory('Semua');
                                        }}
                                        className="bg-card hover:bg-secondary mt-5 min-h-11 cursor-pointer rounded-full border px-4 text-sm font-bold transition-colors"
                                    >
                                        Tampilkan semua menu
                                    </button>
                                )}
                            </div>
                        )}
                    </section>
                </main>

                {itemCount > 0 && (!isPublicMenu || qrToken) && (
                    <div className="bg-background/95 fixed inset-x-0 bottom-0 z-40 border-t p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl">
                        <Link
                            href={
                                isPublicMenu
                                    ? `/q/${qrToken}/checkout`
                                    : '/demo/checkout'
                            }
                            aria-label={`Lihat pesanan, ${itemCount} item, total ${formatCurrency(cartTotal)}`}
                            className="bg-primary text-primary-foreground mx-auto flex min-h-14 max-w-xl cursor-pointer items-center justify-between rounded-full px-5 shadow-[0_18px_35px_-16px_var(--primary)] transition-transform hover:-translate-y-0.5"
                        >
                            <span className="flex items-center gap-3">
                                <span
                                    className="flex size-8 items-center justify-center rounded-full bg-white/15 text-sm font-bold"
                                    aria-live="polite"
                                >
                                    {itemCount}
                                </span>
                                <span className="text-left">
                                    <span className="block text-[10px] leading-none font-bold tracking-wider text-white/70 uppercase">
                                        Lihat pesanan
                                    </span>
                                    <span className="mt-1 block text-sm leading-none font-bold">
                                        {formatCurrency(cartTotal)}
                                    </span>
                                </span>
                            </span>
                            <ArrowRight className="size-5" aria-hidden="true" />
                        </Link>
                    </div>
                )}

                <Dialog
                    open={selected !== null}
                    onOpenChange={(open) => !open && setSelected(null)}
                >
                    {selected && (
                        <DialogContent className="max-h-[90vh] overflow-y-auto p-0 sm:max-w-xl">
                            <div className="bg-muted aspect-[16/10] overflow-hidden rounded-t-lg">
                                {selected.image ? (
                                    <img
                                        src={selected.image}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <div className="bg-secondary text-primary flex size-full items-center justify-center">
                                        <ShoppingBag
                                            className="size-12"
                                            aria-hidden="true"
                                        />
                                    </div>
                                )}
                            </div>
                            <div className="p-6 pt-2">
                                <DialogHeader>
                                    <p className="text-primary text-xs font-bold tracking-wider uppercase">
                                        {selected.category}
                                    </p>
                                    <DialogTitle className="font-display text-3xl leading-tight">
                                        {selected.name}
                                    </DialogTitle>
                                    <DialogDescription className="text-sm leading-6">
                                        {selected.description}
                                    </DialogDescription>
                                </DialogHeader>
                                {isPublicMenu && !isAvailable(selected) ? (
                                    <div
                                        className="bg-muted text-muted-foreground mt-6 rounded-2xl px-4 py-3 text-sm font-semibold"
                                        role="status"
                                    >
                                        Menu ini sedang habis dan belum dapat
                                        ditambahkan ke pesanan.
                                    </div>
                                ) : isPublicMenu ? (
                                    <>
                                        {(selected.variants?.length ?? 0) >
                                            0 && (
                                            <fieldset className="mt-6 rounded-2xl border p-4">
                                                <legend className="px-1 text-sm font-bold">
                                                    Ukuran
                                                </legend>
                                                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                                    {selected.variants?.map(
                                                        (variant) => (
                                                            <button
                                                                key={variant.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    setSelectedVariantId(
                                                                        variant.id,
                                                                    )
                                                                }
                                                                aria-pressed={
                                                                    selectedVariantId ===
                                                                    variant.id
                                                                }
                                                                className={`flex min-h-11 cursor-pointer items-center justify-between rounded-xl border px-3 text-left text-sm transition-colors ${selectedVariantId === variant.id ? 'border-primary bg-primary/8 text-primary' : 'hover:bg-secondary'}`}
                                                            >
                                                                <span className="font-bold">
                                                                    {
                                                                        variant.name
                                                                    }
                                                                </span>
                                                                <span className="text-xs font-semibold">
                                                                    {variant.price_delta >
                                                                    0
                                                                        ? `+${formatCurrency(variant.price_delta)}`
                                                                        : 'Harga dasar'}
                                                                </span>
                                                            </button>
                                                        ),
                                                    )}
                                                </div>
                                            </fieldset>
                                        )}
                                        {selected.modifiers?.map((modifier) => {
                                            const selectedForModifier =
                                                selectedOptionIds.filter((id) =>
                                                    modifier.options.some(
                                                        (option) =>
                                                            option.id === id,
                                                    ),
                                                );

                                            return (
                                                <fieldset
                                                    key={modifier.id}
                                                    className="mt-4 rounded-2xl border p-4"
                                                >
                                                    <legend className="px-1 text-sm font-bold">
                                                        {modifier.name}{' '}
                                                        {modifier.is_required && (
                                                            <span className="text-muted-foreground font-normal">
                                                                (wajib)
                                                            </span>
                                                        )}
                                                    </legend>
                                                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                                        {modifier.options.map(
                                                            (option) => {
                                                                const isSelected =
                                                                    selectedOptionIds.includes(
                                                                        option.id,
                                                                    );

                                                                return (
                                                                    <button
                                                                        key={
                                                                            option.id
                                                                        }
                                                                        type="button"
                                                                        onClick={() =>
                                                                            toggleOption(
                                                                                modifier.id,
                                                                                option.id,
                                                                                modifier.selection_type,
                                                                                modifier.maximum_selections,
                                                                            )
                                                                        }
                                                                        aria-pressed={
                                                                            isSelected
                                                                        }
                                                                        className={`flex min-h-11 cursor-pointer items-center justify-between rounded-xl border px-3 text-left text-sm transition-colors ${isSelected ? 'border-primary bg-primary/8 text-primary' : 'hover:bg-secondary'}`}
                                                                    >
                                                                        <span className="font-semibold">
                                                                            {
                                                                                option.name
                                                                            }
                                                                        </span>
                                                                        {option.price_delta !==
                                                                            0 && (
                                                                            <span className="text-xs font-semibold">
                                                                                {option.price_delta >
                                                                                0
                                                                                    ? '+'
                                                                                    : ''}
                                                                                {formatCurrency(
                                                                                    option.price_delta,
                                                                                )}
                                                                            </span>
                                                                        )}
                                                                    </button>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                    <p className="text-muted-foreground mt-2 text-xs">
                                                        Pilih{' '}
                                                        {modifier.minimum_selections >
                                                        0
                                                            ? `minimal ${modifier.minimum_selections}`
                                                            : 'hingga'}{' '}
                                                        {
                                                            modifier.maximum_selections
                                                        }
                                                        .
                                                        {selectedForModifier.length >
                                                            0 &&
                                                            ` Terpilih ${selectedForModifier.length}.`}
                                                    </p>
                                                </fieldset>
                                            );
                                        })}
                                        <label className="mt-4 block text-sm font-bold">
                                            Catatan untuk dapur
                                            <textarea
                                                value={selectedNote}
                                                onChange={(event) =>
                                                    setSelectedNote(
                                                        event.target.value,
                                                    )
                                                }
                                                className="bg-background focus:ring-ring mt-2 min-h-20 w-full resize-none rounded-2xl border p-3 text-sm font-normal outline-none focus:ring-2"
                                                placeholder="Contoh: tanpa bawang..."
                                                maxLength={500}
                                            />
                                        </label>
                                        {dialogError && (
                                            <p
                                                className="text-destructive mt-3 text-sm font-semibold"
                                                role="alert"
                                            >
                                                {dialogError}
                                            </p>
                                        )}
                                        <div className="mt-6 flex gap-3">
                                            <div className="bg-card flex min-h-12 items-center rounded-full border p-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedQuantity(
                                                            (quantity) =>
                                                                Math.max(
                                                                    1,
                                                                    quantity -
                                                                        1,
                                                                ),
                                                        )
                                                    }
                                                    className="hover:bg-secondary flex size-10 cursor-pointer items-center justify-center rounded-full"
                                                    aria-label="Kurangi jumlah"
                                                >
                                                    <Minus className="size-4" />
                                                </button>
                                                <span className="w-8 text-center text-sm font-bold">
                                                    {selectedQuantity}
                                                </span>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedQuantity(
                                                            (quantity) =>
                                                                Math.min(
                                                                    50,
                                                                    quantity +
                                                                        1,
                                                                ),
                                                        )
                                                    }
                                                    className="hover:bg-secondary flex size-10 cursor-pointer items-center justify-center rounded-full"
                                                    aria-label="Tambah jumlah"
                                                >
                                                    <Plus className="size-4" />
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={addPublicItem}
                                                className="bg-primary text-primary-foreground hover:bg-primary/90 flex min-h-12 flex-1 cursor-pointer items-center justify-between rounded-full px-5 text-sm font-bold transition-colors"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <ShoppingBag
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />{' '}
                                                    Tambahkan
                                                </span>
                                                {formatCurrency(
                                                    configuredPrice(
                                                        selected,
                                                        selectedVariantId,
                                                        selectedOptionIds,
                                                    ) * selectedQuantity,
                                                )}
                                            </button>
                                        </div>
                                    </>
                                ) : (
                                    <>
                                        <div className="mt-6 rounded-2xl border p-4">
                                            <p className="text-sm font-bold">
                                                Tingkat kepedasan
                                            </p>
                                            <p className="bg-primary/8 text-primary mt-3 rounded-xl px-3 py-2 text-sm font-semibold">
                                                Sedang
                                            </p>
                                        </div>
                                        <div className="mt-6 flex gap-3">
                                            <div className="bg-card flex min-h-12 items-center rounded-full border px-4 text-sm font-bold">
                                                <span className="sr-only">
                                                    Jumlah produk demo:{' '}
                                                </span>
                                                1
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    addItem(selected)
                                                }
                                                className="bg-primary text-primary-foreground hover:bg-primary/90 flex min-h-12 flex-1 cursor-pointer items-center justify-between rounded-full px-5 text-sm font-bold transition-colors"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <ShoppingBag
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />{' '}
                                                    Tambahkan
                                                </span>
                                                {formatCurrency(selected.price)}
                                            </button>
                                        </div>
                                    </>
                                )}
                            </div>
                        </DialogContent>
                    )}
                </Dialog>
            </div>
        </>
    );
}
