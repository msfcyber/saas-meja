<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->string('name');
            $table->string('code', 30);
            $table->string('zone')->nullable();
            $table->unsignedSmallInteger('capacity')->default(2);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['outlet_id', 'code']);
            $table->unique(['id', 'tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
        });

        Schema::create('table_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->unsignedBigInteger('table_id');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();

            $table->index(['table_id', 'revoked_at']);
            $table->foreign(['table_id', 'tenant_id', 'outlet_id'])
                ->references(['id', 'tenant_id', 'outlet_id'])
                ->on('tables')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_qr_tokens');
        Schema::dropIfExists('tables');
    }
};
