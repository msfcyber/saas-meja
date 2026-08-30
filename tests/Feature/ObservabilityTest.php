<?php

use App\Models\AuditLog;
use App\Services\AuditLogService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

test('web request IDs are propagated to the response, context, and audit log', function () {
    Route::middleware('web')->get('/_test/request-correlation', function (Request $request): JsonResponse {
        app(AuditLogService::class)->record('test.request');

        return response()->json([
            'request_id' => $request->attributes->get('request_id'),
            'context_id' => Context::get('request_id'),
        ]);
    });

    $response = $this->withHeader('X-Request-ID', 'checkout-trace-42')
        ->getJson('/_test/request-correlation');

    $response->assertOk()
        ->assertHeader('X-Request-ID', 'checkout-trace-42')
        ->assertJson([
            'request_id' => 'checkout-trace-42',
            'context_id' => 'checkout-trace-42',
        ]);

    expect(AuditLog::withoutGlobalScopes()->latest('id')->value('request_id'))
        ->toBe('checkout-trace-42');
});

test('invalid request IDs are replaced with a safe UUID on API responses', function () {
    Route::middleware('api')->get('/_test/api-request-correlation', function (Request $request): JsonResponse {
        return response()->json(['request_id' => $request->attributes->get('request_id')]);
    });

    $requestId = $this->withHeader('X-Request-ID', 'not a safe request id')
        ->getJson('/_test/api-request-correlation')
        ->assertOk()
        ->json('request_id');

    expect($requestId)
        ->toBeString()
        ->not->toBe('not a safe request id')
        ->and(Str::isUuid($requestId))->toBeTrue();

    $this->withHeader('X-Request-ID', $requestId)
        ->getJson('/_test/api-request-correlation')
        ->assertHeader('X-Request-ID', $requestId);
});

test('request telemetry records structured route timing without request payloads', function () {
    Log::spy();

    Route::middleware('web')
        ->get('/_test/request-telemetry', fn (): JsonResponse => response()->json(['ok' => true]))
        ->name('test.request.telemetry');

    $this->withHeader('X-Request-ID', 'telemetry-trace-7')
        ->getJson('/_test/request-telemetry')
        ->assertOk();

    Log::shouldHaveReceived('info')
        ->once()
        ->with('application.telemetry', Mockery::on(static function (array $context): bool {
            return $context['event'] === 'request.completed'
                && $context['request_id'] === 'telemetry-trace-7'
                && $context['route'] === 'test.request.telemetry'
                && $context['status'] === 200
                && is_int($context['duration_ms'])
                && $context['duration_ms'] >= 0;
        }));
});

test('telemetry drops non-scalar attributes before writing logs', function () {
    Log::spy();

    app(TelemetryService::class)->record('test.metric', [
        'duration_ms' => 12,
        'payload' => ['secret' => 'must-not-be-logged'],
    ]);

    Log::shouldHaveReceived('info')
        ->once()
        ->with('application.telemetry', Mockery::on(static function (array $context): bool {
            return $context['event'] === 'test.metric'
                && $context['duration_ms'] === 12
                && ! array_key_exists('payload', $context);
        }));
});

test('ops health returns a healthy machine-readable summary', function () {
    $this->artisan('ops:health', ['--json' => true])
        ->expectsOutputToContain('"status":"ok"')
        ->assertExitCode(0);
});

test('ops health detects failed jobs above the configured threshold', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'sync',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'test failure',
        'failed_at' => now(),
    ]);

    config(['observability.failed_jobs_threshold' => 0]);

    $this->artisan('ops:health', ['--json' => true])
        ->expectsOutputToContain('"status":"degraded"')
        ->assertExitCode(1);
});
