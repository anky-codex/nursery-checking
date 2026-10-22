<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Small builders so each test reads as a story about nurseries, not about factories.
 */
trait NurseryFixtures
{
    /** Make every following request in this test arrive as the given tenant. */
    protected function asTenant(Tenant $tenant): static
    {
        return $this->withHeader('X-Tenant-ID', (string) $tenant->getKey());
    }

    protected function makeRoom(Tenant $tenant, string $name): Room
    {
        return Room::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => $name]);
    }

    protected function makeChild(Tenant $tenant, string $name): Child
    {
        return Child::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => $name]);
    }

    /** An open check-in, created directly (bypassing the API). */
    protected function checkedIn(Room $room, Child $child, ?CarbonInterface $at = null): CheckIn
    {
        return CheckIn::factory()->create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->getKey(),
            'child_id' => $child->getKey(),
            'checked_in_at' => $at ?? now(),
        ]);
    }

    /** A check-in that has already been closed, created directly. */
    protected function checkedOut(Room $room, Child $child, CarbonInterface $in, CarbonInterface $out): CheckIn
    {
        return CheckIn::factory()->checkedOut($out)->create([
            'tenant_id' => $room->tenant_id,
            'room_id' => $room->getKey(),
            'child_id' => $child->getKey(),
            'checked_in_at' => $in,
        ]);
    }

    /** Number of open check-ins across all tenants (deliberately unscoped). */
    protected function openCheckInCount(): int
    {
        return CheckIn::withoutGlobalScopes()->whereNull('checked_out_at')->count();
    }
}
