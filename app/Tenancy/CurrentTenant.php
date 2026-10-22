<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Holds the tenant for the request being handled.
 * Bound as a scoped service, so every request gets its own instance.
 */
final class CurrentTenant
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    public function id(): int
    {
        $id = $this->tenant?->getKey() ?? throw new NoTenantResolved();

        return (int) $id;
    }
}
