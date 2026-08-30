<?php

namespace App\Http\Middleware;

use App\Services\TelemetryService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function __construct(private readonly TelemetryService $telemetry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $secret = config('payments.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            return $this->reject($request, 'not_configured', 503, $startedAt);
        }

        $timestampHeader = $request->header('X-Payment-Timestamp');
        $signatureHeader = $request->header('X-Payment-Signature');
        $provider = strtolower((string) $request->route('provider'));

        if (! is_string($timestampHeader)
            || ! ctype_digit($timestampHeader)
            || ! is_string($signatureHeader)
            || $provider === '') {
            return $this->reject($request, 'missing_signature', 401, $startedAt);
        }

        $timestamp = (int) $timestampHeader;
        $tolerance = max(0, (int) config('payments.webhook_tolerance', 300));

        if (abs(now()->getTimestamp() - $timestamp) > $tolerance) {
            return $this->reject($request, 'expired_timestamp', 401, $startedAt);
        }

        $signature = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        if (! preg_match('/\A[a-f0-9]{64}\z/i', $signature)) {
            return $this->reject($request, 'malformed_signature', 401, $startedAt);
        }

        $expected = hash_hmac('sha256', $provider.'.'.$timestampHeader.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject($request, 'signature_mismatch', 401, $startedAt);
        }

        return $next($request);
    }

    private function reject(Request $request, string $reason, int $status, int $startedAt): JsonResponse
    {
        $routeProvider = $request->route('provider');

        $this->telemetry->recordDuration('payment.webhook.rejected', $startedAt, [
            'provider' => is_string($routeProvider) ? strtolower($routeProvider) : 'unknown',
            'reason' => $reason,
            'status' => $status,
        ], 'warning');

        $message = $reason === 'not_configured'
            ? 'Payment webhook belum dikonfigurasi.'
            : ($reason === 'expired_timestamp'
                ? 'Timestamp payment webhook sudah kedaluwarsa.'
                : 'Signature payment webhook tidak valid.');

        return response()->json(['message' => $message], $status);
    }
}
