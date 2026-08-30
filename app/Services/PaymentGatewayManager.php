<?php

namespace App\Services;

final class PaymentGatewayManager
{
    public function __construct(private readonly MidtransPaymentGateway $midtrans) {}

    public function for(string $provider): PaymentGateway
    {
        return match (strtolower(trim($provider))) {
            'midtrans' => $this->midtrans,
            default => throw new PaymentGatewayException('Provider payment belum didukung.'),
        };
    }
}
