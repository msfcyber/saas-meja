<?php

namespace Database\Factories;

use App\Models\DiningTable;
use App\Models\TableQrToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TableQrToken> */
class TableQrTokenFactory extends Factory
{
    public function definition(): array
    {
        return [
            'table_id' => DiningTable::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'last_used_at' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (TableQrToken $token) {
            $table = DiningTable::query()->findOrFail($token->table_id);
            $token->tenant_id = $table->tenant_id;
            $token->outlet_id = $table->outlet_id;
        });
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }
}
