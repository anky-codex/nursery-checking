<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\CheckIn;
use App\Models\Room;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
{
    protected $model = CheckIn::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            // Room and child default to ones belonging to the same tenant.
            'room_id' => fn (array $attributes) => Room::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'child_id' => fn (array $attributes) => Child::factory()
                ->create(['tenant_id' => $attributes['tenant_id']])->getKey(),
            'checked_in_at' => now(),
            'checked_out_at' => null,
        ];
    }

    public function checkedOut(?CarbonInterface $at = null): static
    {
        return $this->state(fn () => ['checked_out_at' => $at ?? now()]);
    }
}
