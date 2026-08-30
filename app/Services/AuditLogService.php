<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class AuditLogService
{
    /**
     * @param array{
     *     tenant_id?: int|null,
     *     outlet_id?: int|null,
     *     actor_type?: string|null,
     *     actor_id?: int|null,
     *     auditable_type?: string|null,
     *     auditable_id?: int|null,
     *     request_id?: string|null,
     *     old_values?: array<string, mixed>|null,
     *     new_values?: array<string, mixed>|null
     * } $attributes
     */
    public function record(string $event, array $attributes = []): AuditLog
    {
        $request = app()->bound(Request::class) ? app(Request::class) : null;
        $actorId = $attributes['actor_id'] ?? Auth::id();
        $actorId = is_numeric($actorId) ? (int) $actorId : null;
        $requestId = $attributes['request_id'] ?? $request?->header('X-Request-ID');

        return AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $attributes['tenant_id'] ?? null,
            'outlet_id' => $attributes['outlet_id'] ?? null,
            'actor_type' => $attributes['actor_type'] ?? ($actorId === null ? 'system' : 'user'),
            'actor_id' => $actorId,
            'event' => $event,
            'auditable_type' => $attributes['auditable_type'] ?? null,
            'auditable_id' => $attributes['auditable_id'] ?? null,
            'request_id' => is_string($requestId) && $requestId !== '' ? substr($requestId, 0, 100) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'old_values' => $attributes['old_values'] ?? null,
            'new_values' => $attributes['new_values'] ?? null,
        ]);
    }
}
