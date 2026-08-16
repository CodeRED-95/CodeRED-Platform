<?php

namespace Tests\Feature;

use App\Models\Declaration;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Agencies\Models\Agency;
use App\Services\Declarations\DeclarationPdfBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Formato del documento oficial.
 *
 * Lo que se fija aquí: que la sede sea la ubicación completa y no sólo el
 * nombre, que el destinatario, el motivo y los bienes puedan faltar, que la
 * tabla conserve siempre sus tres filas, y que la foto del DNI —y sólo ella—
 * cambie la orientación a apaisada.
 */
class DeclarationFormatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // El endpoint escribe el PDF en el disco `local`. Sin esto, cada
        // ejecucion de la suite deja documentos en el almacenamiento privado
        // real, con identificadores de la base de pruebas.
        Storage::fake('local');
    }

    private const PERMISSION = 'declaracion-jurada.view';

    private function usuario(): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $role = Role::query()->create(['slug' => 'dj-'.uniqid(), 'name' => 'Tester']);
        $permission = Permission::query()->firstOrCreate(['slug' => self::PERMISSION], ['name' => self::PERMISSION]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function agencia(array $overrides = []): Agency
    {
        return Agency::factory()->create(array_merge([
            'department' => 'PIURA',
            'province' => 'PIURA',
            'district' => 'CASTILLA',
            'name' => 'AV TACNA',
            'created_by' => User::factory()->create()->getKey(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function payload(Agency $agency, array $extra = []): array
    {
        return array_merge([
            'remitente_dni' => '12345678',
            'remitente_nombre' => 'MARIA FERNANDEZ',
            'agency_id' => $agency->getKey(),
        ], $extra);
    }

    /**
     * PNG real de 16x10, para no depender de la extension GD -que no esta
     * instalada ni hace falta: FPDF interpreta PNG y JPEG por si mismo-.
     */
    private function fotoDni(): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAABAAAAAKCAIAAAAy3EnLAAAAFElEQVR42mM4oWFDEmIY1TA0NQAA1We7gRA4KX4AAAAASUVORK5CYII='
        );

        return UploadedFile::fake()->createWithContent('dni.png', $png);
    }

    // --- Sede ---------------------------------------------------------------

    /**
     * El nombre de la agencia no dice dónde está. La sede que se imprime tiene
     * que llevar la ubicación entera, y se compone de las columnas del
     * catálogo, nunca troceando texto.
     */
    public function test_la_sede_es_la_ubicacion_completa_de_la_agencia(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency))
            ->assertCreated()
            ->assertJsonPath('data.sede_destino', 'PIURA / PIURA / CASTILLA / AV TACNA');
    }

    /** Una agencia sin distrito no debe imprimir separadores vacíos. */
    public function test_la_sede_omite_las_partes_que_falten(): void
    {
        $agency = $this->agencia(['district' => null, 'province' => null]);
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency))
            ->assertCreated()
            ->assertJsonPath('data.sede_destino', 'PIURA / AV TACNA');
    }

    /** La sede la fija el servidor: lo que mande el cliente se ignora. */
    public function test_la_sede_no_la_decide_el_cliente(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['sede_destino' => 'LO QUE YO QUIERA']))
            ->assertCreated()
            ->assertJsonPath('data.sede_destino', 'PIURA / PIURA / CASTILLA / AV TACNA');
    }

    // --- Campos opcionales --------------------------------------------------

    public function test_se_emite_sin_destinatario_sin_motivo_y_sin_bienes(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency))->assertCreated();

        $declaration = Declaration::query()->firstOrFail();
        $this->assertNull($declaration->destinatario_nombre);
        $this->assertNull($declaration->destinatario_dni);
        $this->assertNull($declaration->motivo_envio);
        $this->assertCount(0, $declaration->items);
    }

    public function test_admite_un_destinatario_a_medias(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency, [
            'destinatario_nombre' => 'JUAN PEREZ',
        ]))->assertCreated();

        $declaration = Declaration::query()->firstOrFail();
        $this->assertSame('JUAN PEREZ', $declaration->destinatario_nombre);
        $this->assertNull($declaration->destinatario_dni);
        $this->assertNull($declaration->destinatario_telefono);
    }

    public function test_un_dni_de_destinatario_mal_formado_sigue_rechazandose(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['destinatario_dni' => '12']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('destinatario_dni');
    }

    public function test_el_remitente_sigue_siendo_obligatorio(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', ['agency_id' => $agency->getKey()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remitente_dni', 'remitente_nombre']);
    }

    // --- Bienes -------------------------------------------------------------

    /**
     * @return array<string, array{int}>
     */
    public static function cantidadesDeBienes(): array
    {
        return ['cero' => [0], 'uno' => [1], 'dos' => [2], 'tres' => [3]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cantidadesDeBienes')]
    public function test_acepta_de_cero_a_tres_bienes(int $cuantos): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $items = [];

        for ($i = 0; $i < $cuantos; $i++) {
            $items[] = ['cantidad' => (string) ($i + 1), 'descripcion' => 'Bien '.($i + 1)];
        }

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['items' => $items]))->assertCreated();

        $this->assertCount($cuantos, Declaration::query()->firstOrFail()->items);
    }

    /** El formato impreso tiene tres filas: un cuarto bien no cabría. */
    public function test_rechaza_mas_de_tres_bienes(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $items = array_fill(0, 4, ['cantidad' => '1', 'descripcion' => 'Bien']);

        $this->postJson('/api/v1/declarations', $this->payload($agency, ['items' => $items]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    // --- Orientación --------------------------------------------------------

    public function test_sin_foto_el_documento_es_vertical(): void
    {
        $agency = $this->agencia();
        $user = $this->usuario();
        Sanctum::actingAs($user, ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency))->assertCreated();

        $pdf = app(DeclarationPdfBuilder::class)->build(Declaration::query()->with('items')->firstOrFail());

        [$width, $height] = $this->mediaBox($pdf);
        $this->assertGreaterThan($width, $height, 'El documento sin foto debe ser A4 vertical.');
    }

    public function test_con_foto_el_documento_es_apaisado(): void
    {
        Storage::fake('local');

        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->post('/api/v1/declarations', $this->payload($agency, [
            'foto_dni' => $this->fotoDni(),
        ]), ['Accept' => 'application/json'])->assertCreated();

        $declaration = Declaration::query()->with('items')->firstOrFail();
        $this->assertNotNull($declaration->foto_dni_path);

        $pdf = app(DeclarationPdfBuilder::class)->build($declaration);

        [$width, $height] = $this->mediaBox($pdf);
        $this->assertGreaterThan($height, $width, 'El documento con foto debe ser A4 apaisado.');
    }

    /**
     * Un archivo que desaparece del disco no puede impedir emitir el
     * documento: se emite vertical, que es el formato base.
     */
    public function test_si_la_foto_ya_no_esta_el_documento_vuelve_a_vertical(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->postJson('/api/v1/declarations', $this->payload($agency))->assertCreated();

        $declaration = Declaration::query()->with('items')->firstOrFail();
        $declaration->forceFill(['foto_dni_path' => 'declarations/999/no-existe.jpg'])->save();

        $pdf = app(DeclarationPdfBuilder::class)->build($declaration);

        [$width, $height] = $this->mediaBox($pdf);
        $this->assertGreaterThan($width, $height);
    }

    public function test_rechaza_un_adjunto_que_no_sea_imagen(): void
    {
        $agency = $this->agencia();
        Sanctum::actingAs($this->usuario(), ['declaraciones:gestionar']);

        $this->post('/api/v1/declarations', $this->payload($agency, [
            'foto_dni' => UploadedFile::fake()->create('documento.pdf', 20, 'application/pdf'),
        ]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto_dni');
    }

    /**
     * Primer MediaBox del PDF, en puntos.
     *
     * @return array{float, float}
     */
    private function mediaBox(string $pdf): array
    {
        $this->assertSame('%PDF', substr($pdf, 0, 4));
        $this->assertSame(1, preg_match('/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $matches));

        return [(float) $matches[1], (float) $matches[2]];
    }
}
