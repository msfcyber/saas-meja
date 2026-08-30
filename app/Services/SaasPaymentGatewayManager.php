<?php

namespace App\Services;

final class SaasPaymentGatewayManager
{
    public function __construct(private readonly MidtransSaasPaymentGateway $midtrans) {}

    public function for(string $provider): SaasPaymentGateway
    {
        return match (strtolower(trim($provider))) {
            'midtrans' => $this->midtrans,
            default => throw new PaymentGatewayException('Provider subscription belum didukung.'),
        };
    }
}
