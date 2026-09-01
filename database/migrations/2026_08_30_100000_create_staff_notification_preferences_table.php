<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('outlet_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('visual_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'outlet_id', 'user_id'],
                'staff_notification_scope_user_unique',
            );
            $table->foreign(['outlet_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('outlets')
                ->cascadeOnDelete();
            $table->index(['user_id', 'tenant_id', 'outlet_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notification_preferences');
    }
};
