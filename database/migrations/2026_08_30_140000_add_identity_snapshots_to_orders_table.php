<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('outlet_name_snapshot')->nullable();
            $table->text('outlet_address_snapshot')->nullable();
            $table->string('outlet_phone_snapshot', 50)->nullable();
            $table->string('table_name_snapshot')->nullable();
            $table->string('table_code_snapshot', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'outlet_name_snapshot',
                'outlet_address_snapshot',
                'outlet_phone_snapshot',
                'table_name_snapshot',
                'table_code_snapshot',
            ]);
        });
    }
};
