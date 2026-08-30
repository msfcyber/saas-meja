<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Suspended = 'suspended';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function allowsOrders(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::PastDue => true,
            default => false,
        };
    }
}
