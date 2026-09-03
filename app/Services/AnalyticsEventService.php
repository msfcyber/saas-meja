<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Support\PublicTableAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class AnalyticsEventService
{
    /** @var list<string> */
    public const EVENTS = [
        'qr_opened',
        'menu_viewed',
        'product_viewed',
        'add_to_cart',
        'checkout_started',
        'order_created',
        'payment_started',
        'payment_paid',
        'payment_failed',
        'order_accepted',
        'order_preparing',
        'order_ready',
        'order_served',
        'order_completed',
    ];

    /** @var list<string> */
    public const PUBLIC_EVENTS = [
        'qr_opened',
        'menu_viewed',
        'product_viewed',
        'add_to_cart',
        'checkout_started',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $event,
        int $tenantId,
        int $outletId,
        array $context = [],
    ): AnalyticsEvent {
        if (! in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException("Analytics event [{$event}] tidak didukung.");
        }

        $sessionId = $context['session_id'] ?? null;
        $qrToken = $context['qr_token'] ?? null;

        return AnalyticsEvent::query()->create([
            'tenant_id' => $tenantId,
            'outlet_id' => $outletId,
            'order_id' => $this->nullableInt($context['order_id'] ?? null),
            'product_id' => $this->nullableInt($context['product_id'] ?? null),
            'event' => $event,
            'session_hash' => $this->hashValue($sessionId),
            'qr_token_hash' => $this->hashValue($qrToken),
            'properties' => $this->safeProperties($context['properties'] ?? []),
            'occurred_at' => $context['occurred_at'] ?? CarbonImmutable::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public function recordPublic(
        string $event,
        PublicTableAccess $access,
        ?string $sessionId = null,
        ?int $productId = null,
        ?int $orderId = null,
        array $properties = [],
    ): AnalyticsEvent {
        if (! in_array($event, self::PUBLIC_EVENTS, true)) {
            throw new InvalidArgumentException("Public analytics event [{$event}] tidak didukung.");
        }

        return $this->record($event, (int) $access->tenant->getKey(), (int) $access->outlet->getKey(), [
            'qr_token' => $access->plainToken,
            'session_id' => $sessionId,
            'product_id' => $productId,
            'order_id' => $orderId,
            'properties' => $properties,
        ]);
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function hashValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return hash('sha256', $value);
    }

    /** @param array<string, mixed> $properties
     * @return array<string, scalar|null>|null
     */
    private function safeProperties(array $properties): ?array
    {
        $allowed = Arr::only($properties, [
            'source',
            'status',
            'from_status',
            'to_status',
        ]);

        $safe = [];

        foreach ($allowed as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $safe[$key] = is_string($value) ? mb_substr($value, 0, 80) : $value;
            }
        }

        return $safe === [] ? null : $safe;
    }
}
