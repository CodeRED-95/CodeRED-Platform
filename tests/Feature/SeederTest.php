<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DEV_ADMIN_NAME=Administrador Dev');
        putenv('DEV_ADMIN_EMAIL=admin@codered.local');
        putenv('DEV_ADMIN_PASSWORD=ChangeMe123!');

        $_ENV['DEV_ADMIN_NAME'] = 'Administrador Dev';
        $_ENV['DEV_ADMIN_EMAIL'] = 'admin@codered.local';
        $_ENV['DEV_ADMIN_PASSWORD'] = 'ChangeMe123!';
    }

    public function test_agency_factory_uses_explicit_new_factory(): void
    {
        $agency = Agency::factory()->make();

        $this->assertInstanceOf(Agency::class, $agency);
        $this->assertNotEmpty($agency->code);
    }

    public function test_database_seeder_creates_admin_and_demo_agencies(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', [
            'email' => 'admin@codered.local',
        ]);

        $admin = User::query()->where('email', 'admin@codered.local')->firstOrFail();

        $this->assertTrue($admin->is_active);
        $this->assertTrue($admin->hasRole('super-admin'));
        $this->assertFalse($admin->hasRole('admin'));
        $this->assertCount(3, Role::query()->get());
        $this->assertGreaterThan(0, Agency::query()->count());
    }
}
