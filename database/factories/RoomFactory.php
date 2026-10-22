<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            // tenant_id is not mass-assignable; factories bypass that on purpose.
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->bothify('Room ###'),
        ];
    }
}
