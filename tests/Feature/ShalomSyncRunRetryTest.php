<?php

namespace Tests\Feature;

use App\Livewire\Admin\Agencies\ShalomSyncRun;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Jobs\SyncShalomAgenciesJob;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ShalomSyncRunRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_retry_does_not_dispatch_when_run_is_processing(): void
    {
        Bus::fake();
        $user = $this->authorizedUser();
        $run = AgencyImportRun::create([
            'type' => 'shalom_sync',
            'status' => 'processing',
            'chosen_storage_path' => 'imports/shalom/1/chosen-original.json',
        ]);

        $component = new ShalomSyncRun;
        $component->importRun = $run;

        $this->actingAs($user);
        $component->retry();

        Bus::assertNothingDispatched();
    }

    public function test_retry_dispatches_again_for_failed_run_with_empty_coordinates_safe_flow(): void
    {
        Bus::fake();
        $user = $this->authorizedUser();
        $run = AgencyImportRun::create([
            'type' => 'shalom_sync',
            'status' => 'failed',
            'error_message' => 'Anterior',
            'chosen_storage_path' => 'imports/shalom/2/chosen-original.json',
        ]);

        $component = new ShalomSyncRun;
        $component->importRun = $run;

        $this->actingAs($user);
        $component->retry();

        Bus::assertDispatched(SyncShalomAgenciesJob::class);
        $this->assertSame('pending', $run->fresh()->status);
        $this->assertNull($run->fresh()->error_message);
    }

    private function authorizedUser(): User
    {
        $role = Role::query()->create([
            'name' => 'Super Administrador',
            'slug' => 'super-admin',
            'is_system' => true,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
