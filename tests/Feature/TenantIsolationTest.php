<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\Child;
use App\Models\Room;
use App\Models\Tenant;
use App\Tenancy\CurrentTenant;
use App\Tenancy\NoTenantResolved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\NurseryFixtures;
use Tests\TestCase;

/**
 * Tenant A must never be able to read or change tenant B's data.
 * Both tenants deliberately have a room with the same name.
 */
final class TenantIsolationTest extends TestCase
{
    use NurseryFixtures;
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Room $roomA;
    private Room $roomB;
    private Child $childA;
    private Child $childB;
    private CheckIn $checkInB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->roomA = $this->makeRoom($this->tenantA, 'Sunflowers');
        $this->roomB = $this->makeRoom($this->tenantB, 'Sunflowers');
        $this->childA = $this->makeChild($this->tenantA, 'Amara');
        $this->childB = $this->makeChild($this->tenantB, 'Bruno');

        // Bruno (tenant B) is in tenant B's room.
        $this->checkInB = $this->checkedIn($this->roomB, $this->childB);
    }

    // --- Identifying the tenant -------------------------------------------

    /** @return array<string, array{string, string}> */
    public static function tenantRoutes(): array
    {
        return [
            'check in' => ['POST', '/rooms/1/check-ins'],
            'check out' => ['POST', '/check-ins/1/check-out'],
            'occupants' => ['GET', '/rooms/1/occupants'],
        ];
    }

    #[DataProvider('tenantRoutes')]
    public function test_a_request_without_a_tenant_header_is_rejected(string $method, string $uri): void
    {
        $this->json($method, $uri, ['child_id' => 1])->assertStatus(400);
    }

    #[DataProvider('tenantRoutes')]
    public function test_a_non_numeric_tenant_header_is_rejected(string $method, string $uri): void
    {
        $this->withHeader('X-Tenant-ID', 'bright-beginnings')
            ->json($method, $uri, ['child_id' => 1])
            ->assertStatus(400);
    }

    #[DataProvider('tenantRoutes')]
    public function test_an_unknown_tenant_is_rejected(string $method, string $uri): void
    {
        $this->withHeader('X-Tenant-ID', '999999')
            ->json($method, $uri, ['child_id' => 1])
            ->assertNotFound();
    }

    // --- Reading ----------------------------------------------------------

    public function test_tenant_a_cannot_list_the_occupants_of_tenant_bs_room(): void
    {
        $response = $this->asTenant($this->tenantA)
            ->getJson("/rooms/{$this->roomB->id}/occupants");

        $response->assertNotFound();
        $this->assertStringNotContainsString('Bruno', $response->getContent());
    }

    public function test_each_tenant_only_sees_its_own_occupants(): void
    {
        $this->checkedIn($this->roomA, $this->childA);

        $this->asTenant($this->tenantA)
            ->getJson("/rooms/{$this->roomA->id}/occupants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child.name', 'Amara');

        $this->asTenant($this->tenantB)
            ->getJson("/rooms/{$this->roomB->id}/occupants")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.child.name', 'Bruno');
    }

    // --- Changing ---------------------------------------------------------

    public function test_tenant_a_cannot_check_out_tenant_bs_check_in(): void
    {
        $this->asTenant($this->tenantA)
            ->postJson("/check-ins/{$this->checkInB->id}/check-out")
            ->assertNotFound();

        $this->assertNull($this->checkInB->fresh()->checked_out_at);
    }

    public function test_tenant_a_cannot_check_a_child_into_tenant_bs_room(): void
    {
        $this->asTenant($this->tenantA)
            ->postJson("/rooms/{$this->roomB->id}/check-ins", ['child_id' => $this->childA->id])
            ->assertNotFound();

        $this->assertSame(0, CheckIn::withoutGlobalScopes()->where('child_id', $this->childA->id)->count());
    }

    public function test_tenant_a_cannot_check_tenant_bs_child_into_its_own_room(): void
    {
        $this->asTenant($this->tenantA)
            ->postJson("/rooms/{$this->roomA->id}/check-ins", ['child_id' => $this->childB->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('child_id');

        $this->assertSame(1, CheckIn::withoutGlobalScopes()->where('child_id', $this->childB->id)->count());
    }

    public function test_a_tenant_id_sent_in_the_body_is_ignored(): void
    {
        $this->asTenant($this->tenantA)
            ->postJson("/rooms/{$this->roomA->id}/check-ins", [
                'child_id' => $this->childA->id,
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertCreated();

        $created = CheckIn::withoutGlobalScopes()->where('child_id', $this->childA->id)->sole();
        $this->assertSame($this->tenantA->id, $created->tenant_id);
    }

    public function test_another_tenants_child_is_indistinguishable_from_a_missing_child(): void
    {
        // Tenant A tries Bruno's id: the answer is the same as for a child that does not exist.
        $forB = $this->asTenant($this->tenantA)
            ->postJson("/rooms/{$this->roomA->id}/check-ins", ['child_id' => $this->childB->id]);
        $forNobody = $this->asTenant($this->tenantA)
            ->postJson("/rooms/{$this->roomA->id}/check-ins", ['child_id' => 999999]);

        $this->assertSame($forNobody->getStatusCode(), $forB->getStatusCode());
        $this->assertSame($forNobody->json('errors'), $forB->json('errors'));
    }

    // --- The scope itself -------------------------------------------------

    public function test_models_are_scoped_to_the_current_tenant(): void
    {
        app(CurrentTenant::class)->set($this->tenantA);

        $this->assertSame([$this->roomA->id], Room::query()->pluck('id')->all());
        $this->assertSame([$this->childA->id], Child::query()->pluck('id')->all());
        $this->assertSame(0, CheckIn::query()->count());
    }

    public function test_queries_fail_closed_when_no_tenant_is_set(): void
    {
        $this->expectException(NoTenantResolved::class);

        Room::query()->get();
    }

    public function test_creating_without_a_tenant_fails_closed(): void
    {
        $this->expectException(NoTenantResolved::class);

        Room::query()->create(['name' => 'Orphan']);
    }
}
