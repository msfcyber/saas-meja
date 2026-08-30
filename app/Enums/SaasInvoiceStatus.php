<?php

namespace App\Enums;

enum SaasInvoiceStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Void = 'void';
    case Refunded = 'refunded';
}
