<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Enums\AgencyStatus;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencySyncChange;
use App\Modules\Agencies\Services\AgencySyncCursor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class AgencyIncrementalSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_metadata_etag_is_stable_and_changes_for_every_lifecycle_event(): void
    {
        $token = $this->token();
        $initial = $this->withToken($token)->getJson('/api/v1/catalog/metadata')->assertOk();
        $etag = (string) $initial->headers->get('ETag');
        $this->assertNotSame('', $etag);
        $this->assertStringContainsString('private', (string) $initial->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $initial->headers->get('Cache-Control'));
        $this->assertStringContainsString('Authorization', (string) $initial->headers->get('Vary'));
        $this->assertStringContainsString('Accept-Encoding', (string) $initial->headers->get('Vary'));
        $notModified = $this->withToken($token)->withHeader('If-None-Match', $etag)->get('/api/v1/catalog/metadata');
        $notModified->assertStatus(304);
        $this->assertSame('', $notModified->getContent());

        $agency = Agency::factory()->create();
        $createdEtag = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($etag, $createdEtag);
        $agency->update(['name' => 'Agencia sincronizada']);
        $updatedEtag = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($createdEtag, $updatedEtag);
        $agency->delete();
        $deletedEtag = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($updatedEtag, $deletedEtag);
        $agency->restore();
        $restoredEtag = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($deletedEtag, $restoredEtag);
        $agency->delete();
        $agency->forceDelete();
        $forceDeletedEtag = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($restoredEtag, $forceDeletedEtag);
        $this->assertDatabaseHas('agency_sync_changes', ['agency_internal_id' => $agency->id, 'operation' => 'delete']);
    }

    public function test_incremental_changes_are_paginated_deterministically_without_omissions(): void
    {
        $token = $this->token();
        $cursor = $this->withToken($token)->getJson('/api/v1/catalog/metadata')->json('current_cursor');
        $this->assertIsString($cursor);
        $time = now()->startOfSecond();
        $agencies = collect(range(1, 3))->map(fn (): Agency => Agency::factory()->create(['updated_at' => $time]));

        $first = $this->withToken($token)->getJson('/api/v1/agencies/changes?limit=2&cursor='.urlencode($cursor));
        $first->assertOk()->assertJsonPath('meta.has_more', true)->assertJsonCount(2, 'data.upserted');
        $nextCursor = $first->json('meta.next_cursor');
        $this->assertIsString($nextCursor);
        $second = $this->withToken($token)->getJson('/api/v1/agencies/changes?limit=2&cursor='.urlencode($nextCursor));
        $second->assertOk()->assertJsonPath('meta.has_more', false)->assertJsonCount(1, 'data.upserted');
        $ids = collect($first->json('data.upserted'))->merge($second->json('data.upserted'))->pluck('internal_id');
        $this->assertSame($agencies->pluck('id')->sort()->values()->all(), $ids->sort()->values()->all());
        $this->assertSame($ids->count(), $ids->unique()->count());
    }

    public function test_lifecycle_contract_remains_replayable_after_force_delete(): void
    {
        $token = $this->token();
        $cursor = app(AgencySyncCursor::class)->encode((int) (AgencySyncChange::query()->max('id') ?? 0));
        $agency = Agency::factory()->create(['external_id' => 810]);
        $agency->update(['name' => 'Cambio incremental']);
        $agency->delete();
        $agency->restore();
        $agency->delete();
        $agency->forceDelete();

        $response = $this->withToken($token)->getJson('/api/v1/agencies/changes?limit=100&cursor='.urlencode($cursor));
        $response->assertOk();
        $response->assertJsonCount(0, 'data.upserted')->assertJsonCount(1, 'data.deleted');
        $response->assertJsonFragment(['internal_id' => $agency->id, 'id' => 810, 'code' => $agency->code]);
    }

    public function test_status_and_operations_center_changes_refresh_etag_and_incremental_payload(): void
    {
        $token = $this->token();
        $agency = Agency::factory()->create([
            'status' => AgencyStatus::Inactive,
            'is_operations_center' => false,
        ]);
        $cursor = $this->withToken($token)->getJson('/api/v1/catalog/metadata')->json('current_cursor');
        $before = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');

        $agency->update(['status' => AgencyStatus::Active]);
        $afterStatus = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($before, $afterStatus);

        $agency->update(['is_operations_center' => true]);
        $afterOperationsCenter = (string) $this->withToken($token)->getJson('/api/v1/catalog/metadata')->headers->get('ETag');
        $this->assertNotSame($afterStatus, $afterOperationsCenter);

        $this->withToken($token)->getJson('/api/v1/agencies/changes?cursor='.urlencode((string) $cursor))->assertOk()
            ->assertJsonPath('data.upserted.0.internal_id', $agency->id)
            ->assertJsonPath('data.upserted.0.estado', 'Activa')
            ->assertJsonPath('data.upserted.0.centro_operaciones', true)
            ->assertJsonPath('meta.schema_version', 2);
    }

    public function test_invalid_schema_and_expired_cursors_require_full_sync(): void
    {
        $token = $this->token();
        $cursor = app(AgencySyncCursor::class)->encode(0);
        $this->withToken($token)->getJson('/api/v1/agencies/changes?cursor='.urlencode($cursor.'altered'))
            ->assertStatus(409)->assertJsonPath('code', 'full_sync_required');
        config()->set('api.agency_schema_version', 3);
        $this->withToken($token)->getJson('/api/v1/agencies/changes?cursor='.urlencode($cursor))
            ->assertStatus(409)->assertJsonPath('meta.schema_version', 3);
        config()->set('api.agency_schema_version', 2);

        Agency::factory()->create();
        AgencySyncChange::query()->update(['changed_at' => now()->subDays(200)]);
        $this->artisan('agencies:prune-sync-changes', ['--dry-run' => true])->assertSuccessful();
        $this->assertGreaterThan(0, AgencySyncChange::query()->count());
        $this->artisan('agencies:prune-sync-changes')->assertSuccessful();
        $this->assertSame(0, AgencySyncChange::query()->count());
        $this->withToken($token)->getJson('/api/v1/agencies/changes?cursor='.urlencode($cursor))
            ->assertStatus(409)->assertJsonPath('code', 'full_sync_required');
    }

    public function test_rollback_does_not_leave_agency_or_sync_event(): void
    {
        $before = AgencySyncChange::query()->count();
        try {
            DB::transaction(function (): void {
                Agency::factory()->create(['code' => 'ROLLBACK-SYNC']);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // Expected rollback.
        }
        $this->assertDatabaseMissing('agencies', ['code' => 'ROLLBACK-SYNC']);
        $this->assertSame($before, AgencySyncChange::query()->count());
    }

    public function test_catalog_etag_varies_by_query_and_supports_conditional_request(): void
    {
        Agency::factory()->count(2)->create();
        $token = $this->token();
        $pageOne = $this->withToken($token)->getJson('/api/v1/agencies?per_page=1&page=1')->assertOk();
        $pageTwo = $this->withToken($token)->getJson('/api/v1/agencies?per_page=1&page=2')->assertOk();
        $etag = (string) $pageOne->headers->get('ETag');
        $this->assertNotSame($etag, $pageTwo->headers->get('ETag'));
        $pageOne->assertJsonPath('meta.schema_version', 2);
        $this->withToken($token)->withHeader('If-None-Match', $etag)
            ->get('/api/v1/agencies?per_page=1&page=1')->assertStatus(304);
    }

    private function token(): string
    {
        return User::factory()->create()->createToken('Sync API', ['agencies:read'])->plainTextToken;
    }
}
