<?php

declare(strict_types=1);

namespace Tests\Feature\Ruc;

use App\Models\Role;
use App\Models\User;
use App\Modules\Ruc\Models\RucBackup;
use App\Modules\Ruc\Models\RucBackupOperation;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucBackupProcessRunner;
use App\Modules\Ruc\Services\RucChunkedBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RucTestingSafetyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->firstOrFail());

        return $user;
    }

    public function test_testing_environment_never_calls_pg_dump_or_pg_restore_and_keeps_operations_inside_testing_database(): void
    {
        $this->assertStringContainsString('testing', (string) config('database.connections.pgsql.database'));

        RucRecord::query()->create([
            'ruc' => '20123456789',
            'razon_social' => 'EMPRESA PRUEBA S.A.C.',
        ]);

        $spy = (object) ['commands' => []];
        $this->app->bind(RucBackupProcessRunner::class, function () use ($spy) {
            return new class($spy) extends RucBackupProcessRunner
            {
                public function __construct(private object $spy) {}

                public function run(Process $process): void
                {
                    $this->spy->commands[] = (string) $process->getCommandLine();
                    $process->run();
                }
            };
        });

        $user = $this->adminUser();
        $backup = app(RucChunkedBackupService::class)->create($user, 1000);
        $this->assertTrue($backup->isChunked());

        Queue::fake();
        $token = 'csrf-token-for-test';
        $response = $this->actingAs($user)
            ->withSession(['_token' => $token])
            ->post(route('admin.ruc.backups.restore', $backup->id), ['_token' => $token]);

        $response->assertRedirect(route('admin.ruc.backups'));

        $allCommands = implode("\n", $spy->commands);
        $this->assertStringNotContainsString('pg_dump', $allCommands);
        $this->assertStringNotContainsString('pg_restore', $allCommands);
        $this->assertStringContainsString('zstd', $allCommands);

        $this->assertGreaterThanOrEqual(1, RucBackup::count());
        $this->assertGreaterThanOrEqual(1, RucBackupOperation::count());
        $this->assertGreaterThanOrEqual(1, RucRecord::count());
        Queue::assertNothingPushed();
    }
}
