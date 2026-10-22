<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\NurseryFixtures;
use Tests\TestCase;

final class CheckInTest extends TestCase
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

    public function test_it_checks_a_child_into_a_room(): void
    {
        Carbon::setTestNow('2026-10-22 09:00:00');
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');

        $response = $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id]);

        $response->assertCreated()
            ->assertJsonPath('data.room_id', $room->id)
            ->assertJsonPath('data.child_id', $child->id)
            ->assertJsonPath('data.checked_in_at', '2026-10-22T09:00:00+00:00')
            ->assertJsonPath('data.checked_out_at', null);

        $checkIn = CheckIn::withoutGlobalScopes()->sole();
        $this->assertSame($this->tenant->id, $checkIn->tenant_id);
        $this->assertSame($room->id, $checkIn->room_id);
        $this->assertSame($child->id, $checkIn->child_id);
        $this->assertNull($checkIn->checked_out_at);
    }

    public function test_it_requires_a_child_id(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');

        $this->postJson("/rooms/{$room->id}/check-ins", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        $this->assertSame(0, $this->openCheckInCount());
    }

    public function test_it_rejects_a_non_numeric_child_id(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');

        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => 'amara'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');
    }

    public function test_it_rejects_a_child_that_does_not_exist(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');

        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        $this->assertSame(0, $this->openCheckInCount());
    }

    public function test_it_returns_404_for_an_unknown_room(): void
    {
        $child = $this->makeChild($this->tenant, 'Amara');

        $this->postJson('/rooms/999999/check-ins', ['child_id' => $child->id])
            ->assertNotFound();

        $this->assertSame(0, $this->openCheckInCount());
    }

    public function test_it_rejects_a_second_check_in_while_the_child_is_still_in_another_room(): void
    {
        $sunflowers = $this->makeRoom($this->tenant, 'Sunflowers');
        $explorers = $this->makeRoom($this->tenant, 'Little Explorers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $first = $this->checkedIn($sunflowers, $child);

        $this->postJson("/rooms/{$explorers->id}/check-ins", ['child_id' => $child->id])
            ->assertConflict()
            ->assertJsonPath('code', 'child_already_checked_in')
            ->assertJsonPath('current_check_in.id', $first->id)
            ->assertJsonPath('current_check_in.room_id', $sunflowers->id);

        // Nothing moved: the child is still, and only, in the first room.
        $open = CheckIn::withoutGlobalScopes()->whereNull('checked_out_at')->sole();
        $this->assertSame($first->id, $open->id);
        $this->assertSame($sunflowers->id, $open->room_id);
    }

    public function test_it_rejects_checking_the_child_into_the_same_room_twice(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $this->checkedIn($room, $child);

        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $child->id])
            ->assertConflict()
            ->assertJsonPath('code', 'child_already_checked_in');

        $this->assertSame(1, $this->openCheckInCount());
    }

    public function test_a_child_can_be_checked_into_another_room_after_being_checked_out(): void
    {
        $sunflowers = $this->makeRoom($this->tenant, 'Sunflowers');
        $explorers = $this->makeRoom($this->tenant, 'Little Explorers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $this->checkedOut(
            $sunflowers,
            $child,
            Carbon::parse('2026-10-22 08:00:00'),
            Carbon::parse('2026-10-22 10:00:00'),
        );

        $this->postJson("/rooms/{$explorers->id}/check-ins", ['child_id' => $child->id])
            ->assertCreated()
            ->assertJsonPath('data.room_id', $explorers->id);

        $this->assertSame(2, CheckIn::withoutGlobalScopes()->count());
        $this->assertSame(1, $this->openCheckInCount());
    }

    public function test_different_children_can_be_in_the_same_room(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $amara = $this->makeChild($this->tenant, 'Amara');
        $ben = $this->makeChild($this->tenant, 'Ben');

        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $amara->id])->assertCreated();
        $this->postJson("/rooms/{$room->id}/check-ins", ['child_id' => $ben->id])->assertCreated();

        $this->assertSame(2, $this->openCheckInCount());
    }
}
