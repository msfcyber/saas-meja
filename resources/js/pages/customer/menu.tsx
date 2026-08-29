import { Head, Link } from "@inertiajs/react";
import {
    ArrowRight,
    Clock3,
    Flame,
    Minus,
    Plus,
    Search,
    ShoppingBag,
    Sparkles,
} from "lucide-react";
import { useDeferredValue, useState } from "react";
import { CustomerHeader } from "@/components/customer-header";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { formatCurrency, menuItems, type MenuItem } from "@/data/demo";

const categories = ["Semua", "Makanan utama", "Camilan", "Minuman", "Pencuci mulut"];

export default function Menu() {
    const [category, setCategory] = useState("Semua");
    const [query, setQuery] = useState("");
    const [selected, setSelected] = useState<MenuItem | null>(null);
    const [cart, setCart] = useState<Record<number, number>>({ 1: 1 });
    const deferredQuery = useDeferredValue(query);

    const filteredItems = menuItems.filter((item) => {
        const matchesCategory = category === "Semua" || item.category === category;
        const matchesSearch = item.name.toLowerCase().includes(deferredQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });
    const itemCount = Object.values(cart).reduce((sum, quantity) => sum + quantity, 0);
    const cartTotal = menuItems.reduce((sum, item) => sum + item.price * (cart[item.id] ?? 0), 0);

    const addItem = (item: MenuItem) => {
        setCart((current) => ({ ...current, [item.id]: (current[item.id] ?? 0) + 1 }));
        setSelected(null);
    };

    return (
        <>
            <Head title="Menu Kedai Sore" />
            <div className="min-h-screen bg-background pb-32">
                <CustomerHeader />

                <main className="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
                    <section className="relative overflow-hidden rounded-[1.75rem] bg-[#283025] px-6 py-8 text-[#fffaf0] sm:px-10 sm:py-12">
                        <div
                            className="absolute -right-14 -bottom-28 size-72 rounded-full border-[44px] border-[#d89a77]/20"
                            aria-hidden="true"
                        />
                        <div className="relative max-w-2xl">
                            <div className="flex items-center gap-2 text-xs font-bold tracking-[0.16em] text-[#e0a483] uppercase">
                                <Sparkles className="size-3.5" aria-hidden="true" />
                                Rekomendasi dapur
                            </div>
                            <h1 className="font-display mt-3 text-4xl leading-tight font-bold tracking-tight sm:text-5xl">
                                Makan enak, tak perlu menunggu lama.
                            </h1>
                            <div className="mt-5 flex flex-wrap gap-4 text-sm text-[#ccd2c4]">
                                <span className="flex items-center gap-2">
                                    <Clock3 className="size-4" aria-hidden="true" /> Disiapkan 10-15
                                    menit
                                </span>
                                <span className="flex items-center gap-2">
                                    <Flame className="size-4" aria-hidden="true" /> Dimasak setelah
                                    dipesan
                                </span>
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
                                        onClick={() => setSelected(item)}
                                        className="block w-full text-left"
                                    >
                                        <div className="relative aspect-[4/3] overflow-hidden bg-muted">
                                            <img
                                                src={item.image}
                                                alt=""
                                                className="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                loading="lazy"
                                            />
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
                                                <span className="flex size-11 items-center justify-center rounded-full bg-foreground text-background transition-colors group-hover:bg-primary">
                                                    <Plus className="size-4" aria-hidden="true" />
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

                {itemCount > 0 && (
                    <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-background/95 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] backdrop-blur-xl">
                        <Link
                            href="/demo/checkout"
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
                                <img
                                    src={selected.image}
                                    alt=""
                                    className="size-full object-cover"
                                />
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
                                <div className="mt-6 rounded-2xl border p-4">
                                    <p className="text-sm font-bold">Tingkat kepedasan</p>
                                    <div className="mt-3 grid grid-cols-3 gap-2">
                                        {["Tidak pedas", "Sedang", "Pedas"].map((level, index) => (
                                            <button
                                                key={level}
                                                type="button"
                                                className={`min-h-11 rounded-xl border px-2 text-xs font-bold ${index === 1 ? "border-primary bg-primary/8 text-primary" : "hover:bg-secondary"}`}
                                            >
                                                {level}
                                            </button>
                                        ))}
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
                                        <span className="w-8 text-center text-sm font-bold">1</span>
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
                                            <ShoppingBag className="size-4" aria-hidden="true" />{" "}
                                            Tambahkan
                                        </span>
                                        {formatCurrency(selected.price)}
                                    </button>
                                </div>
                            </div>
                        </DialogContent>
                    )}
                </Dialog>
            </div>
        </>
    );
}
