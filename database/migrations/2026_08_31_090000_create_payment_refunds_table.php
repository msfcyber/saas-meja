<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('idempotency_key', 100);
            $table->string('provider', 50);
            $table->string('provider_refund_key', 150);
            $table->string('provider_reference', 150)->nullable();
            $table->string('status', 30)->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->text('reason');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'outlet_id', 'idempotency_key'], 'payment_refunds_idempotency_unique');
            $table->unique(['provider', 'provider_refund_key'], 'payment_refunds_provider_key_unique');
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->restrictOnDelete();
            $table->foreign(['payment_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('payments')
                ->cascadeOnDelete();
            $table->foreign('requested_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
    }
};
