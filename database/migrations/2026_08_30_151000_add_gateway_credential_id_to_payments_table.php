<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('gateway_credential_id')->nullable()->after('provider');
            $table->foreign(
                ['gateway_credential_id', 'tenant_id'],
                'payments_gateway_credential_tenant_foreign',
            )
                ->references(['id', 'tenant_id'])
                ->on('payment_gateway_credentials')
                ->restrictOnDelete();
            $table->index(['tenant_id', 'gateway_credential_id'], 'payments_gateway_credential_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign('payments_gateway_credential_tenant_foreign');
            $table->dropIndex('payments_gateway_credential_index');
            $table->dropColumn('gateway_credential_id');
        });
    }
};
