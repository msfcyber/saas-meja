<?php

namespace App\Services;

use App\Models\DiningTable;
use App\Models\TableQrToken;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class TableQrCodeService
{
    private const TOKEN_BYTES = 32;

    public function ensure(DiningTable $table): TableQrToken
    {
        $token = $table->qrTokens()
            ->whereNull('revoked_at')
            ->latest('id')
            ->first();

        if ($token !== null && $token->qr_path !== null && Storage::disk('public')->exists($token->qr_path)) {
            return $token;
        }

        return $this->issue($table);
    }

    public function issue(DiningTable $table): TableQrToken
    {
        $newPath = null;
        $revokedPaths = [];

        try {
            $token = DB::transaction(function () use ($table, &$newPath, &$revokedPaths): TableQrToken {
                $activeTokens = $table->qrTokens()
                    ->whereNull('revoked_at')
                    ->lockForUpdate()
                    ->get();
                $revokedPaths = $activeTokens
                    ->pluck('qr_path')
                    ->filter(fn ($path): bool => is_string($path) && $path !== '')
                    ->values()
                    ->all();

                foreach ($activeTokens as $activeToken) {
                    $activeToken->update(['revoked_at' => now()]);
                }

                $plainToken = bin2hex(random_bytes(self::TOKEN_BYTES));
                $token = TableQrToken::query()->create([
                    'tenant_id' => $table->tenant_id,
                    'outlet_id' => $table->outlet_id,
                    'table_id' => $table->getKey(),
                    'token_hash' => hash('sha256', $plainToken),
                    'expires_at' => null,
                    'revoked_at' => null,
                ]);

                $newPath = $this->qrPath($table);
                $svg = $this->render($plainToken);

                if (! Storage::disk('public')->put($newPath, $svg)) {
                    throw new RuntimeException('QR code could not be stored.');
                }

                $token->update(['qr_path' => $newPath]);

                return $token->fresh();
            }, attempts: 3);
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }

            throw $exception;
        }

        if ($revokedPaths !== []) {
            Storage::disk('public')->delete($revokedPaths);
        }

        return $token;
    }

    public function revoke(DiningTable $table): void
    {
        $revokedPaths = DB::transaction(function () use ($table): array {
            $activeTokens = $table->qrTokens()
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();

            foreach ($activeTokens as $activeToken) {
                $activeToken->update(['revoked_at' => now()]);
            }

            return $activeTokens
                ->pluck('qr_path')
                ->filter(fn ($path): bool => is_string($path) && $path !== '')
                ->values()
                ->all();
        }, attempts: 3);

        if ($revokedPaths !== []) {
            Storage::disk('public')->delete($revokedPaths);
        }
    }

    private function qrPath(DiningTable $table): string
    {
        return "tenants/{$table->tenant_id}/outlets/{$table->outlet_id}/tables/{$table->getKey()}/qr/".Str::uuid().'.svg';
    }

    private function render(string $plainToken): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(480, 4),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString(
            url('/q/'.$plainToken),
            'UTF-8',
            ErrorCorrectionLevel::M(),
        );
    }
}
