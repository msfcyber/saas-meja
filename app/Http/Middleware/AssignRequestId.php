<?php

namespace App\Http\Middleware;

use App\Services\TelemetryService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AssignRequestId
{
    public function __construct(private readonly TelemetryService $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->normalize($request->header('X-Request-ID'));
        $startedAt = hrtime(true);

        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-ID', $requestId);
        Context::add('request_id', $requestId);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->telemetry->recordDuration('request.failed', $startedAt, [
                'method' => $request->method(),
                'route' => $this->routeName($request),
                'outcome' => 'exception',
                'exception' => $exception::class,
            ], 'error');

            throw $exception;
        }

        $response->headers->set('X-Request-ID', $requestId);
        $status = $response->getStatusCode();
        $duration = $this->telemetry->durationSince($startedAt);
        $slow = $duration >= (int) config('observability.slow_request_ms', 1000);
        $event = $status >= 500
            ? 'request.failed'
            : ($slow ? 'request.slow' : 'request.completed');
        $level = $status >= 500 || $status >= 400 || $slow ? 'warning' : 'info';

        $this->telemetry->record($event, [
            'method' => $request->method(),
            'route' => $this->routeName($request),
            'status' => $status,
            'outcome' => $status >= 500 ? 'error' : ($status >= 400 ? 'client_error' : 'success'),
            'duration_ms' => $duration,
        ], $level);

        return $response;
    }

    private function routeName(Request $request): ?string
    {
        $route = $request->route();

        return $route instanceof Route ? $route->getName() : null;
    }

    private function normalize(?string $requestId): string
    {
        $requestId = trim((string) $requestId);

        if ($requestId !== '' && preg_match('/\A[A-Za-z0-9._:-]{1,100}\z/D', $requestId) === 1) {
            return $requestId;
        }

        return (string) Str::uuid();
    }
}
