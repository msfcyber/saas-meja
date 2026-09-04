<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class SalesReportService
{
    /** @var list<string> */
    private const SALES_STATUSES = [
        'paid',
        'accepted',
        'preparing',
        'ready',
        'served',
        'completed',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     filters: array{from: string, to: string, outlet: int|null},
     *     summary: array{orders: int, gross_sales: int, average_order: int, refunded_orders: int, refunded_amount: int},
     *     payment_methods: list<array{method: string, orders: int, amount: int}>,
     *     daily_sales: list<array{date: string, orders: int, amount: int}>,
     *     top_products: list<array{name: string, quantity: int, amount: int}>,
     *     transactions: list<array{order_number: string, outlet: string|null, status: string, payment_method: string|null, amount: int, paid_at: string|null}>
     * }
     */
    public function build(Tenant $tenant, array $filters): array
    {
        [$from, $toExclusive, $fromDate, $toDate] = $this->dateRange($tenant, $filters);
        $outletId = $this->outletId($filters);
        $orders = $this->orders($tenant, $outletId, $from, $toExclusive);
        $sales = (clone $orders)->whereIn('status', self::SALES_STATUSES);
        $orderCount = (int) $sales->count();
        $grossSales = (int) $sales->sum('grand_total');
        $refunds = $this->refundSummary($orders);

        return [
            'filters' => [
                'from' => $fromDate,
                'to' => $toDate,
                'outlet' => $outletId,
            ],
            'summary' => [
                'orders' => $orderCount,
                'gross_sales' => $grossSales,
                'average_order' => $orderCount === 0 ? 0 : intdiv($grossSales, $orderCount),
                'refunded_orders' => $refunds['orders'],
                'refunded_amount' => $refunds['amount'],
            ],
            'payment_methods' => $this->paymentMethods($sales),
            'daily_sales' => $this->dailySales($sales, $tenant->timezone ?: 'UTC'),
            'top_products' => $this->topProducts($sales),
            'transactions' => $this->transactions($sales),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string, 3: string}
     */
    private function dateRange(Tenant $tenant, array $filters): array
    {
        $timezone = $tenant->timezone ?: 'UTC';
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $fromDate = is_string($filters['from'] ?? null) && $filters['from'] !== ''
            ? CarbonImmutable::parse($filters['from'], $timezone)->startOfDay()
            : $today->subDays(29);
        $toDate = is_string($filters['to'] ?? null) && $filters['to'] !== ''
            ? CarbonImmutable::parse($filters['to'], $timezone)->startOfDay()
            : $today;

        return [
            $fromDate->setTimezone('UTC'),
            $toDate->addDay()->setTimezone('UTC'),
            $fromDate->format('Y-m-d'),
            $toDate->format('Y-m-d'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function outletId(array $filters): ?int
    {
        $outlet = $filters['outlet'] ?? null;

        return is_numeric($outlet) && (int) $outlet > 0 ? (int) $outlet : null;
    }

    /** @return Builder<Order> */
    private function orders(Tenant $tenant, ?int $outletId, CarbonImmutable $from, CarbonImmutable $toExclusive): Builder
    {
        return Order::withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->when($outletId !== null, fn (Builder $query) => $query->where('outlet_id', $outletId))
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $from)
            ->where('paid_at', '<', $toExclusive);
    }

    /**
     * @param  Builder<Order>  $sales
     * @return list<array{method: string, orders: int, amount: int}>
     */
    private function paymentMethods(Builder $sales): array
    {
        $payments = Payment::withoutGlobalScopes()
            ->whereIn('order_id', (clone $sales)->select('id'))
            ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
            ->select('method')
            ->selectRaw('COUNT(DISTINCT order_id) as orders')
            ->selectRaw('SUM(amount) as amount')
            ->groupBy('method')
            ->orderByDesc('amount')
            ->get();

        $result = [];

        foreach ($payments as $payment) {
            $result[] = [
                'method' => (string) $payment->method,
                'orders' => (int) $payment->getAttribute('orders'),
                'amount' => (int) $payment->getAttribute('amount'),
            ];
        }

        return $result;
    }

    /**
     * @param  Builder<Order>  $orders
     * @return array{orders: int, amount: int}
     */
    private function refundSummary(Builder $orders): array
    {
        $payments = Payment::withoutGlobalScopes()
            ->whereIn('order_id', (clone $orders)->select('id'))
            ->whereIn('status', [PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded])
            ->get(['order_id', 'amount', 'status', 'metadata']);
        $orderIds = [];
        $amount = 0;

        foreach ($payments as $payment) {
            $orderIds[(int) $payment->order_id] = true;
            $metadata = is_array($payment->metadata) ? $payment->metadata : [];
            $partialAmount = $metadata['refund_amount'] ?? null;
            $amount += $payment->status === PaymentStatus::PartiallyRefunded && is_numeric($partialAmount)
                ? min((int) $payment->amount, max(0, (int) $partialAmount))
                : (int) $payment->amount;
        }

        return ['orders' => count($orderIds), 'amount' => $amount];
    }

    /**
     * @param  Builder<Order>  $sales
     * @return list<array{date: string, orders: int, amount: int}>
     */
    private function dailySales(Builder $sales, string $timezone): array
    {
        /** @var array<string, array{date: string, orders: int, amount: int}> $daily */
        $daily = [];

        $orders = (clone $sales)->orderBy('paid_at')->get(['paid_at', 'grand_total']);

        foreach ($orders as $order) {
            $date = $order->paid_at?->setTimezone($timezone)->format('Y-m-d');

            if ($date === null) {
                continue;
            }

            $daily[$date] ??= ['date' => $date, 'orders' => 0, 'amount' => 0];
            $daily[$date]['orders']++;
            $daily[$date]['amount'] += $order->grand_total;
        }

        $result = [];

        foreach ($daily as $entry) {
            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @param  Builder<Order>  $sales
     * @return list<array{name: string, quantity: int, amount: int}>
     */
    private function topProducts(Builder $sales): array
    {
        $items = OrderItem::withoutGlobalScopes()
            ->whereIn('order_id', (clone $sales)->select('id'))
            ->select('product_name_snapshot as name')
            ->selectRaw('SUM(quantity) as quantity')
            ->selectRaw('SUM(line_total) as amount')
            ->groupBy('product_name_snapshot')
            ->orderByDesc('amount')
            ->limit(10)
            ->get();

        $result = [];

        foreach ($items as $item) {
            $result[] = [
                'name' => (string) $item->getAttribute('name'),
                'quantity' => (int) $item->getAttribute('quantity'),
                'amount' => (int) $item->getAttribute('amount'),
            ];
        }

        return $result;
    }

    /**
     * @param  Builder<Order>  $sales
     * @return list<array{order_number: string, outlet: string|null, status: string, payment_method: string|null, amount: int, paid_at: string|null}>
     */
    private function transactions(Builder $sales): array
    {
        $orders = (clone $sales)
            ->with([
                'outlet' => fn ($query) => $query->withoutGlobalScopes(),
                'payments' => fn ($query) => $query
                    ->withoutGlobalScopes()
                    ->whereIn('status', [PaymentStatus::Paid, PaymentStatus::PartiallyRefunded])
                    ->orderByDesc('id'),
            ])
            ->orderByDesc('paid_at')
            ->limit(100)
            ->get();

        $result = [];

        foreach ($orders as $order) {
            $payment = $order->payments->first();

            $result[] = [
                'order_number' => $order->order_number,
                'outlet' => $order->outlet?->name,
                'status' => $order->status->label(),
                'payment_method' => $payment === null ? null : (string) $payment->method,
                'amount' => $order->grand_total,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ];
        }

        return $result;
    }
}
