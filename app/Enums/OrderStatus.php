<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Accepted = 'accepted';
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Served = 'served';
    case Completed = 'completed';
    case PaymentExpired = 'payment_expired';
    case Cancelled = 'cancelled';
    case Rejected = 'rejected';
    case Refunded = 'refunded';

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, match ($this) {
            self::Draft => [self::AwaitingPayment, self::Cancelled],
            self::AwaitingPayment => [self::Paid, self::PaymentExpired, self::Cancelled],
            self::Paid => [self::Accepted, self::Refunded],
            self::Accepted => [self::Preparing, self::Refunded],
            self::Preparing => [self::Ready, self::Refunded],
            self::Ready => [self::Served, self::Refunded],
            self::Served => [self::Completed, self::Refunded],
            self::PaymentExpired => [self::AwaitingPayment],
            self::Completed => [self::Refunded],
            self::Cancelled, self::Rejected, self::Refunded => [],
        }, true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingPayment => 'Menunggu pembayaran',
            self::Paid => 'Order baru',
            self::Accepted => 'Diterima',
            self::Preparing => 'Disiapkan',
            self::Ready => 'Siap disajikan',
            self::Served => 'Disajikan',
            self::Completed => 'Selesai',
            self::PaymentExpired => 'Pembayaran kedaluwarsa',
            self::Cancelled => 'Dibatalkan',
            self::Rejected => 'Ditolak',
            self::Refunded => 'Dikembalikan',
        };
    }
}
