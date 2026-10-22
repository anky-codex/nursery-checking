<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\NurseryFixtures;
use Tests\TestCase;

final class RoomOccupantsTest extends TestCase
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

    public function test_it_lists_only_children_currently_in_the_room_earliest_arrival_first(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $amara = $this->makeChild($this->tenant, 'Amara');
        $ben = $this->makeChild($this->tenant, 'Ben');
        $chloe = $this->makeChild($this->tenant, 'Chloe');

        $this->checkedIn($room, $amara, Carbon::parse('2026-10-22 09:00:00'));
        $this->checkedIn($room, $ben, Carbon::parse('2026-10-22 08:30:00'));
        $this->checkedOut(
            $room,
            $chloe,
            Carbon::parse('2026-10-22 08:00:00'),
            Carbon::parse('2026-10-22 09:30:00'),
        );

        $this->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.child.name', 'Ben')
            ->assertJsonPath('data.1.child.name', 'Amara');
    }

    public function test_it_describes_each_occupant(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');
        $child = $this->makeChild($this->tenant, 'Amara');
        $checkIn = $this->checkedIn($room, $child, Carbon::parse('2026-10-22 09:00:00'));

        $this->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertExactJson([
                'data' => [[
                    'check_in_id' => $checkIn->id,
                    'checked_in_at' => '2026-10-22T09:00:00+00:00',
                    'child' => ['id' => $child->id, 'name' => 'Amara'],
                ]],
            ]);
    }

    public function test_it_only_lists_children_in_the_requested_room(): void
    {
        $sunflowers = $this->makeRoom($this->tenant, 'Sunflowers');
        $explorers = $this->makeRoom($this->tenant, 'Little Explorers');
        $amara = $this->makeChild($this->tenant, 'Amara');
        $ben = $this->makeChild($this->tenant, 'Ben');
        $this->checkedIn($sunflowers, $amara);
        $this->checkedIn($explorers, $ben);

        $this->getJson("/rooms/{$sunflowers->id}/occupants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child.name', 'Amara');

        $this->getJson("/rooms/{$explorers->id}/occupants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child.name', 'Ben');
    }

    public function test_an_empty_room_returns_an_empty_list(): void
    {
        $room = $this->makeRoom($this->tenant, 'Sunflowers');

        $this->getJson("/rooms/{$room->id}/occupants")
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_returns_404_for_an_unknown_room(): void
    {
        $this->getJson('/rooms/999999/occupants')->assertNotFound();
    }

    public function test_it_returns_404_for_a_non_numeric_room_id(): void
    {
        $this->getJson('/rooms/sunflowers/occupants')->assertNotFound();
    }

    public function test_the_list_follows_children_moving_between_rooms(): void
    {
        $sunflowers = $this->makeRoom($this->tenant, 'Sunflowers');
        $explorers = $this->makeRoom($this->tenant, 'Little Explorers');
        $child = $this->makeChild($this->tenant, 'Amara');

        $first = $this->postJson("/rooms/{$sunflowers->id}/check-ins", ['child_id' => $child->id])
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/check-ins/{$first}/check-out")->assertOk();
        $this->postJson("/rooms/{$explorers->id}/check-ins", ['child_id' => $child->id])->assertCreated();

        $this->getJson("/rooms/{$sunflowers->id}/occupants")->assertExactJson(['data' => []]);
        $this->getJson("/rooms/{$explorers->id}/occupants")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child.name', 'Amara');
    }
}
