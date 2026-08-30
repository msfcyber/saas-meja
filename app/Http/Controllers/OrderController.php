<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\OrderStatusUpdated;
use App\Http\Requests\Orders\UpdateOrderNotificationPreferencesRequest;
use App\Http\Requests\Orders\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\StaffNotificationPreference;
use App\Services\OrderStatusService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request, TenantContext $context): Response
    {
        $this->authorize('viewAny', Order::class);

        $search = trim((string) $request->query('search', ''));
        $status = $this->normalizeStatus($request->query('status'));
        $boardStatuses = $this->boardStatuses();
        $activeStatuses = $this->activeStatuses();

        $query = Order::query()->with([
            'items' => fn ($query) => $query->orderBy('id'),
            'items.modifiers',
            'payments' => fn ($query) => $query->orderByDesc('id'),
            'statusHistories' => fn ($query) => $query->orderBy('created_at'),
            'table',
            'outlet',
        ]);

        if ($status === 'active') {
            $query->whereIn('status', $activeStatuses);
        } else {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $term = "%{$search}%";

                $query->where('order_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhereHas('table', function ($table) use ($term): void {
                        $table->where('name', 'like', $term)->orWhere('code', 'like', $term);
                    });
            });
        }

        $orders = $query->orderByDesc('created_at')->limit(100)->get();
        $countQuery = Order::query()->whereIn('status', $boardStatuses);
        $counts = [
            'active' => (clone $countQuery)->whereIn('status', $activeStatuses)->count(),
        ];

        foreach ($boardStatuses as $boardStatus) {
            $counts[$boardStatus] = (clone $countQuery)->where('status', $boardStatus)->count();
        }

        $outlet = $context->outletOrFail();
        $preference = StaffNotificationPreference::query()
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->first();
        $notificationOrders = Order::query()
            ->where('status', OrderStatus::Paid)
            ->with('table')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->getKey(),
                'number' => $order->order_number,
                'table_name' => $order->table?->name,
            ])
            ->values();

        return Inertia::render('orders', [
            'outlet' => [
                'name' => $outlet->name,
                'timezone' => $outlet->timezone,
            ],
            'realtime' => [
                'channel' => OrderStatusUpdated::outletChannel((int) $outlet->getKey()),
            ],
            'notifications' => [
                'visual_enabled' => $preference === null ? true : $preference->visual_enabled,
                'sound_enabled' => $preference === null ? true : $preference->sound_enabled,
            ],
            'notification_orders' => $notificationOrders,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'counts' => $counts,
            'orders' => $orders->map(fn (Order $order) => (new OrderResource($order))->resolve($request))->values(),
        ]);
    }

    public function updateStatus(
        UpdateOrderStatusRequest $request,
        Order $order,
        OrderStatusService $statuses,
    ): RedirectResponse {
        $this->authorize('updateStatus', $order);
        $status = OrderStatus::from((string) $request->validated('status'));
        $actorId = $request->user()?->getAuthIdentifier();

        $statuses->transition(
            $order,
            $status,
            'user',
            is_numeric($actorId) ? (int) $actorId : null,
            $request->validated('note'),
        );

        return to_route('orders')->with('success', 'Status order berhasil diperbarui.');
    }

    public function updateNotificationPreferences(
        UpdateOrderNotificationPreferencesRequest $request,
        TenantContext $context,
    ): RedirectResponse {
        $outlet = $context->outletOrFail();
        $userId = $request->user()?->getAuthIdentifier();

        StaffNotificationPreference::query()->updateOrCreate(
            [
                'tenant_id' => $context->tenantId(),
                'outlet_id' => $outlet->getKey(),
                'user_id' => $userId,
            ],
            $request->validated(),
        );

        return to_route('orders');
    }

    /** @return list<string> */
    private function boardStatuses(): array
    {
        return [
            OrderStatus::Paid->value,
            OrderStatus::Accepted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
            OrderStatus::Completed->value,
        ];
    }

    /** @return list<string> */
    private function activeStatuses(): array
    {
        return [
            OrderStatus::Paid->value,
            OrderStatus::Accepted->value,
            OrderStatus::Preparing->value,
            OrderStatus::Ready->value,
            OrderStatus::Served->value,
        ];
    }

    private function normalizeStatus(mixed $value): string
    {
        $status = is_string($value) ? $value : 'active';

        return in_array($status, [...$this->boardStatuses(), 'active'], true) ? $status : 'active';
    }
}
