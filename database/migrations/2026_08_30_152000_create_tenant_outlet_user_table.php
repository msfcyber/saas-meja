<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_outlet_user', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['tenant_id', 'outlet_id', 'user_id']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'outlet_id']);
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
        });

        // Preserve every existing membership's outlet access before assignment enforcement begins.
        DB::table('tenant_outlet_user')->insertUsing(
            ['tenant_id', 'outlet_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('tenant_user')
                ->join('outlets', 'outlets.tenant_id', '=', 'tenant_user.tenant_id')
                ->select([
                    'tenant_user.tenant_id',
                    'outlets.id as outlet_id',
                    'tenant_user.user_id',
                    DB::raw('CURRENT_TIMESTAMP as created_at'),
                    DB::raw('CURRENT_TIMESTAMP as updated_at'),
                ]),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_outlet_user');
    }
};
