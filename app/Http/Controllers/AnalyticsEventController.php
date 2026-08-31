<?php

namespace App\Http\Controllers;

use App\Http\Requests\Analytics\StoreAnalyticsEventRequest;
use App\Models\Product;
use App\Services\AnalyticsEventService;
use App\Services\PublicTableAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class AnalyticsEventController extends Controller
{
    public function __invoke(
        StoreAnalyticsEventRequest $request,
        PublicTableAccessService $access,
        AnalyticsEventService $events,
    ): JsonResponse {
        $data = $request->validated();
        $tableAccess = $access->resolve((string) $data['qr_token']);

        if ($tableAccess === null) {
            throw ValidationException::withMessages([
                'qr_token' => 'QR meja tidak valid atau tidak lagi menerima pesanan.',
            ]);
        }

        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;

        if ($productId !== null && ! Product::withoutGlobalScopes()
            ->whereKey($productId)
            ->where('tenant_id', $tableAccess->tenant->getKey())
            ->where('outlet_id', $tableAccess->outlet->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk tidak tersedia pada outlet ini.',
            ]);
        }

        $events->recordPublic(
            (string) $data['event'],
            $tableAccess,
            (string) $data['session_id'],
            $productId,
            null,
            ['source' => 'customer_web'],
        );

        return response()->json(['accepted' => true], 202);
    }
}
