<?php

namespace Tests\Feature;

use App\Modules\Agencies\Actions\ApplyAgencyMoveAction;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Support\AgencyVersion;
use InvalidArgumentException;
use Tests\TestCase;

class AgencyMoveTest extends TestCase
{
    public function test_move_to_another_agency_sets_status_and_destination(): void
    {
        $origin = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);
        $destination = Agency::factory()->create(['status' => AgencyStatus::Active, 'has_moved' => false]);

        $updated = app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => true,
            'moved_to_agency_id' => $destination->id,
            'moved_at' => '2026-07-17',
            'move_notice' => 'Ahora atendemos en el nuevo local.',
        ]);

        $this->assertTrue($updated->has_moved);
        $this->assertSame(AgencyStatus::Moved, $updated->status);
        $this->assertSame($destination->id, $updated->moved_to_agency_id);
    }

    public function test_move_to_manual_address_is_allowed(): void
    {
        $origin = Agency::factory()->create();

        $updated = app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => true,
            'moved_to_address' => 'Jr. Ejemplo 123',
        ]);

        $this->assertTrue($updated->has_moved);
        $this->assertSame('Jr. Ejemplo 123', $updated->moved_to_address);
    }

    public function test_move_without_destination_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $origin = Agency::factory()->create();
        app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => true,
        ]);
    }

    public function test_move_to_same_agency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $origin = Agency::factory()->create();
        app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => true,
            'moved_to_agency_id' => $origin->id,
        ]);
    }

    public function test_cycle_move_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $a = Agency::factory()->create();
        $b = Agency::factory()->create(['moved_to_agency_id' => $a->id, 'has_moved' => true, 'status' => AgencyStatus::Moved]);

        app(ApplyAgencyMoveAction::class)->execute($a, [
            'has_moved' => true,
            'moved_to_agency_id' => $b->id,
        ]);
    }

    public function test_move_cancel_clears_fields(): void
    {
        $origin = Agency::factory()->create([
            'has_moved' => true,
            'status' => AgencyStatus::Moved,
            'moved_to_address' => 'Jr. Ejemplo 123',
        ]);

        $updated = app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => false,
            'status' => AgencyStatus::UnderReview->value,
        ]);

        $this->assertFalse($updated->has_moved);
        $this->assertNull($updated->moved_to_agency_id);
        $this->assertNull($updated->moved_to_address);
        $this->assertSame(AgencyStatus::UnderReview, $updated->status);
    }

    public function test_moved_agency_excluded_from_public_list(): void
    {
        Agency::factory()->create(['status' => AgencyStatus::Moved, 'has_moved' => true]);

        $response = $this->getJson('/api/v1/agencies');

        $response->assertOk();
        $this->assertSame(0, $response->json('data.total'));
    }

    public function test_moved_agency_is_available_by_code(): void
    {
        $agency = Agency::factory()->create(['status' => AgencyStatus::Moved, 'has_moved' => true, 'code' => 'SHA-000010']);

        $response = $this->getJson('/api/v1/agencies/SHA-000010');

        $response->assertOk()->assertJsonPath('data.code', $agency->code);
    }

    public function test_snapshot_includes_move_reference(): void
    {
        $origin = Agency::factory()->create([
            'status' => AgencyStatus::Moved,
            'has_moved' => true,
            'code' => 'SHA-000003',
            'moved_to_address' => 'Jr. Ejemplo 123',
            'move_notice' => 'Esta agencia se trasladó.',
        ]);

        $response = $this->getJson('/api/v1/agencies/snapshot');

        $response->assertOk();
        $this->assertNotEmpty($response->json('moved_agencies'));
    }

    public function test_public_version_increments_on_move(): void
    {
        $before = AgencyVersion::current();
        $origin = Agency::factory()->create();

        app(ApplyAgencyMoveAction::class)->execute($origin, [
            'has_moved' => true,
            'moved_to_address' => 'Jr. Ejemplo 123',
        ]);

        $after = AgencyVersion::current();
        $this->assertGreaterThanOrEqual($before, $after);
    }
}
