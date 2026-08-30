<?php

namespace App\Services;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TelemetryService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function record(string $event, array $attributes = [], string $level = 'info'): void
    {
        $context = [
            'event' => $event,
            ...$this->scalarAttributes($attributes),
        ];
        $requestId = Context::get('request_id');

        if (is_string($requestId)) {
            $context['request_id'] = $requestId;
        }

        try {
            match ($level) {
                'error' => Log::error('application.telemetry', $context),
                'warning' => Log::warning('application.telemetry', $context),
                default => Log::info('application.telemetry', $context),
            };
        } catch (Throwable $exception) {
            error_log('Application telemetry unavailable: '.$exception::class);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function recordDuration(
        string $event,
        int $startedAt,
        array $attributes = [],
        string $level = 'info',
    ): void {
        $this->record($event, [
            ...$attributes,
            'duration_ms' => $this->durationSince($startedAt),
        ], $level);
    }

    public function durationSince(int $startedAt): int
    {
        return max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, int|float|string|bool|null>
     */
    private function scalarAttributes(array $attributes): array
    {
        $scalar = [];

        foreach ($attributes as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $scalar[$key] = $value;
            }
        }

        return $scalar;
    }
}
