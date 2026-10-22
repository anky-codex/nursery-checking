<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant from the X-Tenant-ID header (authentication is assumed
 * to have happened upstream) and makes it the current tenant for the request.
 */
final class IdentifyTenant
{
    public function __construct(private readonly CurrentTenant $currentTenant)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('X-Tenant-ID');

        if (! is_string($header) || ! ctype_digit($header)) {
            abort(400, 'A numeric X-Tenant-ID header is required.');
        }

        $tenant = Tenant::query()->find((int) $header);

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        $this->currentTenant->set($tenant);

        try {
            return $next($request);
        } finally {
            // Never let a tenant leak into the next request (long-lived workers).
            $this->currentTenant->clear();
        }
    }
}
