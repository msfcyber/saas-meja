<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentGatewayCredential;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PaymentGatewayCredentialService
{
    public function __construct(private readonly AuditLogService $audits) {}

    public function rotate(
        Tenant $tenant,
        User $creator,
        string $provider,
        string $secret,
    ): PaymentGatewayCredential {
        $provider = strtolower(trim($provider));

        if ($provider !== 'midtrans') {
            throw new PaymentGatewayException('Provider gateway belum didukung.');
        }

        if ($secret === '') {
            throw new PaymentGatewayException('Credential Midtrans wajib diisi.');
        }

        return DB::transaction(function () use ($tenant, $creator, $provider, $secret): PaymentGatewayCredential {
            $lockedTenant = Tenant::withoutGlobalScopes()
                ->whereKey($tenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $latest = PaymentGatewayCredential::withoutGlobalScopes()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('provider', $provider)
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $current = PaymentGatewayCredential::withoutGlobalScopes()
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('provider', $provider)
                ->whereNull('retired_at')
                ->orderByDesc('version')
                ->lockForUpdate()
                ->first();
            $rotatedAt = now();
            $nextVersion = $latest === null ? 1 : ((int) $latest->version + 1);

            if ($current !== null) {
                $current->update(['retired_at' => $rotatedAt]);
            }

            $credential = PaymentGatewayCredential::withoutGlobalScopes()->create([
                'tenant_id' => $lockedTenant->getKey(),
                'provider' => $provider,
                'version' => $nextVersion,
                'secret' => $secret,
                'fingerprint' => PaymentGatewayCredential::fingerprintFor($secret),
                'created_by' => $creator->getKey(),
                'created_at' => $rotatedAt,
                'updated_at' => $rotatedAt,
            ]);

            $this->audits->record('gateway.credential_rotated', [
                'tenant_id' => (int) $lockedTenant->getKey(),
                'actor_type' => 'user',
                'actor_id' => (int) $creator->getKey(),
                'auditable_type' => PaymentGatewayCredential::class,
                'auditable_id' => (int) $credential->getKey(),
                'old_values' => $current === null ? null : [
                    'provider' => $provider,
                    'version' => (int) $current->version,
                    'credential_id' => (int) $current->getKey(),
                    'retired_at' => $current->retired_at?->toIso8601String(),
                ],
                'new_values' => [
                    'provider' => $provider,
                    'version' => $nextVersion,
                    'credential_id' => (int) $credential->getKey(),
                    'created_at' => $credential->created_at?->toIso8601String(),
                    'previous_credential_id' => $current?->getKey(),
                    'previous_version' => $current === null ? null : (int) $current->version,
                    'previous_retired_at' => $current?->retired_at?->toIso8601String(),
                ],
            ]);

            return $credential;
        }, attempts: 3);
    }

    public function forPayment(Payment $payment, bool $bind = true): PaymentGatewayCredential
    {
        $provider = strtolower(trim((string) $payment->provider));

        if ($provider !== 'midtrans') {
            throw new PaymentGatewayException('Provider payment belum didukung.');
        }

        if ($payment->gateway_credential_id !== null) {
            return $this->boundCredential($payment, $provider);
        }

        if (! $bind) {
            return $this->currentCredential((int) $payment->tenant_id, $provider)
                ?? throw new PaymentGatewayException('Midtrans belum dikonfigurasi.');
        }

        return $this->bindCurrent($payment, $provider);
    }

    public function bind(Payment $payment, PaymentGatewayCredential $credential): PaymentGatewayCredential
    {
        $provider = strtolower(trim((string) $payment->provider));

        if ($provider !== 'midtrans') {
            throw new PaymentGatewayException('Provider payment belum didukung.');
        }

        return DB::transaction(function () use ($payment, $credential, $provider): PaymentGatewayCredential {
            $lockedTenant = Tenant::withoutGlobalScopes()
                ->whereKey($payment->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment = Payment::withoutGlobalScopes()
                ->whereKey($payment->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->gateway_credential_id !== null) {
                $bound = $this->boundCredential($lockedPayment, $provider);
                $this->syncPaymentBinding($payment, $bound);

                return $bound;
            }

            $bound = PaymentGatewayCredential::withoutGlobalScopes()
                ->whereKey($credential->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->where('provider', $provider)
                ->lockForUpdate()
                ->first();

            if ($bound === null) {
                throw new PaymentGatewayException('Credential payment Midtrans tidak tersedia.');
            }

            $lockedPayment->update(['gateway_credential_id' => $bound->getKey()]);
            $this->syncPaymentBinding($payment, $bound);

            return $bound;
        }, attempts: 3);
    }

    private function bindCurrent(Payment $payment, string $provider): PaymentGatewayCredential
    {
        return DB::transaction(function () use ($payment, $provider): PaymentGatewayCredential {
            $lockedTenant = Tenant::withoutGlobalScopes()
                ->whereKey($payment->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment = Payment::withoutGlobalScopes()
                ->whereKey($payment->getKey())
                ->where('tenant_id', $lockedTenant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPayment->gateway_credential_id !== null) {
                $bound = $this->boundCredential($lockedPayment, $provider);
                $this->syncPaymentBinding($payment, $bound);

                return $bound;
            }

            $credential = $this->currentCredential((int) $lockedTenant->getKey(), $provider, true);

            if ($credential === null) {
                throw new PaymentGatewayException('Midtrans belum dikonfigurasi.');
            }

            $lockedPayment->update(['gateway_credential_id' => $credential->getKey()]);
            $this->syncPaymentBinding($payment, $credential);

            return $credential;
        }, attempts: 3);
    }

    private function boundCredential(Payment $payment, string $provider): PaymentGatewayCredential
    {
        return PaymentGatewayCredential::withoutGlobalScopes()
            ->whereKey($payment->gateway_credential_id)
            ->where('tenant_id', $payment->tenant_id)
            ->where('provider', $provider)
            ->first()
            ?? throw new PaymentGatewayException('Credential payment Midtrans tidak tersedia.');
    }

    private function currentCredential(
        int $tenantId,
        string $provider,
        bool $lock = false,
    ): ?PaymentGatewayCredential {
        $query = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('provider', $provider)
            ->whereNull('retired_at')
            ->orderByDesc('version');

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function syncPaymentBinding(Payment $payment, PaymentGatewayCredential $credential): void
    {
        $payment->setAttribute('gateway_credential_id', $credential->getKey());
        $payment->setRelation('gatewayCredential', $credential);
    }
}
