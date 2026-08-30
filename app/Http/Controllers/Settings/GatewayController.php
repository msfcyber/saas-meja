<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RotateGatewayCredentialRequest;
use App\Models\PaymentGatewayCredential;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentGatewayCredentialService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GatewayController extends Controller
{
    public function edit(Request $request, TenantContext $context): Response
    {
        $tenant = $context->tenantOrFail();
        $this->authorizeOwner($request, $tenant);
        $credential = PaymentGatewayCredential::query()
            ->where('provider', 'midtrans')
            ->whereNull('retired_at')
            ->latest('version')
            ->first();

        return Inertia::render('settings/gateway', [
            'gateway' => [
                'provider' => 'midtrans',
                'configured' => $credential !== null,
                'version' => $credential?->version,
                'configured_at' => $credential?->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function rotate(
        RotateGatewayCredentialRequest $request,
        TenantContext $context,
        PaymentGatewayCredentialService $credentials,
    ): RedirectResponse {
        $tenant = $context->tenantOrFail();
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $credentials->rotate(
            $tenant,
            $user,
            'midtrans',
            (string) $request->validated('server_key'),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Credential Midtrans berhasil dirotasi.',
        ]);

        return to_route('gateway.edit');
    }

    private function authorizeOwner(Request $request, Tenant $tenant): void
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
                && $user->can('gateway.manage')
                && $tenant->users()
                    ->whereKey($user->getKey())
                    ->wherePivot('status', 'active')
                    ->wherePivot('is_owner', true)
                    ->exists(),
            403,
        );
    }
}
