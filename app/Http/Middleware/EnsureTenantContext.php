<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->context->tenant() === null) {
            return redirect()->route('onboarding.create');
        }

        abort_if($this->context->outlet() === null, 403, 'Outlet aktif belum dipilih.');

        return $next($request);
    }
}
