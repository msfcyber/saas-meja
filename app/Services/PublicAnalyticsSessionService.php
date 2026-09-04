<?php

namespace App\Services;

use App\Support\PublicTableAccess;

final class PublicAnalyticsSessionService
{
    private const TTL_SECONDS = 3600;

    /** @return array{token: string, session_id: string} */
    public function issue(PublicTableAccess $access): array
    {
        $sessionId = bin2hex(random_bytes(16));
        $payload = $this->encode([
            'v' => 1,
            'q' => hash('sha256', $access->plainToken),
            's' => $sessionId,
            'e' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]);
        $token = $payload.'.'.hash_hmac('sha256', $payload, (string) config('app.key'));

        return ['token' => $token, 'session_id' => $sessionId];
    }

    public function sessionId(string $token, string $qrToken): ?string
    {
        [$payload, $signature] = array_pad(explode('.', $token, 2), 2, null);

        if (! is_string($payload)
            || ! is_string($signature)
            || ! hash_equals(hash_hmac('sha256', $payload, (string) config('app.key')), $signature)) {
            return null;
        }

        $session = json_decode($this->decode($payload), true);

        if (! is_array($session)
            || ($session['v'] ?? null) !== 1
            || ! is_string($session['q'] ?? null)
            || ! is_string($session['s'] ?? null)
            || ! is_int($session['e'] ?? null)
            || $session['e'] < now()->getTimestamp()
            || ! hash_equals($session['q'], hash('sha256', $qrToken))) {
            return null;
        }

        return $session['s'];
    }

    /** @param array<string, int|string> $payload */
    private function encode(array $payload): string
    {
        return rtrim(strtr(base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decode(string $payload): string
    {
        return base64_decode(strtr($payload, '-_', '+/'), true) ?: '';
    }
}
