<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->string('name', 50)->nullable();
            $table->unsignedSmallInteger('rate_basis_points')->default(0);
            $table->boolean('is_inclusive')->default(false);
            $table->timestamps();

            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_settings');
    }
};
