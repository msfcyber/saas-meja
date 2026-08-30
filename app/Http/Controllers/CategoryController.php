<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\SaveCategoryRequest;
use App\Models\Category;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function store(SaveCategoryRequest $request, TenantContext $context): RedirectResponse
    {
        $this->authorize('create', Category::class);

        Category::query()->create([
            ...$request->validated(),
            'tenant_id' => $context->tenantId(),
            'outlet_id' => $context->outletOrFail()->id,
            'slug' => $this->uniqueSlug($request->string('name')->toString()),
            'position' => (Category::query()->max('position') ?? -1) + 1,
        ]);

        return to_route('products')->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(SaveCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($request->string('name')->toString(), $category),
        ]);

        return to_route('products')->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Kategori masih digunakan oleh produk. Pindahkan produk terlebih dahulu.',
            ]);
        }

        $category->delete();

        return to_route('products')->with('success', 'Kategori berhasil dihapus.');
    }

    private function uniqueSlug(string $name, ?Category $ignore = null): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
