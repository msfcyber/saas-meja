<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\StoreProductRequest;
use App\Models\Category;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);

        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category');
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position')
            ->orderBy('name')
            ->get();
        $products = Product::query()
            ->with([
                'category:id,name',
                'variants:id,product_id,name,price_delta,is_default,is_active,position',
                'modifiers:id,name',
            ])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', "%{$search}%"))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        return Inertia::render('products', [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'is_active' => $category->is_active,
                'products_count' => $category->products_count,
            ])->values(),
            'filters' => ['search' => $search, 'category' => $categoryId ?: null],
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'category' => $product->category?->only(['id', 'name']),
                'description' => $product->description,
                'image_url' => $product->image_path === null ? null : Storage::disk('public')->url($product->image_path),
                'base_price' => $product->base_price,
                'is_active' => $product->is_active,
                'is_available' => $product->is_available,
                'is_featured' => $product->is_featured,
                'variants' => $product->variants->map(fn (ProductVariant $variant): array => [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'price_delta' => $variant->price_delta,
                    'is_default' => $variant->is_default,
                    'is_active' => $variant->is_active,
                ])->values(),
                'modifier_ids' => $product->modifiers->pluck('id')->values(),
            ])->values(),
            'modifiers' => Modifier::query()
                ->with('options')
                ->orderBy('name')
                ->get()
                ->map(fn (Modifier $modifier): array => [
                    'id' => $modifier->id,
                    'name' => $modifier->name,
                    'selection_type' => $modifier->selection_type->value,
                    'minimum_selections' => $modifier->minimum_selections,
                    'maximum_selections' => $modifier->maximum_selections,
                    'is_required' => $modifier->is_required,
                    'is_active' => $modifier->is_active,
                    'options' => $modifier->options->map(fn (ModifierOption $option): array => [
                        'id' => $option->id,
                        'name' => $option->name,
                        'price_delta' => $option->price_delta,
                        'is_active' => $option->is_active,
                    ])->values(),
                ])->values(),
            'summary' => [
                'products' => Product::query()->count(),
                'available_products' => Product::query()->where('is_active', true)->where('is_available', true)->count(),
                'categories' => $categories->count(),
                'featured_products' => Product::query()->where('is_featured', true)->count(),
            ],
        ]);
    }

    public function store(StoreProductRequest $request, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Product::class);
        $outlet = $context->outletOrFail();
        $attributes = Arr::except($request->validated(), 'image');
        $baseSlug = Str::slug($attributes['name']) ?: 'produk';
        $imagePath = $this->storeImage($request, $context);

        try {
            DB::transaction(function () use ($attributes, $context, $outlet, $baseSlug, $imagePath) {
                Product::query()->create([
                    ...$attributes,
                    'tenant_id' => $context->tenantId(),
                    'outlet_id' => $outlet->id,
                    'image_path' => $imagePath,
                    'slug' => $this->uniqueSlug($baseSlug),
                    'position' => (Product::query()->max('position') ?? -1) + 1,
                ]);
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($imagePath !== null) {
                Storage::disk('public')->delete($imagePath);
            }

            throw $exception;
        }

        return to_route('products')->with('success', 'Produk berhasil ditambahkan.');
    }

    private function uniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $suffix = 2;

        while (Product::query()->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(StoreProductRequest $request, TenantContext $context): ?string
    {
        $image = $request->image('image');

        if ($image === null) {
            return null;
        }

        $path = $image
            ->orient()
            ->scale(1600, 1600)
            ->optimize('webp', 80)
            ->storePubliclyAs(
                path: "tenants/{$context->tenantId()}/outlets/{$context->outletId()}/products",
                name: Str::uuid().'.webp',
                disk: 'public',
            );

        if ($path === false) {
            throw new RuntimeException('Product image could not be stored.');
        }

        return $path;
    }
}
