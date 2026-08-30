<?php

namespace Database\Factories;

use App\Models\PaymentGatewayCredential;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentGatewayCredential> */
class PaymentGatewayCredentialFactory extends Factory
{
    public function definition(): array
    {
        $secret = 'SB-Mid-server-'.Str::random(32);

        return [
            'tenant_id' => Tenant::factory(),
            'provider' => 'midtrans',
            'version' => 1,
            'secret' => $secret,
            'fingerprint' => PaymentGatewayCredential::fingerprintFor($secret),
            'metadata' => null,
            'created_by' => null,
            'retired_at' => null,
        ];
    }
}
