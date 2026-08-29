<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use App\Services\TableQrCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TableQrCodeController extends Controller
{
    public function regenerate(DiningTable $table, TableQrCodeService $qrCodes): RedirectResponse
    {
        $this->authorize('update', $table);
        $qrCodes->issue($table);

        return back()->with('success', 'QR meja berhasil dibuat ulang.');
    }

    public function revoke(DiningTable $table, TableQrCodeService $qrCodes): RedirectResponse
    {
        $this->authorize('update', $table);
        $qrCodes->revoke($table);

        return back()->with('success', 'QR meja berhasil dicabut.');
    }

    public function download(DiningTable $table): Response
    {
        $this->authorize('view', $table);
        $token = $table->activeQrToken;

        if ($token === null || $token->qr_path === null || ! Storage::disk('public')->exists($token->qr_path)) {
            abort(404, 'QR meja belum tersedia.');
        }

        return Storage::disk('public')->download(
            $token->qr_path,
            "qr-{$table->code}.svg",
            [
                'Cache-Control' => 'no-store, private',
                'Content-Type' => 'image/svg+xml',
            ],
        );
    }

    public function print(DiningTable $table): Response
    {
        $this->authorize('view', $table);
        $token = $table->activeQrToken;

        if ($token === null || $token->qr_path === null) {
            abort(404, 'QR meja belum tersedia.');
        }

        $svg = Storage::disk('public')->get($token->qr_path);

        return response()
            ->view('tables.qr-print', [
                'outletName' => $table->outlet->name,
                'tableName' => $table->name,
                'tableCode' => $table->code,
                'qrSvg' => $svg,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
