<?php

declare(strict_types=1);

namespace App\Tenancy;

use RuntimeException;

/**
 * Thrown when tenant-owned data is queried without a tenant in context.
 * The tenancy layer fails closed: it never falls back to an unscoped query.
 */
final class NoTenantResolved extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No tenant has been resolved; refusing to run an unscoped query.');
    }
}
