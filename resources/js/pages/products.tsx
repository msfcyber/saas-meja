import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    Edit3,
    ImageIcon,
    Layers3,
    ListTree,
    Plus,
    Search,
    SlidersHorizontal,
    Soup,
    Star,
    Trash2,
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

type Category = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    products_count: number;
};
type Variant = {
    id: number;
    name: string;
    price_delta: number;
    is_default: boolean;
    is_active: boolean;
};
type ModifierOption = {
    id: number;
    name: string;
    price_delta: number;
    is_active: boolean;
};
type Modifier = {
    id: number;
    name: string;
    selection_type: 'single' | 'multiple';
    minimum_selections: number;
    maximum_selections: number;
    is_required: boolean;
    is_active: boolean;
    options: ModifierOption[];
};
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
    variants: Variant[];
    modifier_ids: number[];
};

type Props = {
    categories: Category[];
    filters: { search: string; category: number | null };
    products: Product[];
    modifiers: Modifier[];
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
    modifiers,
    summary,
}: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingProduct, setEditingProduct] = useState<Product | null>(null);
    const [isCatalogOpen, setIsCatalogOpen] = useState(false);
    const [catalogTab, setCatalogTab] = useState<'categories' | 'modifiers'>(
        'categories',
    );
    const [editingCategory, setEditingCategory] = useState<Category | null>(
        null,
    );
    const [editingModifier, setEditingModifier] = useState<Modifier | null>(
        null,
    );
    const [optionModifierId, setOptionModifierId] = useState<number | null>(
        null,
    );
    const [editingOption, setEditingOption] = useState<ModifierOption | null>(
        null,
    );
    const [configuredProductId, setConfiguredProductId] = useState<number | null>(
        null,
    );
    const [editingVariant, setEditingVariant] = useState<Variant | null>(null);
    const [modifierIds, setModifierIds] = useState<number[]>([]);
    const [processingProductId, setProcessingProductId] = useState<
        number | null
    >(null);
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        name: '',
        category_id: '',
        description: '',
        image: null as File | null,
        remove_image: false,
        base_price: '',
        is_active: true,
        is_available: true,
        is_featured: false,
        _method: 'post' as 'post' | 'patch',
    });
    const categoryForm = useForm({
        name: '',
        description: '',
        is_active: true,
    });
    const modifierForm = useForm({
        name: '',
        selection_type: 'single' as 'single' | 'multiple',
        minimum_selections: '0',
        maximum_selections: '1',
        is_required: false,
        is_active: true,
    });
    const optionForm = useForm({
        name: '',
        price_delta: '0',
        is_active: true,
    });
    const variantForm = useForm({
        name: '',
        price_delta: '0',
        is_default: false,
        is_active: true,
    });
    const configuredProduct = products.find(
        (product) => product.id === configuredProductId,
    );
    const optionModifier = modifiers.find(
        (modifier) => modifier.id === optionModifierId,
    );

    function applyFilters(category: number | null) {
        router.get(
            '/products',
            { search, category: category ?? undefined },
            { preserveState: true, replace: true },
        );
    }

    function submitProduct(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(editingProduct ? `/products/${editingProduct.id}` : '/products', {
            onSuccess: () => {
                closeProductForm();
            },
        });
    }

    function openCreateProduct() {
        setEditingProduct(null);
        form.reset();
        form.clearErrors();
        form.setData('_method', 'post');
        setIsCreateOpen(true);
    }

    function editProduct(product: Product) {
        setEditingProduct(product);
        form.clearErrors();
        form.setData({
            name: product.name,
            category_id: product.category ? String(product.category.id) : '',
            description: product.description ?? '',
            image: null,
            remove_image: false,
            base_price: String(product.base_price),
            is_active: product.is_active,
            is_available: product.is_available,
            is_featured: product.is_featured,
            _method: 'patch',
        });
        setIsCreateOpen(true);
    }

    function closeProductForm() {
        form.reset();
        form.clearErrors();
        setEditingProduct(null);
        setIsCreateOpen(false);
    }

    function submitCategory(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                categoryForm.reset();
                setEditingCategory(null);
            },
        };

        if (editingCategory) {
            categoryForm.patch(`/categories/${editingCategory.id}`, options);
        } else {
            categoryForm.post('/categories', options);
        }
    }

    function editCategory(category: Category) {
        setEditingCategory(category);
        categoryForm.clearErrors();
        categoryForm.setData({
            name: category.name,
            description: category.description ?? '',
            is_active: category.is_active,
        });
    }

    function submitModifier(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                modifierForm.reset();
                setEditingModifier(null);
            },
        };

        if (editingModifier) {
            modifierForm.patch(`/modifiers/${editingModifier.id}`, options);
        } else {
            modifierForm.post('/modifiers', options);
        }
    }

    function editModifier(modifier: Modifier) {
        setEditingModifier(modifier);
        modifierForm.clearErrors();
        modifierForm.setData({
            name: modifier.name,
            selection_type: modifier.selection_type,
            minimum_selections: String(modifier.minimum_selections),
            maximum_selections: String(modifier.maximum_selections),
            is_required: modifier.is_required,
            is_active: modifier.is_active,
        });
    }

    function selectModifierOptions(modifier: Modifier) {
        setOptionModifierId(modifier.id);
        setEditingOption(null);
        optionForm.reset();
        optionForm.clearErrors();
    }

    function submitOption(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!optionModifierId) {
            return;
        }

        const options = {
            onSuccess: () => {
                optionForm.reset();
                setEditingOption(null);
            },
        };

        if (editingOption) {
            optionForm.patch(`/modifier-options/${editingOption.id}`, options);
        } else {
            optionForm.post(`/modifiers/${optionModifierId}/options`, options);
        }
    }

    function editOption(option: ModifierOption) {
        setEditingOption(option);
        optionForm.clearErrors();
        optionForm.setData({
            name: option.name,
            price_delta: String(option.price_delta),
            is_active: option.is_active,
        });
    }

    function openProductConfiguration(product: Product) {
        setConfiguredProductId(product.id);
        setModifierIds(product.modifier_ids);
        setEditingVariant(null);
        variantForm.reset();
        variantForm.clearErrors();
    }

    function submitVariant(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!configuredProduct) {
            return;
        }

        const options = {
            onSuccess: () => {
                variantForm.reset();
                setEditingVariant(null);
            },
        };

        if (editingVariant) {
            variantForm.patch(`/product-variants/${editingVariant.id}`, options);
        } else {
            variantForm.post(
                `/products/${configuredProduct.id}/variants`,
                options,
            );
        }
    }

    function editVariant(variant: Variant) {
        setEditingVariant(variant);
        variantForm.clearErrors();
        variantForm.setData({
            name: variant.name,
            price_delta: String(variant.price_delta),
            is_default: variant.is_default,
            is_active: variant.is_active,
        });
    }

    function saveModifierAssignments() {
        if (!configuredProduct) {
            return;
        }

        router.put(
            `/products/${configuredProduct.id}/modifiers`,
            { modifier_ids: modifierIds },
            { preserveScroll: true },
        );
    }

    function toggleModifierAssignment(modifierId: number) {
        setModifierIds((current) =>
            current.includes(modifierId)
                ? current.filter((id) => id !== modifierId)
                : [...current, modifierId],
        );
    }

    function removeResource(url: string, name: string) {
        if (!window.confirm(`Hapus ${name}? Tindakan ini tidak dapat dibatalkan.`)) {
            return;
        }

        router.delete(url, {
            preserveScroll: true,
            onError: (errors) => {
                toast.error(`Tidak dapat menghapus ${name}`, {
                    description:
                        Object.values(errors)[0] ??
                        'Coba lagi dalam beberapa saat.',
                });
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
                    <div className="flex flex-wrap gap-2">
                        <Button
                            size="lg"
                            variant="outline"
                            className="min-h-12 rounded-xl"
                            onClick={() => setIsCatalogOpen(true)}
                        >
                            <ListTree aria-hidden="true" /> Kelola katalog
                        </Button>
                        <Button
                            size="lg"
                            className="min-h-12 rounded-xl shadow-[0_14px_30px_-18px_var(--primary)]"
                            onClick={openCreateProduct}
                        >
                            <Plus aria-hidden="true" /> Tambah produk
                        </Button>
                    </div>
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
                                         onClick={openCreateProduct}
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
                                                        ? 'Tampil dan dapat dipesan di menu QR'
                                                        : 'Tampil sebagai habis di menu QR'}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 flex-col gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="min-h-10 rounded-xl text-xs"
                                                    onClick={() =>
                                                        openProductConfiguration(
                                                            product,
                                                        )
                                                    }
                                                >
                                                    <Layers3 aria-hidden="true" />
                                                    Pilihan
                                                </Button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        toggleAvailability(product)
                                                    }
                                                    disabled={
                                                        processingProductId !==
                                                        null
                                                    }
                                                    aria-busy={
                                                        processingProductId ===
                                                        product.id
                                                    }
                                                    aria-pressed={
                                                        product.is_available
                                                    }
                                                    aria-label={`${product.is_available ? 'Tandai habis' : 'Tandai tersedia'}: ${product.name}`}
                                                    className={`inline-flex min-h-10 items-center gap-2 rounded-xl border px-3 text-xs font-bold transition-colors ${product.is_available ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 hover:bg-emerald-500/15 dark:border-emerald-400/30 dark:text-emerald-300' : 'border-border bg-muted text-muted-foreground hover:bg-secondary'}`}
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
                                        <div className="border-border/70 mt-4 flex gap-2 border-t pt-4">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                className="min-h-10 flex-1 rounded-xl text-xs"
                                                onClick={() => editProduct(product)}
                                            >
                                                <Edit3 aria-hidden="true" /> Ubah produk
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive min-h-10 rounded-xl px-3"
                                                aria-label={`Hapus produk ${product.name}`}
                                                onClick={() =>
                                                    removeResource(
                                                        `/products/${product.id}`,
                                                        `produk ${product.name}`,
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </div>
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>

            <Dialog open={isCatalogOpen} onOpenChange={setIsCatalogOpen}>
                <DialogContent className="border-border/80 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[1.5rem] p-5 sm:max-w-3xl sm:p-7">
                    <DialogHeader className="pr-8">
                        <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                            Katalog outlet
                        </p>
                        <DialogTitle className="font-display text-2xl">
                            Kelola kategori & modifier
                        </DialogTitle>
                        <DialogDescription>
                            Perubahan berlaku untuk outlet yang sedang aktif.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="bg-muted grid grid-cols-2 rounded-xl p-1">
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                catalogTab === 'categories'
                                    ? 'secondary'
                                    : 'ghost'
                            }
                            className="min-h-10 rounded-lg"
                            onClick={() => setCatalogTab('categories')}
                        >
                            Kategori
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant={
                                catalogTab === 'modifiers' ? 'secondary' : 'ghost'
                            }
                            className="min-h-10 rounded-lg"
                            onClick={() => setCatalogTab('modifiers')}
                        >
                            Modifier
                        </Button>
                    </div>

                    {catalogTab === 'categories' ? (
                        <div className="grid gap-6">
                            <form
                                onSubmit={submitCategory}
                                className="border-border/70 bg-muted/20 grid gap-4 rounded-2xl border p-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-display font-bold">
                                        {editingCategory
                                            ? 'Ubah kategori'
                                            : 'Tambah kategori'}
                                    </h3>
                                    {editingCategory && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setEditingCategory(null);
                                                categoryForm.reset();
                                            }}
                                        >
                                            Batal
                                        </Button>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="category-name">Nama</Label>
                                        <Input
                                            id="category-name"
                                            value={categoryForm.data.name}
                                            onChange={(event) =>
                                                categoryForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            aria-invalid={Boolean(
                                                categoryForm.errors.name,
                                            )}
                                            className="min-h-11 rounded-xl"
                                            required
                                        />
                                        <InputError
                                            message={categoryForm.errors.name}
                                        />
                                    </div>
                                    <Label htmlFor="category-active" className="border-border/70 bg-background flex min-h-11 items-center gap-3 self-end rounded-xl border px-3 text-sm">
                                        <Checkbox
                                            id="category-active"
                                            checked={categoryForm.data.is_active}
                                            onCheckedChange={(checked) =>
                                                categoryForm.setData(
                                                    'is_active',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Kategori aktif
                                    </Label>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="category-description">
                                        Deskripsi <span className="text-muted-foreground font-normal">(opsional)</span>
                                    </Label>
                                    <textarea
                                        id="category-description"
                                        value={categoryForm.data.description}
                                        onChange={(event) =>
                                            categoryForm.setData(
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                        className="border-input focus-visible:border-ring focus-visible:ring-ring/50 min-h-20 w-full rounded-xl border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                                    />
                                    <InputError
                                        message={categoryForm.errors.description}
                                    />
                                </div>
                                <Button
                                    type="submit"
                                    className="min-h-11 justify-self-start rounded-xl"
                                    disabled={categoryForm.processing}
                                >
                                    {categoryForm.processing && <Spinner />}
                                    {editingCategory ? 'Simpan kategori' : 'Tambah kategori'}
                                </Button>
                            </form>
                            <div className="grid gap-2">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="border-border/70 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
                                    >
                                        <div>
                                            <p className="font-semibold">{category.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {category.products_count} produk ·{' '}
                                                {category.is_active ? 'Aktif' : 'Nonaktif'}
                                            </p>
                                        </div>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="min-h-10 min-w-10"
                                                aria-label={`Ubah kategori ${category.name}`}
                                                onClick={() => editCategory(category)}
                                            >
                                                <Edit3 aria-hidden="true" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="text-destructive min-h-10 min-w-10"
                                                aria-label={`Hapus kategori ${category.name}`}
                                                onClick={() =>
                                                    removeResource(
                                                        `/categories/${category.id}`,
                                                        `kategori ${category.name}`,
                                                    )
                                                }
                                            >
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="grid gap-6">
                            <form
                                onSubmit={submitModifier}
                                className="border-border/70 bg-muted/20 grid gap-4 rounded-2xl border p-4"
                            >
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-display font-bold">
                                        {editingModifier
                                            ? 'Ubah modifier'
                                            : 'Tambah modifier'}
                                    </h3>
                                    {editingModifier && (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                setEditingModifier(null);
                                                modifierForm.reset();
                                            }}
                                        >
                                            Batal
                                        </Button>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="modifier-name">Nama</Label>
                                        <Input
                                            id="modifier-name"
                                            value={modifierForm.data.name}
                                            onChange={(event) =>
                                                modifierForm.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-11 rounded-xl"
                                            required
                                        />
                                        <InputError
                                            message={modifierForm.errors.name}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="modifier-type">Tipe pilihan</Label>
                                        <select
                                            id="modifier-type"
                                            value={modifierForm.data.selection_type}
                                            onChange={(event) =>
                                                modifierForm.setData(
                                                    'selection_type',
                                                    event.target.value as
                                                        | 'single'
                                                        | 'multiple',
                                                )
                                            }
                                            className="border-input bg-background min-h-11 rounded-xl border px-3 text-sm"
                                        >
                                            <option value="single">Satu pilihan</option>
                                            <option value="multiple">Banyak pilihan</option>
                                        </select>
                                    </div>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="modifier-minimum">Minimum</Label>
                                        <Input
                                            id="modifier-minimum"
                                            type="number"
                                            min="0"
                                            value={modifierForm.data.minimum_selections}
                                            onChange={(event) =>
                                                modifierForm.setData(
                                                    'minimum_selections',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-11 rounded-xl"
                                            required
                                        />
                                        <InputError message={modifierForm.errors.minimum_selections} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="modifier-maximum">Maksimum</Label>
                                        <Input
                                            id="modifier-maximum"
                                            type="number"
                                            min="1"
                                            max={modifierForm.data.selection_type === 'single' ? '1' : undefined}
                                            value={modifierForm.data.maximum_selections}
                                            onChange={(event) =>
                                                modifierForm.setData(
                                                    'maximum_selections',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-h-11 rounded-xl"
                                            required
                                        />
                                        <InputError message={modifierForm.errors.maximum_selections} />
                                    </div>
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    <Label htmlFor="modifier-required" className="border-border/70 bg-background flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm">
                                        <Checkbox
                                            id="modifier-required"
                                            checked={modifierForm.data.is_required}
                                            onCheckedChange={(checked) =>
                                                modifierForm.setData(
                                                    'is_required',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Wajib dipilih
                                    </Label>
                                    <Label htmlFor="modifier-active" className="border-border/70 bg-background flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm">
                                        <Checkbox
                                            id="modifier-active"
                                            checked={modifierForm.data.is_active}
                                            onCheckedChange={(checked) =>
                                                modifierForm.setData(
                                                    'is_active',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Modifier aktif
                                    </Label>
                                </div>
                                <Button
                                    type="submit"
                                    className="min-h-11 justify-self-start rounded-xl"
                                    disabled={modifierForm.processing}
                                >
                                    {modifierForm.processing && <Spinner />}
                                    {editingModifier ? 'Simpan modifier' : 'Tambah modifier'}
                                </Button>
                            </form>
                            <div className="grid gap-2">
                                {modifiers.map((modifier) => (
                                    <div
                                        key={modifier.id}
                                        className="border-border/70 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"
                                    >
                                        <div>
                                            <p className="font-semibold">{modifier.name}</p>
                                            <p className="text-muted-foreground text-xs">
                                                {modifier.selection_type === 'single' ? 'Satu pilihan' : 'Banyak pilihan'} · {modifier.options.length} opsi
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap gap-1">
                                            <Button type="button" variant="outline" size="sm" className="min-h-10 rounded-lg" onClick={() => selectModifierOptions(modifier)}>
                                                Opsi
                                            </Button>
                                            <Button type="button" variant="ghost" size="icon" className="min-h-10 min-w-10" aria-label={`Ubah modifier ${modifier.name}`} onClick={() => editModifier(modifier)}>
                                                <Edit3 aria-hidden="true" />
                                            </Button>
                                            <Button type="button" variant="ghost" size="icon" className="text-destructive min-h-10 min-w-10" aria-label={`Hapus modifier ${modifier.name}`} onClick={() => removeResource(`/modifiers/${modifier.id}`, `modifier ${modifier.name}`)}>
                                                <Trash2 aria-hidden="true" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            {optionModifier && (
                                <section className="border-border/70 grid gap-4 rounded-2xl border p-4">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 className="font-display font-bold">Opsi: {optionModifier.name}</h3>
                                            <p className="text-muted-foreground text-xs">Harga dapat dikurangi dengan nilai negatif.</p>
                                        </div>
                                        <Button type="button" variant="ghost" size="sm" onClick={() => setOptionModifierId(null)}>Tutup</Button>
                                    </div>
                                    <form onSubmit={submitOption} className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                                        <div className="grid gap-2">
                                            <Label htmlFor="option-name">Nama opsi</Label>
                                            <Input id="option-name" value={optionForm.data.name} onChange={(event) => optionForm.setData('name', event.target.value)} className="min-h-11 rounded-xl" required />
                                            <InputError message={optionForm.errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="option-price">Selisih (Rp)</Label>
                                            <Input id="option-price" type="number" value={optionForm.data.price_delta} onChange={(event) => optionForm.setData('price_delta', event.target.value)} className="min-h-11 rounded-xl" required />
                                            <InputError message={optionForm.errors.price_delta} />
                                        </div>
                                        <Button type="submit" className="min-h-11 rounded-xl" disabled={optionForm.processing}>
                                            {optionForm.processing && <Spinner />}{editingOption ? 'Simpan' : 'Tambah'}
                                        </Button>
                                    </form>
                                    {editingOption && <Button type="button" variant="ghost" size="sm" className="justify-self-start" onClick={() => { setEditingOption(null); optionForm.reset(); }}>Batal ubah opsi</Button>}
                                    <div className="grid gap-2">
                                        {optionModifier.options.map((option) => (
                                            <div key={option.id} className="border-border/70 flex items-center justify-between gap-3 rounded-xl border p-3">
                                                <p className="text-sm font-medium">{option.name} <span className="text-muted-foreground font-normal">({formatCurrency(option.price_delta)})</span></p>
                                                <div className="flex gap-1">
                                                    <Button type="button" variant="ghost" size="icon" className="min-h-10 min-w-10" aria-label={`Ubah opsi ${option.name}`} onClick={() => editOption(option)}><Edit3 aria-hidden="true" /></Button>
                                                    <Button type="button" variant="ghost" size="icon" className="text-destructive min-h-10 min-w-10" aria-label={`Hapus opsi ${option.name}`} onClick={() => removeResource(`/modifier-options/${option.id}`, `opsi ${option.name}`)}><Trash2 aria-hidden="true" /></Button>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </section>
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={configuredProduct !== undefined && configuredProduct !== null}
                onOpenChange={(open) => !open && setConfiguredProductId(null)}
            >
                <DialogContent className="border-border/80 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[1.5rem] p-5 sm:max-w-3xl sm:p-7">
                    {configuredProduct && (
                        <>
                            <DialogHeader className="pr-8">
                                <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">Produk</p>
                                <DialogTitle className="font-display text-2xl">Pilihan {configuredProduct.name}</DialogTitle>
                                <DialogDescription>Kelola varian harga dan modifier yang dapat dipilih tamu.</DialogDescription>
                            </DialogHeader>
                            <section className="grid gap-4">
                                <div className="flex items-center justify-between gap-3"><h3 className="font-display text-lg font-bold">Varian</h3><span className="text-muted-foreground text-xs">{configuredProduct.variants.length} varian</span></div>
                                <form onSubmit={submitVariant} className="border-border/70 bg-muted/20 grid gap-3 rounded-2xl border p-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
                                    <div className="grid gap-2"><Label htmlFor="variant-name">Nama varian</Label><Input id="variant-name" value={variantForm.data.name} onChange={(event) => variantForm.setData('name', event.target.value)} className="min-h-11 rounded-xl" required /><InputError message={variantForm.errors.name} /></div>
                                    <div className="grid gap-2"><Label htmlFor="variant-price">Selisih (Rp)</Label><Input id="variant-price" type="number" value={variantForm.data.price_delta} onChange={(event) => variantForm.setData('price_delta', event.target.value)} className="min-h-11 rounded-xl" required /><InputError message={variantForm.errors.price_delta} /></div>
                                    <Button type="submit" className="min-h-11 rounded-xl" disabled={variantForm.processing}>{variantForm.processing && <Spinner />}{editingVariant ? 'Simpan' : 'Tambah'}</Button>
                                    <Label htmlFor="variant-default" className="border-border/70 bg-background flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm"><Checkbox id="variant-default" checked={variantForm.data.is_default} onCheckedChange={(checked) => variantForm.setData('is_default', checked === true)} />Default</Label>
                                    <Label htmlFor="variant-active" className="border-border/70 bg-background flex min-h-11 items-center gap-3 rounded-xl border px-3 text-sm"><Checkbox id="variant-active" checked={variantForm.data.is_active} onCheckedChange={(checked) => variantForm.setData('is_active', checked === true)} />Aktif</Label>
                                </form>
                                {editingVariant && <Button type="button" variant="ghost" size="sm" className="justify-self-start" onClick={() => { setEditingVariant(null); variantForm.reset(); }}>Batal ubah varian</Button>}
                                <div className="grid gap-2">
                                    {configuredProduct.variants.map((variant) => <div key={variant.id} className="border-border/70 flex flex-wrap items-center justify-between gap-3 rounded-xl border p-3"><p className="text-sm font-medium">{variant.name} <span className="text-muted-foreground font-normal">({formatCurrency(variant.price_delta)}){variant.is_default ? ' · Default' : ''}{!variant.is_active ? ' · Nonaktif' : ''}</span></p><div className="flex gap-1"><Button type="button" variant="ghost" size="icon" className="min-h-10 min-w-10" aria-label={`Ubah varian ${variant.name}`} onClick={() => editVariant(variant)}><Edit3 aria-hidden="true" /></Button><Button type="button" variant="ghost" size="icon" className="text-destructive min-h-10 min-w-10" aria-label={`Hapus varian ${variant.name}`} onClick={() => removeResource(`/product-variants/${variant.id}`, `varian ${variant.name}`)}><Trash2 aria-hidden="true" /></Button></div></div>)}
                                </div>
                            </section>
                            <section className="border-border/70 grid gap-4 border-t pt-5">
                                <div><h3 className="font-display text-lg font-bold">Modifier produk</h3><p className="text-muted-foreground mt-1 text-sm">Pilih modifier yang tersedia untuk produk ini.</p></div>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {modifiers.map((modifier) => <Label key={modifier.id} htmlFor={`product-modifier-${modifier.id}`} className="border-border/70 bg-muted/20 flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm"><Checkbox id={`product-modifier-${modifier.id}`} checked={modifierIds.includes(modifier.id)} onCheckedChange={() => toggleModifierAssignment(modifier.id)} /> <span>{modifier.name}<span className="text-muted-foreground block text-xs">{modifier.options.length} opsi · {modifier.is_active ? 'Aktif' : 'Nonaktif'}</span></span></Label>)}
                                    {modifiers.length === 0 && <p className="text-muted-foreground text-sm">Buat modifier terlebih dahulu melalui Kelola katalog.</p>}
                                </div>
                                <Button type="button" className="min-h-11 justify-self-start rounded-xl" onClick={saveModifierAssignments}>Simpan modifier produk</Button>
                            </section>
                        </>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={isCreateOpen}
                onOpenChange={(open) => (open ? setIsCreateOpen(true) : closeProductForm())}
            >
                <DialogContent className="border-border/80 rounded-[1.5rem] p-5 sm:max-w-xl sm:p-7">
                    <DialogHeader className="pr-8">
                        <p className="text-primary text-[10px] font-bold tracking-[0.18em] uppercase">
                            Katalog outlet
                        </p>
                        <DialogTitle className="font-display text-2xl">
                            {editingProduct ? 'Ubah produk' : 'Tambah produk'}
                        </DialogTitle>
                        <DialogDescription>
                            {editingProduct
                                ? 'Perubahan berlaku pada produk di outlet aktif tanpa mengubah histori order.'
                                : 'Produk dibuat langsung pada outlet yang sedang aktif.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitProduct} className="grid gap-5">
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
                            {editingProduct?.image_url && (
                                <div className="flex items-center gap-3">
                                    <img
                                        src={editingProduct.image_url}
                                        alt={`Foto saat ini: ${editingProduct.name}`}
                                        className="size-14 rounded-lg object-cover"
                                    />
                                    <Label className="flex min-h-10 items-center gap-2 text-xs">
                                        <Checkbox
                                            checked={form.data.remove_image}
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'remove_image',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Hapus foto saat ini
                                    </Label>
                                </div>
                            )}
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
                        <div className="grid gap-2 sm:grid-cols-3">
                            <Label
                                htmlFor="product-active"
                                className="border-border/70 bg-muted/25 flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm"
                            >
                                <Checkbox
                                    id="product-active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', checked === true)
                                    }
                                />
                                Produk aktif
                            </Label>
                            <Label
                                htmlFor="product-available"
                                className="border-border/70 bg-muted/25 flex min-h-12 items-center gap-3 rounded-xl border px-3 text-sm"
                            >
                                <Checkbox
                                    id="product-available"
                                    checked={form.data.is_available}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_available',
                                            checked === true,
                                        )
                                    }
                                />
                                Tersedia dipesan
                            </Label>
                            <Label
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
                                />
                                Produk favorit
                            </Label>
                        </div>
                        <DialogFooter className="border-border/70 mt-1 border-t pt-4">
                            <Button
                                type="submit"
                                className="min-h-11 rounded-xl"
                                disabled={form.processing}
                            >
                                {form.processing && <Spinner />}
                                {editingProduct ? 'Simpan perubahan' : 'Tambah produk'}
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
