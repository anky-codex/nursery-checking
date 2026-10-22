<?php

namespace Database\Seeders;

use App\Models\Child;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Two nursery groups with predictable ids, for trying the API by hand:
 *
 *   tenant 1 "Bright Beginnings": rooms 1 Sunflowers, 2 Little Explorers; children 1-3
 *   tenant 2 "Tiny Steps":        rooms 3 Sunflowers, 4 Little Explorers; children 4-6
 *
 * (Ids are as expected on a freshly migrated database.)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'Bright Beginnings' => ['Amara', 'Ben', 'Chloe'],
            'Tiny Steps' => ['Dev', 'Elena', 'Farid'],
        ];

        foreach ($groups as $tenantName => $childNames) {
            $tenant = Tenant::factory()->create(['name' => $tenantName]);

            foreach (['Sunflowers', 'Little Explorers'] as $roomName) {
                Room::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => $roomName]);
            }

            foreach ($childNames as $childName) {
                Child::factory()->create(['tenant_id' => $tenant->getKey(), 'name' => $childName]);
            }
        }
    }
}
