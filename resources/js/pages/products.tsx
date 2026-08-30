import { Head, router, useForm } from "@inertiajs/react";
import { ImageIcon, Plus, Search, SlidersHorizontal, Soup, Star } from "lucide-react";
import { useState } from "react";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { formatCurrency } from "@/data/demo";

type Category = { id: number; name: string; is_active: boolean };
type Product = {
    id: number;
    name: string;
    category: Pick<Category, "id" | "name"> | null;
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

export default function Products({ categories, filters, products, summary }: Props) {
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const form = useForm({
        name: "",
        category_id: "",
        description: "",
        image: null as File | null,
        base_price: "",
        is_active: true,
        is_available: true,
        is_featured: false,
    });

    function applyFilters(category: number | null) {
        router.get(
            "/products",
            { search, category: category ?? undefined },
            { preserveState: true, replace: true },
        );
    }

    function createProduct(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post("/products", {
            onSuccess: () => {
                form.reset();
                setIsCreateOpen(false);
            },
        });
    }

    return (
        <>
            <Head title="Produk & menu" />
            <div className="mx-auto w-full max-w-[1500px] flex-1 p-4 sm:p-6 lg:p-8">
                <div className="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p className="text-xs font-bold tracking-[0.16em] text-primary uppercase">
                            Katalog outlet
                        </p>
                        <h1 className="font-display mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                            Produk & menu
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Atur harga dan ketersediaan menu pada outlet aktif.
                        </p>
                    </div>
                    <Button
                        size="lg"
                        className="min-h-12 rounded-full"
                        onClick={() => setIsCreateOpen(true)}
                    >
                        <Plus aria-hidden="true" /> Tambah produk
                    </Button>
                </div>

                <section className="mt-8 grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: "Total produk",
                            value: summary.products,
                            detail: `${summary.available_products} tersedia`,
                            icon: Soup,
                        },
                        {
                            label: "Kategori",
                            value: summary.categories,
                            detail: "Di outlet aktif",
                            icon: SlidersHorizontal,
                        },
                        {
                            label: "Produk favorit",
                            value: summary.featured_products,
                            detail: "Ditandai populer",
                            icon: Star,
                        },
                    ].map((metric) => (
                        <article
                            key={metric.label}
                            className="flex items-center gap-4 rounded-[1.3rem] border bg-card p-5"
                        >
                            <span className="flex size-11 items-center justify-center rounded-xl bg-secondary text-primary">
                                <metric.icon className="size-5" aria-hidden="true" />
                            </span>
                            <div>
                                <p className="text-xs text-muted-foreground">{metric.label}</p>
                                <p className="mt-1 text-xl font-bold">
                                    {metric.value}{" "}
                                    <span className="text-xs font-normal text-muted-foreground">
                                        · {metric.detail}
                                    </span>
                                </p>
                            </div>
                        </article>
                    ))}
                </section>

                <section className="mt-5 overflow-hidden rounded-[1.5rem] border bg-card">
                    <div className="flex flex-col gap-4 border-b p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                        <div className="flex gap-2 overflow-x-auto">
                            <button
                                type="button"
                                onClick={() => applyFilters(null)}
                                aria-pressed={filters.category === null}
                                className={`min-h-10 shrink-0 rounded-full px-4 text-xs font-bold ${filters.category === null ? "bg-foreground text-background" : "bg-muted text-muted-foreground hover:text-foreground"}`}
                            >
                                Semua
                            </button>
                            {categories.map((category) => (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() => applyFilters(category.id)}
                                    aria-pressed={filters.category === category.id}
                                    className={`min-h-10 shrink-0 rounded-full px-4 text-xs font-bold ${filters.category === category.id ? "bg-foreground text-background" : "bg-muted text-muted-foreground hover:text-foreground"}`}
                                >
                                    {category.name}
                                </button>
                            ))}
                        </div>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters(filters.category);
                            }}
                            className="relative block sm:w-64"
                        >
                            <Search
                                className="absolute top-1/2 left-4 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <label className="sr-only" htmlFor="product-search">
                                Cari produk
                            </label>
                            <Input
                                id="product-search"
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                className="h-11 rounded-full pr-4 pl-10"
                                placeholder="Cari produk..."
                            />
                        </form>
                    </div>
                    <div className="divide-y">
                        {products.length === 0 ? (
                            <div className="p-10 text-center">
                                <ImageIcon
                                    className="mx-auto size-7 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <p className="mt-3 font-semibold">Belum ada produk yang cocok.</p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Tambahkan produk pertama atau ubah filter pencarian.
                                </p>
                            </div>
                        ) : (
                            products.map((product) => (
                                <article
                                    key={product.id}
                                    className="grid grid-cols-[auto_1fr_auto] items-center gap-4 p-4 transition-colors hover:bg-muted/40 sm:grid-cols-[auto_1fr_0.5fr_auto] sm:px-6"
                                >
                                    <div className="relative flex size-16 items-center justify-center overflow-hidden rounded-2xl bg-secondary text-primary">
                                        {product.image_url ? (
                                            <img
                                                src={product.image_url}
                                                alt=""
                                                className="size-full object-cover"
                                                loading="lazy"
                                            />
                                        ) : (
                                            <Soup className="size-6" aria-hidden="true" />
                                        )}
                                        {product.is_featured && (
                                            <span className="absolute top-1 right-1 flex size-5 items-center justify-center rounded-full bg-amber-400 text-amber-950 ring-2 ring-card">
                                                <Star
                                                    className="size-3 fill-current"
                                                    aria-hidden="true"
                                                />
                                                <span className="sr-only">Produk favorit</span>
                                            </span>
                                        )}
                                    </div>
                                    <div className="min-w-0">
                                        <h2 className="truncate text-sm font-bold">
                                            {product.name}
                                        </h2>
                                        <p className="mt-1 truncate text-xs text-muted-foreground">
                                            {product.category?.name ?? "Tanpa kategori"}
                                        </p>
                                        <p className="mt-2 text-sm font-bold sm:hidden">
                                            {formatCurrency(product.base_price)}
                                        </p>
                                    </div>
                                    <p className="hidden text-sm font-bold sm:block">
                                        {formatCurrency(product.base_price)}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            router.patch(
                                                `/products/${product.id}/availability`,
                                                { is_available: !product.is_available },
                                                { preserveScroll: true },
                                            )
                                        }
                                        aria-pressed={product.is_available}
                                        aria-label={`${product.is_available ? "Tandai habis" : "Tandai tersedia"}: ${product.name}`}
                                        className="col-span-full flex min-h-10 items-center gap-2 rounded-full px-3 text-xs font-bold sm:col-auto sm:flex"
                                    >
                                        <span
                                            className={`relative h-6 w-10 rounded-full transition-colors ${product.is_available ? "bg-emerald-600" : "bg-muted-foreground/30"}`}
                                        >
                                            <span
                                                className={`absolute top-1 size-4 rounded-full bg-white transition-transform ${product.is_available ? "left-5" : "left-1"}`}
                                            />
                                        </span>
                                        {product.is_available ? "Tersedia" : "Habis"}
                                    </button>
                                </article>
                            ))
                        )}
                    </div>
                </section>
            </div>

            <Dialog open={isCreateOpen} onOpenChange={setIsCreateOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Tambah produk</DialogTitle>
                        <DialogDescription>
                            Produk dibuat langsung pada outlet yang sedang aktif.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={createProduct} className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="product-name">Nama produk</Label>
                            <Input
                                id="product-name"
                                aria-invalid={Boolean(form.errors.name)}
                                aria-describedby={
                                    form.errors.name ? "product-name-error" : undefined
                                }
                                value={form.data.name}
                                onChange={(event) => form.setData("name", event.target.value)}
                                autoFocus
                                required
                            />
                            <InputError id="product-name-error" message={form.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-category">Kategori</Label>
                            <select
                                id="product-category"
                                aria-invalid={Boolean(form.errors.category_id)}
                                aria-describedby={
                                    form.errors.category_id ? "product-category-error" : undefined
                                }
                                value={form.data.category_id}
                                onChange={(event) =>
                                    form.setData("category_id", event.target.value)
                                }
                                className="border-input h-9 rounded-md border bg-transparent px-3 text-sm"
                            >
                                <option value="">Tanpa kategori</option>
                                {categories
                                    .filter((category) => category.is_active)
                                    .map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.name}
                                        </option>
                                    ))}
                            </select>
                            <InputError
                                id="product-category-error"
                                message={form.errors.category_id}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-price">Harga (Rp)</Label>
                            <Input
                                id="product-price"
                                aria-invalid={Boolean(form.errors.base_price)}
                                aria-describedby={
                                    form.errors.base_price ? "product-price-error" : undefined
                                }
                                type="number"
                                min="0"
                                value={form.data.base_price}
                                onChange={(event) => form.setData("base_price", event.target.value)}
                                required
                            />
                            <InputError id="product-price-error" message={form.errors.base_price} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-description">
                                Deskripsi{" "}
                                <span className="font-normal text-muted-foreground">
                                    (opsional)
                                </span>
                            </Label>
                            <Input
                                id="product-description"
                                aria-invalid={Boolean(form.errors.description)}
                                aria-describedby={
                                    form.errors.description
                                        ? "product-description-error"
                                        : undefined
                                }
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData("description", event.target.value)
                                }
                            />
                            <InputError
                                id="product-description-error"
                                message={form.errors.description}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="product-image">
                                Foto produk{" "}
                                <span className="font-normal text-muted-foreground">
                                    (opsional)
                                </span>
                            </Label>
                            <Input
                                id="product-image"
                                aria-invalid={Boolean(form.errors.image)}
                                aria-describedby={
                                    form.errors.image ? "product-image-error" : undefined
                                }
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                onChange={(event) =>
                                    form.setData("image", event.target.files?.[0] ?? null)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                JPG, PNG, atau WebP, maksimal 5 MB. Gambar akan dioptimalkan
                                otomatis.
                            </p>
                            <InputError id="product-image-error" message={form.errors.image} />
                            {form.progress && (
                                <p className="text-xs font-medium text-primary">
                                    Mengunggah {form.progress.percentage}%
                                </p>
                            )}
                        </div>
                        <label
                            htmlFor="product-featured"
                            className="flex items-center gap-3 text-sm"
                        >
                            <Checkbox
                                id="product-featured"
                                checked={form.data.is_featured}
                                onCheckedChange={(checked) =>
                                    form.setData("is_featured", checked === true)
                                }
                            />{" "}
                            Tandai sebagai produk favorit
                        </label>
                        <DialogFooter>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing && <Spinner />} Tambah produk
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

Products.layout = { breadcrumbs: [{ title: "Produk & menu", href: "/products" }] };
