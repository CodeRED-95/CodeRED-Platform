<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Protecciones alrededor del borrado de declaraciones.
 *
 * Nacen de un incidente real: el 16/08/2026 una limpieza manual con
 * `DELETE ... WHERE id BETWEEN 10 AND 20` se llevó por delante una declaración
 * que no era de la prueba. Lo que se fija aquí es que la limpieza vuelva a ser
 * posible sólo de forma exacta, y que exista un camino de vuelta.
 */
class DeclarationSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'seg-'.uniqid(), 'name' => 'Tester']);
        $permission = Permission::query()->firstOrCreate(
            ['slug' => 'declaracion-jurada.view'],
            ['name' => 'declaracion-jurada.view']
        );
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function agencia(): Agency
    {
        return Agency::factory()->create([
            'department' => 'PIURA',
            'province' => 'PIURA',
            'district' => 'CASTILLA',
            'name' => 'AV TACNA',
            'created_by' => User::factory()->create()->getKey(),
        ]);
    }

    private function crear(Agency $agency, ?string $run, string $nombre): int
    {
        $payload = [
            'remitente_dni' => '12345678',
            'remitente_nombre' => $nombre,
            'agency_id' => $agency->getKey(),
        ];

        if ($run !== null) {
            $payload['validation_run'] = $run;
        }

        return (int) $this->postJson('/api/v1/declarations', $payload)
            ->assertCreated()
            ->json('data.id');
    }

    // --- Marcador de validación ---------------------------------------------

    public function test_una_declaracion_normal_no_lleva_marca_de_validacion(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');

        $this->assertNull(Declaration::query()->findOrFail($id)->validation_run);
    }

    public function test_la_marca_de_validacion_debe_ser_un_uuid(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', [
            'remitente_dni' => '12345678',
            'remitente_nombre' => 'X',
            'agency_id' => $this->agencia()->getKey(),
            'validation_run' => 'no-es-un-uuid',
        ])->assertStatus(422)->assertJsonValidationErrors('validation_run');
    }

    // --- Limpieza -----------------------------------------------------------

    /**
     * El corazón del asunto: la limpieza sólo alcanza lo que lleva la marca de
     * esa ejecución. Una declaración real con un identificador contiguo —el
     * caso exacto que se perdió— queda intacta.
     */
    public function test_la_limpieza_solo_borra_lo_de_su_ejecucion(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);
        $agency = $this->agencia();
        $run = (string) Str::uuid();

        $validacion = $this->crear($agency, $run, 'VALIDACION');
        $real = $this->crear($agency, null, 'PERSONA REAL');
        $otraEjecucion = $this->crear($agency, (string) Str::uuid(), 'OTRA VALIDACION');

        $this->artisan('validation:cleanup', ['run' => $run, '--force' => true])
            ->assertSuccessful();

        $this->assertNull(Declaration::query()->find($validacion));
        $this->assertNotNull(Declaration::query()->find($real));
        $this->assertNotNull(Declaration::query()->find($otraEjecucion));
    }

    /** Sin --force no se toca nada: la simulación es el comportamiento por defecto. */
    public function test_la_limpieza_sin_force_no_borra(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);
        $run = (string) Str::uuid();

        $id = $this->crear($this->agencia(), $run, 'VALIDACION');

        $this->artisan('validation:cleanup', ['run' => $run])->assertSuccessful();

        $this->assertNotNull(Declaration::query()->find($id));
    }

    /** Un identificador que no es UUID no puede seleccionar filas por accidente. */
    public function test_la_limpieza_rechaza_un_identificador_que_no_sea_uuid(): void
    {
        $this->artisan('validation:cleanup', ['run' => '10', '--force' => true])
            ->assertFailed();
    }

    public function test_la_limpieza_de_una_ejecucion_inexistente_no_hace_nada(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');

        $this->artisan('validation:cleanup', ['run' => (string) Str::uuid(), '--force' => true])
            ->assertSuccessful();

        $this->assertNotNull(Declaration::query()->find($id));
    }

    // --- Borrado reversible -------------------------------------------------

    /**
     * Una declaración jurada es un documento legal: si algún día existe un
     * camino para borrarla desde la aplicación, no debe destruirla.
     */
    public function test_borrar_una_declaracion_no_la_destruye(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');

        Declaration::query()->findOrFail($id)->delete();

        $this->assertNull(Declaration::query()->find($id));
        $this->assertNotNull(Declaration::withTrashed()->find($id));

        Declaration::withTrashed()->findOrFail($id)->restore();

        $this->assertNotNull(Declaration::query()->find($id));
    }

    /** El historial del usuario no debe mostrar lo que se dio de baja. */
    public function test_una_declaracion_dada_de_baja_desaparece_del_historial(): void
    {
        $user = $this->usuario();
        Sanctum::actingAs($user, ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');
        Declaration::query()->findOrFail($id)->delete();

        $this->getJson('/api/v1/declarations')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // --- Copia y recuperación -----------------------------------------------

    /**
     * Restaurar es la mitad que importa de una copia de seguridad. Se prueba el
     * viaje entero: copia, pérdida, vuelta.
     */
    public function test_una_declaracion_borrada_se_recupera_desde_la_copia(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');
        $original = Declaration::query()->findOrFail($id);

        $this->artisan('declarations:backup', ['--output' => 'copia.zip'])->assertSuccessful();
        Storage::disk('local')->assertExists('copia.zip');

        // Se pierde del todo, como en el incidente.
        $original->items()->delete();
        $original->forceDelete();
        $this->assertNull(Declaration::withTrashed()->find($id));

        $this->artisan('declarations:restore', ['archive' => 'copia.zip', '--force' => true])
            ->assertSuccessful();

        $recuperada = Declaration::query()->findOrFail($id);
        $this->assertSame('PERSONA REAL', $recuperada->remitente_nombre);
        $this->assertSame($original->sede_destino, $recuperada->sede_destino);
    }

    /** Recuperar un documento perdido no puede pisar uno vivo. */
    public function test_la_restauracion_no_sobrescribe_una_declaracion_existente(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'ORIGINAL');
        $this->artisan('declarations:backup', ['--output' => 'copia.zip'])->assertSuccessful();

        Declaration::query()->findOrFail($id)->forceFill(['remitente_nombre' => 'MODIFICADA DESPUES'])->save();

        $this->artisan('declarations:restore', ['archive' => 'copia.zip', '--force' => true])
            ->assertSuccessful();

        $this->assertSame('MODIFICADA DESPUES', Declaration::query()->findOrFail($id)->remitente_nombre);
    }

    public function test_la_restauracion_sin_force_no_cambia_nada(): void
    {
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $id = $this->crear($this->agencia(), null, 'PERSONA REAL');
        $this->artisan('declarations:backup', ['--output' => 'copia.zip'])->assertSuccessful();

        Declaration::query()->findOrFail($id)->items()->delete();
        Declaration::query()->findOrFail($id)->forceDelete();

        $this->artisan('declarations:restore', ['archive' => 'copia.zip'])->assertSuccessful();

        $this->assertNull(Declaration::withTrashed()->find($id));
    }
}
