import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    Clock3,
    Flame,
    MapPin,
    Minus,
    Plus,
    QrCode,
    Search,
    ShoppingBag,
    Sparkles,
} from "lucide-react";
import { useDeferredValue, useEffect, useState } from "react";
import { CustomerHeader } from "@/components/customer-header";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { formatCurrency, menuItems } from "@/data/demo";
import {
    cartItemKey,
    itemUnitPrice,
    loadCustomerCart,
    saveCustomerCart,
} from "@/lib/customer-cart";
import type { CustomerCartItem, CustomerMenuProduct } from "@/types/customer";

type CustomerMenuItem = CustomerMenuProduct;
type Props = {
    access?: { valid: boolean; message: string | null; qr_token?: string | null };
    outlet?: { name: string; address: string | null; currency: string } | null;
    table?: { name: string; code: string } | null;
    categories?: string[];
    products?: CustomerMenuItem[];
};

const demoCategories = ["Semua", "Makanan utama", "Camilan", "Minuman", "Pencuci mulut"];

const defaultVariantId = (item: CustomerMenuItem): number | null =>
    item.variants?.find((variant) => variant.is_default)?.id ?? item.variants?.[0]?.id ?? null;

const configuredPrice = (item: CustomerMenuItem, variantId: number | null, optionIds: number[]) => {
    const variant = item.variants?.find((entry) => entry.id === variantId);
    const options = item.modifiers?.flatMap((modifier) => modifier.options) ?? [];

    return (
        item.price +
        (variant?.price_delta ?? 0) +
        optionIds.reduce(
            (total, optionId) =>
                total + (options.find((option) => option.id === optionId)?.price_delta ?? 0),
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
    const categories = providedCategories ? ["Semua", ...providedCategories] : demoCategories;
    const [category, setCategory] = useState("Semua");
    const [query, setQuery] = useState("");
    const [selected, setSelected] = useState<CustomerMenuItem | null>(null);
    const [demoCart, setDemoCart] = useState<Record<number, number>>((): Record<number, number> =>
        isPublicMenu ? {} : { 1: 1 },
    );
    const [publicCart, setPublicCart] = useState<CustomerCartItem[]>(() =>
        qrToken ? loadCustomerCart(qrToken) : [],
    );
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(null);
    const [selectedOptionIds, setSelectedOptionIds] = useState<number[]>([]);
    const [selectedQuantity, setSelectedQuantity] = useState(1);
    const [selectedNote, setSelectedNote] = useState("");
    const [dialogError, setDialogError] = useState<string | null>(null);
    const deferredQuery = useDeferredValue(query);

    useEffect(() => {
        if (qrToken) {
            saveCustomerCart(qrToken, publicCart);
        }
    }, [publicCart, qrToken]);

    const filteredItems = items.filter((item) => {
        const matchesCategory = category === "Semua" || item.category === category;
        const matchesSearch = item.name.toLowerCase().includes(deferredQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });
    const itemCount = isPublicMenu
        ? publicCart.reduce((sum, item) => sum + item.quantity, 0)
        : Object.values(demoCart).reduce((sum, quantity) => sum + quantity, 0);
    const cartTotal = isPublicMenu
        ? publicCart.reduce((sum, item) => sum + itemUnitPrice(item) * item.quantity, 0)
        : items.reduce((sum, item) => sum + item.price * (demoCart[item.id] ?? 0), 0);

    const openProduct = (item: CustomerMenuItem) => {
        setSelected(item);
        setSelectedVariantId(defaultVariantId(item));
        setSelectedOptionIds([]);
        setSelectedQuantity(1);
        setSelectedNote("");
        setDialogError(null);
    };

    const addItem = (item: CustomerMenuItem) => {
        setDemoCart((current) => ({ ...current, [item.id]: (current[item.id] ?? 0) + 1 }));
        setSelected(null);
    };

    const toggleOption = (
        modifierId: number,
        optionId: number,
        selectionType: "single" | "multiple",
        maximum: number,
    ) => {
        const modifier = selected?.modifiers?.find((item) => item.id === modifierId);
        const modifierOptionIds = modifier?.options.map((option) => option.id) ?? [];

        setSelectedOptionIds((current) => {
            const withoutModifier = current.filter((id) => !modifierOptionIds.includes(id));

            if (selectionType === "single") {
                return current.includes(optionId)
                    ? withoutModifier
                    : [...withoutModifier, optionId];
            }

            if (current.includes(optionId)) {
                return current.filter((id) => id !== optionId);
            }

            const selectedCount = current.filter((id) => modifierOptionIds.includes(id)).length;

            if (selectedCount >= maximum) {
                setDialogError(`Maksimal ${maximum} pilihan untuk modifier ini.`);
                return current;
            }

            setDialogError(null);
            return [...current, optionId];
        });
    };

    const addPublicItem = () => {
        if (!selected || !qrToken) {
            return;
        }

        const missingModifier = selected.modifiers?.find((modifier) => {
            const count = selectedOptionIds.filter((id) =>
                modifier.options.some((option) => option.id === id),
            ).length;
            return modifier.is_required && count < Math.max(modifier.minimum_selections, 1);
        });

        if (missingModifier) {
            setDialogError(`Pilih ${missingModifier.name} terlebih dahulu.`);
            return;
        }

        const note = selectedNote.trim() || null;
        const key = cartItemKey(selected.id, selectedVariantId, selectedOptionIds, note);
        setPublicCart((current) => {
            const existing = current.find((item) => item.key === key);

            if (existing) {
                return current.map((item) =>
                    item.key === key
                        ? { ...item, quantity: Math.min(item.quantity + selectedQuantity, 50) }
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
        setSelected(null);
    };

    if (access && !access.valid) {
        return (
            <>
                <Head title="QR tidak tersedia" />
                <div className="min-h-screen bg-background">
                    <CustomerHeader minimal />
                    <main className="mx-auto flex min-h-[calc(100vh-4rem)] max-w-xl items-center px-4 py-10 sm:px-6">
                        <section className="w-full rounded-[1.75rem] border bg-card p-7 text-center shadow-sm sm:p-10">
                            <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-destructive/10 text-destructive">
                                <QrCode className="size-8" aria-hidden="true" />
                            </div>
                            <p className="mt-6 text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                Akses menu
                            </p>
                            <h1 className="font-display mt-2 text-3xl font-bold tracking-tight">
                                QR tidak tersedia
                            </h1>
                            <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                {access.message ?? "QR meja ini tidak dapat digunakan saat ini."}
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

    const pageTitle = isPublicMenu
        ? `${outlet?.name ?? "Menu outlet"} · ${table?.name ?? "Meja"}`
        : "Menu Kedai Sore";

    return (
        <>
            <Head title={pageTitle} />
            <div className="min-h-screen bg-background pb-32">
                <CustomerHeader
                    outletName={outlet?.name}
                    tableName={table?.name}
                    homeHref={isPublicMenu ? "/" : "/demo/menu"}
                />

                <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
                    <section className="relative overflow-hidden rounded-[1.75rem] bg-[#283025] px-6 py-8 text-[#fffaf0] sm:px-10 sm:py-12">
                        <div
                            className="absolute -right-14 -bottom-28 size-72 rounded-full border-[44px] border-[#d89a77]/20"
                            aria-hidden="true"
                        />
                        <div className="relative max-w-2xl">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-[#e0a483] uppercase">
                                <Sparkles className="size-3.5" aria-hidden="true" />
                                {isPublicMenu ? "Menu digital outlet" : "Rekomendasi dapur"}
                            </div>
                            <h1 className="font-display mt-3 text-4xl leading-tight font-bold tracking-tight sm:text-5xl">
                                {isPublicMenu
                                    ? `Selamat datang di ${outlet?.name ?? "outlet kami"}.`
                                    : "Makan enak, tak perlu menunggu lama."}
                            </h1>
                            <div className="mt-5 flex flex-wrap gap-4 text-sm text-[#ccd2c4]">
                                {isPublicMenu ? (
                                    <>
                                        <span className="flex items-center gap-2">
                                            <MapPin className="size-4" aria-hidden="true" />
                                            {table?.name ?? "Meja"} · {table?.code ?? ""}
                                        </span>
                                        {outlet?.address && <span>{outlet.address}</span>}
                                    </>
                                ) : (
                                    <>
                                        <span className="flex items-center gap-2">
                                            <Clock3 className="size-4" aria-hidden="true" />{" "}
                                            Disiapkan 10-15 menit
                                        </span>
                                        <span className="flex items-center gap-2">
                                            <Flame className="size-4" aria-hidden="true" /> Dimasak
                                            setelah dipesan
                                        </span>
                                    </>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="sticky top-16 z-30 -mx-4 mt-7 border-y border-border/60 bg-background/94 px-4 py-4 backdrop-blur-xl sm:mx-0 sm:rounded-2xl sm:border sm:px-5">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <label className="relative block sm:w-72">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Cari menu</span>
                                <input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder="Cari yang kamu suka..."
                                    className="min-h-12 w-full rounded-full border bg-card pr-4 pl-11 text-sm outline-none transition-shadow focus:ring-2 focus:ring-ring"
                                />
                            </label>
                            <div className="flex gap-2 overflow-x-auto pb-1 sm:pb-0">
                                {categories.map((item) => (
                                    <button
                                        key={item}
                                        type="button"
                                        onClick={() => setCategory(item)}
                                        aria-pressed={category === item}
                                        className={`min-h-11 shrink-0 rounded-full px-4 text-sm font-bold transition-colors ${category === item ? "bg-foreground text-background" : "border bg-card hover:bg-secondary"}`}
                                    >
                                        {item}
                                    </button>
                                ))}
                            </div>
                        </div>
                    </section>

                    <section className="mt-9">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                                    Menu hari ini
                                </p>
                                <h2 className="font-display mt-2 text-3xl font-bold tracking-tight">
                                    Dipilih untukmu
                                </h2>
                            </div>
                            <span className="text-sm text-muted-foreground">
                                {filteredItems.length} menu
                            </span>
                        </div>

                        <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                            {filteredItems.map((item) => (
                                <article
                                    key={item.id}
                                    className="group overflow-hidden rounded-[1.5rem] border bg-card shadow-[0_16px_50px_-38px_rgba(54,45,31,0.55)] transition-all hover:-translate-y-1 hover:shadow-[0_24px_60px_-38px_rgba(54,45,31,0.75)]"
                                >
                                    <button
                                        type="button"
                                        onClick={() => openProduct(item)}
                                        className="block w-full text-left"
                                    >
                                        <div className="relative aspect-[4/3] overflow-hidden bg-muted">
                                            {item.image ? (
                                                <img
                                                    src={item.image}
                                                    alt=""
                                                    className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <div className="flex size-full items-center justify-center bg-secondary text-primary">
                                                    <ShoppingBag
                                                        className="size-10"
                                                        aria-hidden="true"
                                                    />
                                                </div>
                                            )}
                                            <div className="absolute top-3 left-3 flex gap-2">
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
                                                        <span className="sr-only">Pedas</span>
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <div className="p-5">
                                            <p className="text-[11px] font-bold tracking-[0.12em] text-primary uppercase">
                                                {item.category}
                                            </p>
                                            <h3 className="mt-2 text-lg font-bold tracking-tight">
                                                {item.name}
                                            </h3>
                                            <p className="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-muted-foreground">
                                                {item.description}
                                            </p>
                                            <div className="mt-5 flex items-center justify-between gap-4">
                                                <span className="font-bold">
                                                    {formatCurrency(item.price)}
                                                </span>
                                                <span className="flex min-h-11 items-center justify-center rounded-full bg-foreground px-4 text-xs font-bold text-background transition-colors group-hover:bg-primary">
                                                    {isPublicMenu ? (
                                                        "Detail"
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
                            ))}
                        </div>
                        {filteredItems.length === 0 && (
                            <div className="mt-6 rounded-3xl border border-dashed p-12 text-center">
                                <p className="font-bold">Menu tidak ditemukan</p>
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Coba kata kunci atau kategori lain.
                                </p>
                            </div>
                        )}
                    </section>
                </main>

                {itemCount > 0 && (!isPublicMenu || qrToken) && (
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl">
                        <Link
                            href={isPublicMenu ? `/q/${qrToken}/checkout` : "/demo/checkout"}
                            className="mx-auto flex min-h-14 max-w-xl items-center justify-between rounded-full bg-primary px-5 text-primary-foreground shadow-[0_18px_35px_-16px_var(--primary)] transition-transform hover:-translate-y-0.5"
                        >
                            <span className="flex items-center gap-3">
                                <span className="flex size-8 items-center justify-center rounded-full bg-white/15 text-sm font-bold">
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
                            <div className="aspect-[16/10] overflow-hidden rounded-t-lg bg-muted">
                                {selected.image ? (
                                    <img
                                        src={selected.image}
                                        alt=""
                                        className="size-full object-cover"
                                    />
                                ) : (
                                    <div className="flex size-full items-center justify-center bg-secondary text-primary">
                                        <ShoppingBag className="size-12" aria-hidden="true" />
                                    </div>
                                )}
                            </div>
                            <div className="p-6 pt-2">
                                <DialogHeader>
                                    <p className="text-xs font-bold tracking-wider text-primary uppercase">
                                        {selected.category}
                                    </p>
                                    <DialogTitle className="font-display text-3xl leading-tight">
                                        {selected.name}
                                    </DialogTitle>
                                    <DialogDescription className="text-sm leading-6">
                                        {selected.description}
                                    </DialogDescription>
                                </DialogHeader>
                                {isPublicMenu ? (
                                    <>
                                        {(selected.variants?.length ?? 0) > 0 && (
                                            <fieldset className="mt-6 rounded-2xl border p-4">
                                                <legend className="px-1 text-sm font-bold">
                                                    Ukuran
                                                </legend>
                                                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                                    {selected.variants?.map((variant) => (
                                                        <button
                                                            key={variant.id}
                                                            type="button"
                                                            onClick={() =>
                                                                setSelectedVariantId(variant.id)
                                                            }
                                                            aria-pressed={
                                                                selectedVariantId === variant.id
                                                            }
                                                            className={`flex min-h-11 items-center justify-between rounded-xl border px-3 text-left text-sm ${selectedVariantId === variant.id ? "border-primary bg-primary/8 text-primary" : "hover:bg-secondary"}`}
                                                        >
                                                            <span className="font-bold">
                                                                {variant.name}
                                                            </span>
                                                            <span className="text-xs font-semibold">
                                                                {variant.price_delta > 0
                                                                    ? `+${formatCurrency(variant.price_delta)}`
                                                                    : "Harga dasar"}
                                                            </span>
                                                        </button>
                                                    ))}
                                                </div>
                                            </fieldset>
                                        )}
                                        {selected.modifiers?.map((modifier) => {
                                            const selectedForModifier = selectedOptionIds.filter(
                                                (id) =>
                                                    modifier.options.some(
                                                        (option) => option.id === id,
                                                    ),
                                            );

                                            return (
                                                <fieldset
                                                    key={modifier.id}
                                                    className="mt-4 rounded-2xl border p-4"
                                                >
                                                    <legend className="px-1 text-sm font-bold">
                                                        {modifier.name}{" "}
                                                        {modifier.is_required && (
                                                            <span className="font-normal text-muted-foreground">
                                                                (wajib)
                                                            </span>
                                                        )}
                                                    </legend>
                                                    <div className="mt-2 grid gap-2 sm:grid-cols-2">
                                                        {modifier.options.map((option) => {
                                                            const isSelected =
                                                                selectedOptionIds.includes(
                                                                    option.id,
                                                                );

                                                            return (
                                                                <button
                                                                    key={option.id}
                                                                    type="button"
                                                                    onClick={() =>
                                                                        toggleOption(
                                                                            modifier.id,
                                                                            option.id,
                                                                            modifier.selection_type,
                                                                            modifier.maximum_selections,
                                                                        )
                                                                    }
                                                                    aria-pressed={isSelected}
                                                                    className={`flex min-h-11 items-center justify-between rounded-xl border px-3 text-left text-sm ${isSelected ? "border-primary bg-primary/8 text-primary" : "hover:bg-secondary"}`}
                                                                >
                                                                    <span className="font-semibold">
                                                                        {option.name}
                                                                    </span>
                                                                    {option.price_delta !== 0 && (
                                                                        <span className="text-xs font-semibold">
                                                                            {option.price_delta > 0
                                                                                ? "+"
                                                                                : ""}
                                                                            {formatCurrency(
                                                                                option.price_delta,
                                                                            )}
                                                                        </span>
                                                                    )}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                    <p className="mt-2 text-xs text-muted-foreground">
                                                        Pilih{" "}
                                                        {modifier.minimum_selections > 0
                                                            ? `minimal ${modifier.minimum_selections}`
                                                            : "hingga"}{" "}
                                                        {modifier.maximum_selections}.
                                                        {selectedForModifier.length > 0 &&
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
                                                    setSelectedNote(event.target.value)
                                                }
                                                className="mt-2 min-h-20 w-full resize-none rounded-2xl border bg-background p-3 text-sm font-normal outline-none focus:ring-2 focus:ring-ring"
                                                placeholder="Contoh: tanpa bawang..."
                                                maxLength={500}
                                            />
                                        </label>
                                        {dialogError && (
                                            <p
                                                className="mt-3 text-sm font-semibold text-destructive"
                                                role="alert"
                                            >
                                                {dialogError}
                                            </p>
                                        )}
                                        <div className="mt-6 flex gap-3">
                                            <div className="flex min-h-12 items-center rounded-full border bg-card p-1">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setSelectedQuantity((quantity) =>
                                                            Math.max(1, quantity - 1),
                                                        )
                                                    }
                                                    className="flex size-10 items-center justify-center rounded-full hover:bg-secondary"
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
                                                        setSelectedQuantity((quantity) =>
                                                            Math.min(50, quantity + 1),
                                                        )
                                                    }
                                                    className="flex size-10 items-center justify-center rounded-full hover:bg-secondary"
                                                    aria-label="Tambah jumlah"
                                                >
                                                    <Plus className="size-4" />
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={addPublicItem}
                                                className="flex min-h-12 flex-1 items-center justify-between rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <ShoppingBag
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />{" "}
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
                                            <p className="text-sm font-bold">Tingkat kepedasan</p>
                                            <div className="mt-3 grid grid-cols-3 gap-2">
                                                {["Tidak pedas", "Sedang", "Pedas"].map(
                                                    (level, index) => (
                                                        <button
                                                            key={level}
                                                            type="button"
                                                            className={`min-h-11 rounded-xl border px-2 text-xs font-bold ${index === 1 ? "border-primary bg-primary/8 text-primary" : "hover:bg-secondary"}`}
                                                        >
                                                            {level}
                                                        </button>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <label className="mt-4 block text-sm font-bold">
                                            Catatan untuk dapur
                                            <textarea
                                                className="mt-2 min-h-20 w-full resize-none rounded-2xl border bg-background p-3 text-sm font-normal outline-none focus:ring-2 focus:ring-ring"
                                                placeholder="Contoh: tanpa bawang..."
                                            />
                                        </label>
                                        <div className="mt-6 flex gap-3">
                                            <div className="flex min-h-12 items-center rounded-full border bg-card p-1">
                                                <button
                                                    type="button"
                                                    className="flex size-10 items-center justify-center rounded-full hover:bg-secondary"
                                                    aria-label="Kurangi jumlah"
                                                >
                                                    <Minus className="size-4" />
                                                </button>
                                                <span className="w-8 text-center text-sm font-bold">
                                                    1
                                                </span>
                                                <button
                                                    type="button"
                                                    className="flex size-10 items-center justify-center rounded-full hover:bg-secondary"
                                                    aria-label="Tambah jumlah"
                                                >
                                                    <Plus className="size-4" />
                                                </button>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => addItem(selected)}
                                                className="flex min-h-12 flex-1 items-center justify-between rounded-full bg-primary px-5 text-sm font-bold text-primary-foreground"
                                            >
                                                <span className="flex items-center gap-2">
                                                    <ShoppingBag
                                                        className="size-4"
                                                        aria-hidden="true"
                                                    />{" "}
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
