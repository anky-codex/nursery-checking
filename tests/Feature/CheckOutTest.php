<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\NurseryFixtures;
use Tests\TestCase;

final class CheckOutTest extends TestCase
{
    use NurseryFixtures;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->asTenant($this->tenant);
    }

    public function test_it_checks_a_child_out(): void
    {
        Carbon::setTestNow('2026-10-22 15:30:00');
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $checkIn = $this->checkedIn($room, $child, Carbon::parse('2026-10-22 09:00:00'));

        $this->postJson("/check-ins/{$checkIn->id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.id', $checkIn->id)
            ->assertJsonPath('data.checked_in_at', '2026-10-22T09:00:00+00:00')
            ->assertJsonPath('data.checked_out_at', '2026-10-22T15:30:00+00:00');

        $this->assertSame(0, $this->openCheckInCount());
    }

    public function test_a_checked_out_child_no_longer_appears_in_the_room(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $checkIn = $this->checkedIn($room, $child);

        $this->postJson("/check-ins/{$checkIn->id}/check-out")->assertOk();

        $this->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_cannot_check_out_twice_and_keeps_the_original_time(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $checkIn = $this->checkedOut(
            $room,
            $child,
            Carbon::parse('2026-10-22 08:00:00'),
            Carbon::parse('2026-10-22 10:00:00'),
        );
        Carbon::setTestNow('2026-10-22 17:00:00');

        $this->postJson("/check-ins/{$checkIn->id}/check-out")
            ->assertConflict()
            ->assertJsonPath('code', 'check_in_already_closed');

        $this->assertTrue(
            CheckIn::withoutGlobalScopes()->findOrFail($checkIn->id)
                ->checked_out_at->equalTo(Carbon::parse('2026-10-22 10:00:00'))
        );
    }

    public function test_it_returns_404_for_an_unknown_check_in(): void
    {
        $this->postJson('/check-ins/999999/check-out')->assertNotFound();
    }

    public function test_it_returns_404_for_a_non_numeric_check_in_id(): void
    {
        $this->postJson('/check-ins/abc/check-out')->assertNotFound();
    }

    public function test_a_child_can_be_checked_in_again_after_checking_out(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $checkIn = $this->checkedIn($room, $child);

        $this->postJson("/check-ins/{$checkIn->id}/check-out")->assertOk();
        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])->assertCreated();

        $this->assertSame(1, $this->openCheckInCount());
        $this->assertSame(2, CheckIn::withoutGlobalScopes()->count());
    }
}
