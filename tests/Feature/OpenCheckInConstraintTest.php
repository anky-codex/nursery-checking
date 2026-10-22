<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\NurseryFixtures;
use Tests\TestCase;

/**
 * The application checks that a child is not in two rooms; this proves the
 * database enforces it too, which is what protects against racing requests.
 */
final class OpenCheckInConstraintTest extends TestCase
{
    use NurseryFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::getDriverName(), ['sqlite', 'pgsql'], true)) {
            $this->markTestSkipped('The partial unique index is only created on SQLite and PostgreSQL.');
        }
    }

    public function test_the_database_refuses_a_second_open_check_in_for_the_same_child(): void
    {
        $tenant = Tenant::factory()->create();
        $sunflowers = $this->makeRoom($tenant, 'Sunflowers');
        $explorers = $this->makeRoom($tenant, 'Little Explorers');
        $child = $this->makeChild($tenant, 'Amara');
        $this->checkedIn($sunflowers, $child);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->checkedIn($explorers, $child);
    }

    public function test_closed_check_ins_do_not_count_against_the_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $room = $this->makeRoom($tenant, 'Sunflowers');
        $child = $this->makeChild($tenant, 'Amara');

        $this->checkedOut($room, $child, Carbon::parse('2026-10-20 08:00:00'), Carbon::parse('2026-10-20 16:00:00'));
        $this->checkedOut($room, $child, Carbon::parse('2026-10-21 08:00:00'), Carbon::parse('2026-10-21 16:00:00'));
        $this->checkedIn($room, $child, Carbon::parse('2026-10-22 08:00:00'));

        $this->assertSame(1, $this->openCheckInCount());
    }
}
