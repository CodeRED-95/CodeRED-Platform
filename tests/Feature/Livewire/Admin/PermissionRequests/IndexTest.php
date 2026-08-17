<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Admin\PermissionRequests;

use App\Enums\PermissionRequestStatus;
use App\Livewire\Admin\PermissionRequests\Index;
use App\Models\Permission;
use App\Models\PermissionRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Permissions\MobileAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    private function usuarioCon(array $permisos): User
    {
        $usuario = User::factory()->create(['status' => 'active', 'is_active' => true]);

        $rol = Role::create(['slug' => 'prueba-'.uniqid(), 'name' => 'Prueba']);
        $rol->permissions()->sync(
            collect($permisos)
                ->map(fn (string $slug) => Permission::firstOrCreate(['slug' => $slug], ['name' => $slug])->id)
                ->all()
        );
        $usuario->roles()->sync([$rol->id]);

        return $usuario;
    }

    private function solicitudPendiente(?User $solicitante = null, string $permiso = MobileAccess::DNI): PermissionRequest
    {
        return PermissionRequest::create([
            'user_id' => ($solicitante ?? User::factory()->create())->id,
            'permission' => $permiso,
            'status' => PermissionRequestStatus::Pending,
            'requested_at' => now(),
        ]);
    }

    public function test_sin_permiso_de_ver_la_bandeja_no_se_abre(): void
    {
        $this->actingAs($this->usuarioCon(['platform.access']));

        // Livewire traduce la denegacion a 403 en lugar de propagar la excepcion.
        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_muestra_las_solicitudes_pendientes(): void
    {
        $solicitante = User::factory()->create(['name' => 'Ana Solicitante']);
        $this->solicitudPendiente($solicitante);

        $this->actingAs($this->usuarioCon(['permission-requests.view']));

        Livewire::test(Index::class)
            ->assertSee('Ana Solicitante')
            ->assertSee(MobileAccess::label(MobileAccess::DNI))
            ->assertSee('Pendiente');
    }

    public function test_quien_solo_puede_ver_no_puede_aprobar(): void
    {
        $solicitud = $this->solicitudPendiente();

        $this->actingAs($this->usuarioCon(['permission-requests.view']));

        Livewire::test(Index::class)
            // El boton no se pinta...
            ->assertDontSee('Aprobar')
            // ...y llamarlo directamente tampoco sirve: la pantalla no es la
            // que autoriza, pero tampoco puede ser el agujero por el que se
            // salta la autorizacion.
            ->call('approve', $solicitud->id)
            ->assertForbidden();

        $this->assertSame(PermissionRequestStatus::Pending, $solicitud->fresh()->status);
    }

    public function test_aprobar_concede_el_permiso_al_solicitante(): void
    {
        $solicitante = User::factory()->create();
        $solicitud = $this->solicitudPendiente($solicitante, MobileAccess::RUC);

        $revisor = $this->usuarioCon(['permission-requests.view', 'permission-requests.manage']);
        $this->actingAs($revisor);

        Livewire::test(Index::class)
            ->call('approve', $solicitud->id)
            ->assertDispatched('toast');

        $solicitud->refresh();

        $this->assertSame(PermissionRequestStatus::Approved, $solicitud->status);
        $this->assertSame($revisor->id, $solicitud->reviewed_by);
        $this->assertTrue($solicitante->fresh()->hasPermission(MobileAccess::RUC));
    }

    public function test_rechazar_guarda_el_motivo_y_no_concede_nada(): void
    {
        $solicitante = User::factory()->create();
        $solicitud = $this->solicitudPendiente($solicitante);

        $this->actingAs($this->usuarioCon(['permission-requests.view', 'permission-requests.manage']));

        Livewire::test(Index::class)
            ->call('confirmReject', $solicitud->id)
            ->assertSet('rejectingId', $solicitud->id)
            ->set('rejectionReason', 'Solicítalo por el canal del área.')
            ->call('reject')
            ->assertSet('rejectingId', null);

        $solicitud->refresh();

        $this->assertSame(PermissionRequestStatus::Rejected, $solicitud->status);
        $this->assertSame('Solicítalo por el canal del área.', $solicitud->rejection_reason);
        $this->assertFalse($solicitante->fresh()->hasPermission(MobileAccess::DNI));
    }

    public function test_una_solicitud_ya_resuelta_no_se_decide_dos_veces(): void
    {
        $solicitud = $this->solicitudPendiente();

        $revisor = $this->usuarioCon(['permission-requests.view', 'permission-requests.manage']);
        $this->actingAs($revisor);

        $componente = Livewire::test(Index::class)->call('approve', $solicitud->id);

        // Alguien mas se adelanto: el segundo intento avisa en lugar de romper.
        $componente->call('approve', $solicitud->id)->assertDispatched('toast');

        $this->assertSame(PermissionRequestStatus::Approved, $solicitud->fresh()->status);
    }

    public function test_el_filtro_de_estado_separa_pendientes_de_resueltas(): void
    {
        $pendiente = User::factory()->create(['name' => 'Pepe Pendiente']);
        $this->solicitudPendiente($pendiente);

        $resuelta = User::factory()->create(['name' => 'Rita Resuelta']);
        $this->solicitudPendiente($resuelta)->update([
            'status' => PermissionRequestStatus::Rejected,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->usuarioCon(['permission-requests.view']));

        Livewire::test(Index::class)
            ->assertSee('Pepe Pendiente')
            ->assertDontSee('Rita Resuelta')
            ->set('status', 'rejected')
            ->assertSee('Rita Resuelta')
            ->assertDontSee('Pepe Pendiente');
    }
}
