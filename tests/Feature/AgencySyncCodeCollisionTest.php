<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Agencies\Actions\ConfirmAgencyImportRunAction;
use App\Modules\Agencies\Models\Agency;
use App\Modules\Agencies\Models\AgencyImportItem;
use App\Modules\Agencies\Models\AgencyImportRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La abreviatura de Shalom no identifica una agencia: `PSC` vale tanto para
 * PISAC (Calca, Cusco) como para PISCO (Ica). Cuando llegaba una agencia nueva
 * cuyo external_id aun no existia, el emparejamiento de respaldo por `code`
 * encontraba la otra y proponia sobrescribirla entera.
 */
class AgencySyncCodeCollisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_agencia_nueva_no_se_apodera_de_otra_que_comparte_abreviatura(): void
    {
        $pisac = Agency::factory()->create([
            'external_id' => 521,
            'code' => 'PSC',
            'name' => 'PISAC',
            'department' => 'CUSCO',
            'province' => 'CALCA',
            'district' => 'PISAC',
        ]);

        $run = AgencyImportRun::create(['type' => 'shalom_sync', 'status' => 'ready_for_review']);

        // SAN CLEMENTE (Pisco, Ica): external_id 496, que todavia no existe.
        AgencyImportItem::create([
            'import_run_id' => $run->id,
            'external_id' => 496,
            'action' => 'create',
            'selected' => true,
            'incoming_data' => [
                'external_id' => 496,
                'code' => 'PSC',
                'name' => 'SAN CLEMENTE',
                'department' => 'ICA',
                'province' => 'PISCO',
                'district' => 'SAN CLEMENTE',
            ],
        ]);

        $user = User::factory()->create(['status' => 'active']);
        $result = (new ConfirmAgencyImportRunAction)->execute($run, $user->id);

        // La agencia de Cusco queda intacta.
        $pisac->refresh();
        $this->assertSame('PISAC', $pisac->name);
        $this->assertSame('CUSCO', $pisac->department);
        $this->assertSame(521, (int) $pisac->external_id);

        // Y la de Ica entra como agencia propia, compartiendo abreviatura:
        // `code` ya no es unico, la identidad es external_id.
        $this->assertSame(1, $result['created']);
        $sanClemente = Agency::query()->where('external_id', 496)->first();
        $this->assertNotNull($sanClemente);
        $this->assertSame('SAN CLEMENTE', $sanClemente->name);
        $this->assertSame('PSC', $sanClemente->code);
        $this->assertNotSame($pisac->id, $sanClemente->id);
    }

    public function test_una_agencia_nueva_con_abreviatura_repetida_es_un_alta(): void
    {
        // El resguardo de verdad esta antes: la vista previa la clasifica como
        // conflicto, con el motivo escrito, y los conflictos no vienen
        // marcados para aplicar.
        $pisac = Agency::factory()->create(['external_id' => 521, 'code' => 'PSC', 'name' => 'PISAC']);

        $job = new \App\Modules\Agencies\Jobs\SyncShalomAgenciesJob(1);
        $method = new \ReflectionMethod($job, 'matchAgency');
        $method->setAccessible(true);

        [$match, $reason] = $method->invoke($job, [
            'external_id' => 496,
            'code' => 'PSC',
            'name' => 'SAN CLEMENTE',
            'department' => 'ICA',
            'province' => 'PISCO',
            'district' => 'SAN CLEMENTE',
        ]);

        // Ni empareja ni es conflicto: es un alta. La abreviatura compartida
        // ya no impide nada, y PISAC no se toca porque lleva otro external_id.
        $this->assertNull($match, 'No debe emparejar con una agencia que lleva otro external_id.');
        $this->assertNull($reason, 'Compartir abreviatura no es un conflicto: es una agencia nueva.');
        $this->assertSame(521, (int) $pisac->fresh()->external_id);
    }

    public function test_dos_registros_sin_external_id_con_la_misma_abreviatura_si_son_conflicto(): void
    {
        // Aqui la abreviatura no distingue y no hay external_id que desempate:
        // emparejar con cualquiera de las dos seria una moneda al aire.
        Agency::factory()->create(['external_id' => null, 'code' => 'DUP', 'name' => 'UNA']);
        Agency::factory()->create(['external_id' => null, 'code' => 'DUP', 'name' => 'OTRA']);

        $job = new \App\Modules\Agencies\Jobs\SyncShalomAgenciesJob(1);
        $method = new \ReflectionMethod($job, 'matchAgency');
        $method->setAccessible(true);

        [$match, $reason] = $method->invoke($job, ['external_id' => 999, 'code' => 'DUP', 'name' => 'TERCERA', 'department' => null, 'province' => null, 'district' => null]);

        $this->assertNull($match);
        $this->assertStringContainsString('DUP', (string) $reason);
    }

    public function test_sin_external_id_entrante_el_respaldo_por_abreviatura_sigue_valiendo(): void
    {
        // Un registro heredado sin external_id debe poder seguir emparejandose
        // por code: el resguardo solo actua cuando ambos lados lo traen y son
        // distintos.
        $agency = Agency::factory()->create(['external_id' => null, 'code' => 'ABC', 'name' => 'ANTIGUA']);

        $run = AgencyImportRun::create(['type' => 'shalom_sync', 'status' => 'ready_for_review']);
        AgencyImportItem::create([
            'import_run_id' => $run->id,
            'action' => 'update',
            'selected' => true,
            'incoming_data' => ['code' => 'ABC', 'name' => 'ACTUALIZADA'],
        ]);

        $user = User::factory()->create(['status' => 'active']);
        (new ConfirmAgencyImportRunAction)->execute($run, $user->id);

        $this->assertSame('ACTUALIZADA', $agency->fresh()->name);
    }
}
