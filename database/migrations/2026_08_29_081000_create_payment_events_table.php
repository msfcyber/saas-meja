<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('last_webhook_at')->nullable()->after('paid_at');
            $table->unique(
                ['tenant_id', 'outlet_id', 'provider', 'provider_reference'],
                'payments_provider_reference_unique',
            );
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('payment_id');
            $table->string('provider', 50);
            $table->string('event_id', 150);
            $table->string('event_type', 50);
            $table->unsignedBigInteger('amount');
            $table->char('currency', 3);
            $table->timestamp('occurred_at');
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['payment_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('payments')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'outlet_id', 'payment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_provider_reference_unique');
            $table->dropColumn('last_webhook_at');
        });
    }
};
