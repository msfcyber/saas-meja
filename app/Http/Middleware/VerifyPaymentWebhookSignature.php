<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyPaymentWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('payments.webhook_secret');

        if (! is_string($secret) || trim($secret) === '') {
            return response()->json(['message' => 'Payment webhook belum dikonfigurasi.'], 503);
        }

        $timestampHeader = $request->header('X-Payment-Timestamp');
        $signatureHeader = $request->header('X-Payment-Signature');
        $provider = strtolower((string) $request->route('provider'));

        if (! is_string($timestampHeader)
            || ! ctype_digit($timestampHeader)
            || ! is_string($signatureHeader)
            || $provider === '') {
            return response()->json(['message' => 'Signature payment webhook tidak valid.'], 401);
        }

        $timestamp = (int) $timestampHeader;
        $tolerance = max(0, (int) config('payments.webhook_tolerance', 300));

        if (abs(now()->getTimestamp() - $timestamp) > $tolerance) {
            return response()->json(['message' => 'Timestamp payment webhook sudah kedaluwarsa.'], 401);
        }

        $signature = str_starts_with($signatureHeader, 'sha256=')
            ? substr($signatureHeader, 7)
            : $signatureHeader;

        if (! preg_match('/\A[a-f0-9]{64}\z/i', $signature)) {
            return response()->json(['message' => 'Signature payment webhook tidak valid.'], 401);
        }

        $expected = hash_hmac('sha256', $provider.'.'.$timestampHeader.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Signature payment webhook tidak valid.'], 401);
        }

        return $next($request);
    }
}
