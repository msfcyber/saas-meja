import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ImageIcon,
    Plus,
    Search,
    SlidersHorizontal,
    Soup,
    Star,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatCurrency } from '@/data/demo';

type Category = { id: number; name: string; is_active: boolean };
type Product = {
    id: number;
    name: string;
    category: Pick<Category, 'id' | 'name'> | null;
    description: string | null;
    image_url: string | null;
    base_price: number;
    is_active: boolean;
    is_available: boolean;
    is_featured: boolean;
};

type Props = {
    categories: Category[];
    filters: { search: string; category: number | null };
    products: Product[];
    summary: {
        products: number;
        available_products: number;
        categories: number;
        featured_products: number;
    };
};

export default function Products({
    categories,
    filters,
    products,
    summary,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [processingProductId, setProcessingProductId] = useState<
        number | null
    >(null);
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        name: '',
        category_id: '',
        description: '',
        image: null as File | null,
        base_price: '',
        is_active: true,
        is_available: true,
        is_featured: false,
    });

    function applyFilters(category: number | null) {
        router.get(
            '/products',
            { search, category: category ?? undefined },
            { preserveState: true, replace: true },
        );
    }

    function createProduct(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/products', {
            onSuccess: () => {
                form.reset();
                setIsCreateOpen(false);
            },
        });
    }

    function toggleAvailability(product: Product) {
        setProcessingProductId(product.id);
        router.patch(
            `/products/${product.id}/availability`,
            { is_available: !product.is_available },
            {
                preserveScroll: true,
                onFinish: () => setProcessingProductId(null),
                onError: (errors) => {
                    const message = Object.values(errors)[0];

                    toast.error('Ketersediaan produk gagal diperbarui', {
                        description:
                            message ?? 'Coba lagi dalam beberapa saat.',
                    });
                },
            },
        );
    }

    function clearFilters() {
        setSearch('');
        router.get('/products', {}, { preserveState: true, replace: true });
    }

    const catalogReadiness =
        summary.products > 0
            ? Math.min(
                  100,
                  Math.round(
                      (summary.available_products / summary.products) * 100,
                  ),
              )
            : 0;
    const hasActiveFilters = filters.search !== '' || filters.category !== null;

    return (
        <>
            <Head title="Produk & menu" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <header className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-primary text-xs font-bold tracking-[0.16em] uppercase">
                            Katalog outlet
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Produk & menu
                        </h1>
                        <p className="text-muted-foreground mt-2 text-sm">
                            Atur harga dan ketersediaan menu pada outlet aktif.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-xl shadow-[0_14px_30px_-18px_var(--primary)]"
                        onClick={() => setIsCreateOpen(true)}
                    >
                        <Plus aria-hidden="true" /> Tambah produk
                    </Button>
                </header>

                <section className="mt-8 grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)]">
                    <article className="bg-foreground text-background relative isolate overflow-hidden rounded-[1.75rem] p-6 shadow-[0_28px_70px_-44px_rgba(53,44,31,0.8)] sm:p-8">
                        <div
                            className="border-primary-foreground/10 absolute -right-20 -bottom-32 size-80 rounded-full border-[46px]"
                            aria-hidden="true"
                        />
                        <div
                            className="border-primary-foreground/10 absolute -top-24 -left-24 size-56 rounded-full border-[30px]"
                            aria-hidden="true"
                        />
                        <div className="relative">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-primary-foreground/65 text-xs font-bold tracking-[0.16em] uppercase">
                                        Kesiapan menu
                                    </p>
                                    <h2 className="font-display mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                                        Katalog siap melayani.
                                    </h2>
                                </div>
                                <span className="bg-background/10 text-background inline-flex min-h-9 items-center rounded-full px-3 text-xs font-bold">
                                    {catalogReadiness}% siap
                                </span>
                            </div>
                            <p className="text-background/65 mt-3 max-w-md text-sm">
                                Pastikan produk yang tampil di menu QR selalu
                                siap dipesan oleh tamu.
                            </p>
                            <div className="mt-8">
                                <div className="flex items-end justify-between gap-4">
                                    <p className="text-background/65 text-xs font-bold tracking-[0.12em] uppercase">
                                        Produk tersedia
                                    </p>
                                    <p className="font-display text-2xl font-bold tabular-nums">
                                        {summary.available_products}
                                        <span className="text-background/55 ml-1 text-sm font-normal">
                                            / {summary.products}
                                        </span>
                                    </p>
                                </div>
                                <div
                                    className="bg-background/15 mt-3 h-2 overflow-hidden rounded-full"
                                    role="progressbar"
                                    aria-label="Kesiapan katalog"
                                    aria-valuemin={0}
                                    aria-valuemax={100}
                                    aria-valuenow={catalogReadiness}
                                >
                                    <div
                                        className="bg-primary h-full rounded-full transition-[width] duration-500"
                                        style={{
                                            width: `${catalogReadiness}%`,
                                        }}
                                    />
                                </div>
                            </div>
                        </div>
                    </article>

                    <div className="grid gap-4 sm:grid-cols-3">
                        {[
                            {
                                label: 'Total produk',
                                value: summary.products,
                                detail: `${summary.available_products} tersedia`,
                                icon: Soup,
                                tone: 'bg-primary/10 text-primary',
                            },
                            {
                                label: 'Kategori',
                                value: summary.categories,
                                detail: 'Di outlet aktif',
                                icon: SlidersHorizontal,
                                tone: 'bg-accent text-accent-foreground',
                            },
                            {
                                label: 'Produk favorit',
                                value: summary.featured_products,
                                detail: 'Ditandai populer',
                                icon: Star,
                                tone: 'bg-amber-500/10 text-amber-700 dark:text-amber-300',
                            },
                        ].map((metric) => (
                            <article
                                key={metric.label}
                                className="border-border/70 bg-card flex min-h-[10rem] flex-col justify-between rounded-[1.3rem] border p-5 shadow-[0_18px_50px_-42px_rgba(53,44,31,0.8)]"
                            >
                                <span
                                    className={`inline-flex size-11 items-center justify-center rounded-xl ${metric.tone}`}
                                >
                                    <metric.icon
                                        className="size-5"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <p className="text-muted-foreground text-xs">
                                        {metric.label}
                                    </p>
                                    <p className="font-display mt-1 text-2xl font-bold tabular-nums">
                                        {metric.value}
                                    </p>
                                    <p className="text-muted-foreground mt-1 text-xs">
                                        {metric.detail}
                                    </p>
                                </div>
                            </article>
                        ))}
                    </div>
                </section>

                <section className="border-border/70 bg-card mt-5 overflow-hidden rounded-[1.5rem] border shadow-[0_18px_60px_-48px_rgba(53,44,31,0.8)]">
                    <div className="border-border/70 border-b p-5 sm:p-6">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                                    Daftar katalog
                                </p>
                                <div className="mt-1 flex flex-wrap items-center gap-3">
                                    <h2 className="font-display text-2xl font-bold tracking-tight">
                                        Menu outlet
                                    </h2>
                                    <span className="bg-secondary text-secondary-foreground inline-flex min-h-7 items-center rounded-full px-2.5 text-[11px] font-bold">
                                        {products.length} dari{' '}
                                        {summary.products} produk
                                    </span>
                                </div>
                            </div>
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    applyFilters(filters.category);
                                }}
                                className="relative w-full lg:max-w-sm"
                            >
                                <Search
                                    className="text-muted-foreground absolute top-1/2 left-4 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                                <label
                                    className="sr-only"
                                    htmlFor="product-search"
                                >
                                    Cari produk
                                </label>
                                <Input
                                    id="product-search"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="border-border/80 bg-background min-h-11 rounded-xl pr-4 pl-10"
                                    placeholder="Cari nama produk..."
                                />
                            </form>
                        </div>

                        <fieldset className="mt-5 flex flex-wrap gap-2">
                            <legend className="sr-only">
                                Filter kategori produk
                            </legend>
                            <button
                                type="button"
                                onClick={() => applyFilters(null)}
                                aria-pressed={filters.category === null}
                                className={`inline-flex min-h-10 shrink-0 items-center rounded-xl px-4 text-xs font-bold transition-colors ${filters.category === null ? 'bg-foreground text-background shadow-sm' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                            >
                                Semua
                            </button>
                            {categories.map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() => applyFilters(category.id)}
                                    aria-pressed={
                                        filters.category === category.id
                                    }
                                    className={`inline-flex min-h-10 shrink-0 items-center rounded-xl px-4 text-xs font-bold transition-colors ${filters.category === category.id ? 'bg-foreground text-background shadow-sm' : 'bg-muted text-muted-foreground hover:bg-secondary hover:text-foreground'}`}
                                >
                                    {category.name}
                                </button>
                            ))}
                        </fieldset>
                    </div>
                    <div className="grid gap-4 p-4 sm:grid-cols-2 sm:p-5 xl:grid-cols-3">
                        {products.length === 0 ? (
                            <div
                                className="border-border/80 bg-background/40 col-span-full rounded-[1.5rem] border border-dashed p-10 text-center sm:p-14"
                                role="status"
                            >
                                <span className="bg-primary/10 text-primary inline-flex size-12 items-center justify-center rounded-2xl">
                                    <ImageIcon
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                                <h3 className="font-display mt-4 text-xl font-bold">
                                    {hasActiveFilters
                                        ? 'Tidak ada produk yang cocok'
                                        : 'Katalog masih kosong'}
                                </h3>
                                <p className="text-muted-foreground mx-auto mt-2 max-w-md text-sm">
                                    {hasActiveFilters
                                        ? 'Coba bersihkan filter atau gunakan kata pencarian yang berbeda.'
                                        : 'Tambahkan produk pertama agar menu QR outlet siap digunakan.'}
                                </p>
                                <div className="mt-5 flex flex-wrap justify-center gap-2">
                                    {hasActiveFilters && (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11 rounded-xl"
                                            onClick={clearFilters}
                                        >
                                            Bersihkan filter
                                        </Button>
                                    )}
                                    <Button
                                        type="button"
                                        className="min-h-11 rounded-xl"
                                        onClick={() => setIsCreateOpen(true)}
                                    >
                                        <Plus aria-hidden="true" /> Tambah
                                        produk
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            products.map((product) => (
                                <article
                                    key={product.id}
                                    className="border-border/70 bg-background/35 group flex min-w-0 flex-col overflow-hidden rounded-[1.35rem] border shadow-sm transition-shadow duration-200 hover:shadow-md"
                                >
                                    <div className="bg-secondary text-primary relative aspect-[16/9] overflow-hidden">
                                        {product.image_url ? (
                                            <img
                                                src={product.image_url}
                                                alt=""
                                                className="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <div className="paper-grid flex size-full flex-col items-center justify-center gap-2">
                                                <Soup
                                                    className="size-8"
                                                    aria-hidden="true"
                                                />
                                                <span className="text-xs font-bold">
                                                    Belum ada foto
                                                </span>
                                            </div>
                                        )}
                                        <div className="absolute inset-x-0 top-0 flex items-start justify-between gap-2 p-3">
                                            {product.is_featured ? (
                                                <span className="inline-flex items-center gap-1 rounded-full bg-amber-400 px-2.5 py-1 text-[10px] font-bold text-amber-950 shadow-sm">
                                                    <Star
                                                        className="size-3 fill-current"
                                                        aria-hidden="true"
                                                    />{' '}
                                                    Favorit
                                                </span>
                                            ) : (
                                                <span />
                                            )}
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-[10px] font-bold shadow-sm ${product.is_active ? 'bg-emerald-500/90 text-white' : 'bg-background/85 text-foreground'}`}
                                            >
                                                {product.is_active
                                                    ? 'Aktif'
                                                    : 'Nonaktif'}
                                            </span>
                                        </div>
                                        <div className="absolute inset-x-0 bottom-0 flex items-end justify-between gap-2 bg-gradient-to-t from-black/70 to-transparent p-4 pt-10">
                                            <span className="text-background max-w-[65%] truncate text-xs font-semibold">
                                                {product.category?.name ??
                                                    'Tanpa kategori'}
                                            </span>
                                            <span
                                                className={`rounded-full px-2.5 py-1 text-[10px] font-bold ${product.is_available ? 'bg-background text-emerald-700' : 'bg-background/80 text-muted-foreground'}`}
                                            >
                                                {product.is_available
                                                    ? 'Tersedia'
                                                    : 'Habis'}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex flex-1 flex-col p-5">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <h3 className="font-display line-clamp-2 text-xl font-bold tracking-tight">
                                                    {product.name}
                                                </h3>
                                                {product.description && (
                                                    <p className="text-muted-foreground mt-2 line-clamp-2 text-sm leading-relaxed">
                                                        {product.description}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="text-primary shrink-0 text-right text-sm font-bold tabular-nums">
                                                {formatCurrency(
                                                    product.base_price,
                                                )}
                                            </p>
                                        </div>

                                        <div className="border-border/70 mt-5 flex items-center justify-between gap-3 border-t pt-4">
                                            <div className="min-w-0">
                                                <p className="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">
                                                    Ketersediaan
                                                </p>
                                                <p className="text-muted-foreground mt-1 truncate text-xs">
                                                    {product.is_available
                                                        ? 'Tampil di menu QR'
                                                        : 'Disembunyikan dari menu QR'}
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    toggleAvailability(product)
                                                }
                                                disabled={
                                                    processingProductId !== null
                                                }
                                                aria-busy={
                                                    processingProductId ===
                                                    product.id
                                                }
                                                aria-pressed={
                                                    product.is_available
                                                }
                                                aria-label={`${product.is_available ? 'Tandai habis' : 'Tandai tersedia'}: ${product.name}`}
                                                className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-xs font-bold transition-colors ${product.is_available ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/15 dark:border-emerald-400/30 dark:text-emerald-300' : 'border-border bg-muted text-muted-foreground hover:bg-secondary'}`}
                                            >
                                                <span
                                                    className={`relative h-6 w-10 rounded-full transition-colors ${product.is_available ? 'bg-emerald-600 dark:bg-emerald-500' : 'bg-muted-foreground/30'}`}
                                                    aria-hidden="true"
                                                >
                                                    <span
                                                        className={`bg-background absolute top-1 size-4 rounded-full shadow-sm transition-transform ${product.is_available ? 'left-5' : 'left-1'}`}
                                                    />
                                                </span>
                                                {product.is_available
                                                    ? 'Tersedia'
                                                    : 'Habis'}
                                            </button>
                                        </div>
                                    </div>
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent className="border-border/80 rounded-[1.5rem] p-5 sm:max-w-xl sm:p-7">
                    <DialogHeader className="pr-8">
                        <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                            Katalog outlet
                        </p>
                        <DialogTitle className="font-display text-2xl">
                            Tambah produk
                        </DialogTitle>
                        <DialogDescription>
                            Produk dibuat langsung pada outlet yang sedang
                            aktif.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createProduct} className="grid gap-5">
                        <div className="grid gap-4 sm:grid-cols-[minmax(0,1.25fr)_minmax(10rem,0.75fr)]">
                            <div className="grid gap-2">
                                <Label htmlFor="product-name">
                                    Nama produk
                                </Label>
                                <Input
                                    id="product-name"
                                    aria-invalid={Boolean(form.errors.name)}
                                    aria-describedby={
                                        form.errors.name
                                            ? 'product-name-error'
                                            : undefined
                                    }
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    className="min-h-11 rounded-xl"
                                    autoFocus
                                    required
                                />
                                <InputError
                                    id="product-name-error"
                                    message={form.errors.name}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="product-price">
                                    Harga (Rp)
                                </Label>
                                <Input
                                    id="product-price"
                                    aria-invalid={Boolean(
                                        form.errors.base_price,
                                    )}
                                    aria-describedby={
                                        form.errors.base_price
                                            ? 'product-price-error'
                                            : undefined
                                    }
                                    type="number"
                                    min="0"
                                    value={form.data.base_price}
                                    onChange={(event) =>
                                        form.setData(
                                            'base_price',
                                            event.target.value,
                                        )
                                    }
                                    className="min-h-11 rounded-xl"
                                    required
                                />
                                <InputError
                                    id="product-price-error"
                                    message={form.errors.base_price}
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-category">Kategori</Label>
                            <div className="relative">
                                <select
                                    id="product-category"
                                    aria-invalid={Boolean(
                                        form.errors.category_id,
                                    )}
                                    aria-describedby={
                                        form.errors.category_id
                                            ? 'product-category-error'
                                            : undefined
                                    }
                                    value={form.data.category_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'category_id',
                                            event.target.value,
                                        )
                                    }
                                    className="border-input focus-visible:border-ring focus-visible:ring-ring/50 bg-background min-h-11 w-full appearance-none rounded-xl border px-3 pr-9 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                >
                                    <option value="">Tanpa kategori</option>
                                    {categories
                                        .filter(
                                            (category) => category.is_active,
                                        )
                                        .map((category) => (
                                            <option
                                                key={category.id}
                                                value={category.id}
                                            >
                                                {category.name}
                                            </option>
                                        ))}
                                </select>
                                <ChevronDown
                                    className="text-muted-foreground pointer-events-none absolute top-1/2 right-3.5 size-4 -translate-y-1/2"
                                    aria-hidden="true"
                                />
                            </div>
                            <InputError
                                id="product-category-error"
                                message={form.errors.category_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-description">
                                Deskripsi{' '}
                                <span className="text-muted-foreground font-normal">
                                    (opsional)
                                </span>
                            </Label>
                            <textarea
                                id="product-description"
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={
                                    form.errors.description
                                        ? 'product-description-error'
                                        : undefined
                                }
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                rows={3}
                                className="border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 w-full resize-y rounded-xl border bg-transparent px-3 py-2.5 text-sm shadow-xs outline-none focus-visible:ring-[3px]"
                                placeholder="Contoh: Nasi hangat dengan ayam panggang dan sambal..."
                            />
                            <InputError
                                id="product-description-error"
                                message={form.errors.description}
                            />
                        </div>
                        <div className="border-border/70 bg-muted/25 grid gap-2 rounded-2xl border border-dashed p-4">
                            <Label htmlFor="product-image">
                                Foto produk{' '}
                                <span className="text-muted-foreground font-normal">
                                    (opsional)
                                </span>
                            </Label>
                            <Input
                                id="product-image"
                                aria-invalid={Boolean(form.errors.image)}
                                aria-describedby={
                                    form.errors.image
                                        ? 'product-image-error'
                                        : undefined
                                }
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    form.setData(
                                        'image',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                className="bg-background min-h-11 cursor-pointer rounded-xl"
                            />
                            <p className="text-muted-foreground text-xs">
                                JPG, PNG, atau WebP, maksimal 5 MB. Gambar akan
                                dioptimalkan otomatis.
                            </p>
                            <InputError
                                id="product-image-error"
                                message={form.errors.image}
                            />
                            {form.progress && (
                                <p className="text-primary text-xs font-medium">
                                    Mengunggah {form.progress.percentage}%
                                </p>
                            )}
                        </div>
                        <label
                            htmlFor="product-featured"
                            className="border-border/70 bg-muted/25 flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm"
                        >
                            <Checkbox
                                id="product-featured"
                                checked={form.data.is_featured}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'is_featured',
                                        checked === true,
                                    )
                                }
                            />{' '}
                            Tandai sebagai produk favorit
                        </label>
                        <DialogFooter className="border-border/70 mt-1 border-t pt-4">
                            <Button
                                type="submit"
                                className="min-h-11 rounded-xl"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />} Tambah produk
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Products.layout = {
    breadcrumbs: [{ title: 'Produk & menu', href: '/products' }],
};
