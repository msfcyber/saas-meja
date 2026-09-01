<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('table_id');
            $table->unsignedInteger('order_sequence');
            $table->string('order_number', 30);
            $table->string('customer_name', 120)->nullable();
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount_amount')->default(0);
            $table->string('tax_name_snapshot', 50)->nullable();
            $table->unsignedSmallInteger('tax_rate_snapshot')->default(0);
            $table->boolean('tax_inclusive_snapshot')->default(false);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('fee_amount')->default(0);
            $table->unsignedBigInteger('grand_total');
            $table->char('currency', 3);
            $table->string('idempotency_key', 100);
            $table->char('idempotency_fingerprint', 64);
            $table->char('access_token_hash', 64)->unique();
            $table->text('access_token_encrypted');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'order_sequence']);
            $table->unique(['tenant_id', 'outlet_id', 'order_number']);
            $table->unique(['tenant_id', 'outlet_id', 'idempotency_key']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->restrictOnDelete();
            $table->foreign(['table_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('tables')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'status']);
            $table->index(['table_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->string('product_name_snapshot', 150);
            $table->text('product_description_snapshot')->nullable();
            $table->string('variant_name_snapshot', 150)->nullable();
            $table->unsignedBigInteger('base_price_snapshot');
            $table->bigInteger('variant_price_delta_snapshot')->default(0);
            $table->bigInteger('modifier_amount_snapshot')->default(0);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['order_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('orders')
                ->cascadeOnDelete();
            $table->foreign(['product_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign('variant_id')
                ->references('id')
                ->on('product_variants')
                ->nullOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'order_id']);
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('modifier_id')->nullable();
            $table->unsignedBigInteger('modifier_option_id')->nullable();
            $table->string('modifier_name_snapshot', 150);
            $table->string('option_name_snapshot', 150);
            $table->bigInteger('price_delta_snapshot')->default(0);
            $table->timestamps();

            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['order_item_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('order_items')
                ->cascadeOnDelete();
            $table->foreign('modifier_id')
                ->references('id')
                ->on('modifiers')
                ->nullOnDelete();
            $table->foreign('modifier_option_id')
                ->references('id')
                ->on('modifier_options')
                ->nullOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'order_item_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('order_id');
            $table->string('method', 30);
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->string('provider', 50)->nullable();
            $table->string('provider_reference', 150)->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['order_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('orders')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'order_id']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('order_id');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('actor_type', 30);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['order_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('orders')
                ->cascadeOnDelete();
            $table->index(
                ['tenant_id', 'outlet_id', 'order_id', 'created_at'],
                'order_histories_scope_created_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
