<?php

namespace Tests\Feature;

use App\Models\DniRecord;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DniLegacyImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('dni_consultas_legacy_test', function (Blueprint $table): void {
            $table->id();
            $table->string('perudevs_id')->nullable();
            $table->string('dni', 8);
            $table->string('nombres');
            $table->string('apellido_paterno')->nullable();
            $table->string('apellido_materno')->nullable();
            $table->string('nombre_completo');
            $table->string('genero')->nullable();
            $table->string('fecha_nacimiento')->nullable();
            $table->string('codigo_verificacion')->nullable();
            $table->timestamp('fecha_consulta')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
        });

        DB::table('dni_consultas_legacy_test')->insert([
            'perudevs_id' => 'legacy-1',
            'dni' => '00123456',
            'nombres' => 'MARIA',
            'apellido_paterno' => 'JIMENEZ',
            'apellido_materno' => 'DIAZ',
            'nombre_completo' => 'MARIA JIMENEZ DIAZ',
            'genero' => 'F',
            'fecha_nacimiento' => '16/11/1994',
            'codigo_verificacion' => '8',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dni_consultas_legacy_test');
        parent::tearDown();
    }

    public function test_dry_run_and_import_preserve_dni_and_avoid_duplicates(): void
    {
        $options = ['--connection' => config('database.default'), '--table' => 'dni_consultas_legacy_test', '--chunk' => 1];

        $this->artisan('dni:import-legacy', $options + ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('dni_records', 0);

        $this->artisan('dni:import-legacy', $options)->assertSuccessful();
        $this->assertDatabaseHas('dni_records', [
            'dni' => '00123456',
            'fecha_nacimiento' => '1994-11-16',
            'codigo_verificacion' => '8',
            'source' => 'import',
        ]);

        $this->artisan('dni:import-legacy', $options)->assertSuccessful();
        $this->assertDatabaseCount('dni_records', 1);
        $this->assertSame('00123456', DniRecord::query()->sole()->dni);
    }
}
