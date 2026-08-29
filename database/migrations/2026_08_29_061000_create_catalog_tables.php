<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['outlet_id', 'slug']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedBigInteger('base_price');
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_available')->default(true)->index();
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['outlet_id', 'slug']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
            $table->foreign(['category_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('categories')
                ->restrictOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('product_id');
            $table->string('name');
            $table->bigInteger('price_delta')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
            $table->foreign(['product_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('products')
                ->cascadeOnDelete();
        });

        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->string('name');
            $table->string('selection_type')->default('single');
            $table->unsignedSmallInteger('minimum_selections')->default(0);
            $table->unsignedSmallInteger('maximum_selections')->default(1);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['outlet_id', 'name']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('modifier_id');
            $table->string('name');
            $table->bigInteger('price_delta')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['modifier_id', 'name']);
            $table->foreign(['modifier_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('modifiers')
                ->cascadeOnDelete();
        });

        Schema::create('product_modifier', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('modifier_id');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->primary(['product_id', 'modifier_id']);
            $table->foreign(['product_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('products')
                ->cascadeOnDelete();
            $table->foreign(['modifier_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('modifiers')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifiers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
