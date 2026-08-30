<?php

namespace Database\Factories;

use App\Enums\SaasInvoiceStatus;
use App\Models\SaasInvoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SaasInvoice> */
class SaasInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => null,
            'subscription_id' => Subscription::factory(),
            'invoice_number' => 'INV-'.strtoupper(fake()->unique()->bothify('########')),
            'status' => SaasInvoiceStatus::Pending,
            'amount' => 299000,
            'currency' => 'IDR',
            'provider' => null,
            'provider_reference' => null,
            'period_starts_at' => now(),
            'period_ends_at' => now()->addMonth(),
            'due_at' => now()->addDays(7),
            'paid_at' => null,
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SaasInvoice $invoice): void {
            $subscription = Subscription::withoutGlobalScopes()->findOrFail($invoice->subscription_id);
            $invoice->tenant_id = $subscription->tenant_id;
        });
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => SaasInvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
