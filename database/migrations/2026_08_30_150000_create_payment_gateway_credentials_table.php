<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->unsignedInteger('version');
            $table->text('secret');
            $table->char('fingerprint', 64);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider', 'version']);
            $table->unique(['id', 'tenant_id']);
            $table->index(['tenant_id', 'provider', 'retired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_credentials');
    }
};
