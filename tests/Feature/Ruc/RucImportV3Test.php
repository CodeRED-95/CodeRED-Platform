<?php

namespace Tests\Feature\Ruc;

use App\Models\User;
use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Models\RucImportDuplicate;
use App\Modules\Ruc\Models\RucImportError;
use App\Modules\Ruc\Models\RucImportEvent;
use App\Modules\Ruc\Models\RucRecord;
use App\Modules\Ruc\Services\RucFileStreamReader;
use App\Modules\Ruc\Services\RucLineValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RucImportV3Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $disk = 'local';

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake($this->disk);
    }

    /**
     * Test: Crear archivo válido pequeño
     */
    public function test_import_small_valid_file(): void
    {
        // Crear archivo temporal con datos válidos
        $content = "RUC|Razón Social|Estado|Condición|UBIGEO\n";
        $content .= "20123456789|EMPRESA SAC|ACTIVO|ACTIVO|150131\n";
        $content .= "20987654321|OTRA EMPRESA|ACTIVO|ACTIVO|150131\n";

        $file = UploadedFile::fromBase64(
            base64_encode($content),
            'test.txt',
            'text/plain'
        );

        // Actuar
        $response = $this->actingAs($this->user)->post('/admin/ruc/imports', [
            'file' => $file,
            'merge_strategy' => 'insert',
        ]);

        // Verificar
        $response->assertStatus(202);
        $this->assertDatabaseHas('ruc_imports', [
            'status' => RucImportStatusV3::Pending->value,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test: Procesar archivo con validaciones
     */
    public function test_line_validation_with_errors(): void
    {
        $validator = new RucLineValidator();

        // Línea con RUC inválido
        $result = $validator->validate(
            ['123', 'EMPRESA SAC', 'ACTIVO', 'ACTIVO', '150131'],
            2,
            new \App\Modules\Ruc\Data\ValidationContext()
        );

        $this->assertFalse($result->valid);
        $this->assertTrue($result->hasErrors());
    }

    /**
     * Test: Detectar duplicados en archivo
     */
    public function test_detect_duplicates_in_file(): void
    {
        $validator = new RucLineValidator();
        $context = new \App\Modules\Ruc\Data\ValidationContext();

        // Primera línea
        $result1 = $validator->validate(
            ['20123456789', 'EMPRESA SAC', 'ACTIVO', 'ACTIVO', '150131'],
            2,
            $context
        );
        $this->assertTrue($result1->valid);
        $this->assertFalse($result1->isDuplicate);

        // Segunda línea con mismo RUC
        $result2 = $validator->validate(
            ['20123456789', 'EMPRESA SAC', 'ACTIVO', 'ACTIVO', '150131'],
            3,
            $context
        );
        $this->assertTrue($result2->isDuplicate);
        $this->assertEquals(2, $result2->firstOccurrence);
    }

    /**
     * Test: Stream reader con archivo grande
     */
    public function test_stream_reader_memory_efficient(): void
    {
        // Crear archivo de 1MB
        $content = "RUC|Razón Social|Estado|Condición|UBIGEO\n";
        for ($i = 0; $i < 10000; $i++) {
            $ruc = sprintf("20%09d", $i);
            $content .= "{$ruc}|EMPRESA {$i}|ACTIVO|ACTIVO|150131\n";
        }

        $file = UploadedFile::fromBase64(
            base64_encode($content),
            'large.txt',
            'text/plain'
        );

        $path = $file->store('test', $this->disk);

        $reader = new RucFileStreamReader();
        $handle = $reader->open(Storage::disk($this->disk)->path($path));

        // Leer línea por línea sin cargar todo en memoria
        $lineCount = 0;
        while ($reader->readline($handle) !== null) {
            $lineCount++;
        }

        $reader->close($handle);

        $this->assertGreaterThan(10000, $lineCount);
    }

    /**
     * Test: Evento de progreso registrado
     */
    public function test_import_events_recorded(): void
    {
        $import = RucImport::factory()->create();

        $import->recordEvent('import.started', [
            'file_size' => 1024,
        ], $this->user);

        $this->assertDatabaseHas('ruc_import_events', [
            'ruc_import_id' => $import->id,
            'event_type' => 'import.started',
        ]);

        $event = RucImportEvent::first();
        $this->assertIsArray($event->data);
        $this->assertEquals(1024, $event->data['file_size']);
    }

    /**
     * Test: Cancelación de importación
     */
    public function test_cancel_import(): void
    {
        $import = RucImport::factory()->create([
            'status' => RucImportStatusV3::Processing->value,
        ]);

        $import->requestCancellation($this->user, 'Test cancel');

        $this->assertNotNull($import->fresh()->cancel_requested_at);
        $this->assertDatabaseHas('ruc_import_events', [
            'ruc_import_id' => $import->id,
            'event_type' => 'import.cancelled',
        ]);
    }

    /**
     * Test: Rollback de importación completada
     */
    public function test_rollback_import(): void
    {
        $import = RucImport::factory()->create([
            'status' => RucImportStatusV3::Completed->value,
            'inserted_records' => 100,
            'started_at' => now()->subHour(),
            'finished_at' => now(),
        ]);

        // Crear registros de prueba
        for ($i = 0; $i < 5; $i++) {
            RucRecord::create([
                'ruc' => sprintf("20%09d", $i),
                'razon_social' => "EMPRESA {$i}",
                'created_at' => now()->subMinutes(30),
            ]);
        }

        $rollbackHandler = app(\App\Modules\Ruc\Services\RucRollbackHandler::class);
        $result = $rollbackHandler->rollback($import, $this->user, 'Test rollback');

        $this->assertTrue($result->success);
        $this->assertEquals(RucImportStatusV3::RolledBack->value, $import->fresh()->status);
    }

    /**
     * Test: Autorización de usuarios
     */
    public function test_import_authorization(): void
    {
        $user = User::factory()->create();

        // Sin permiso
        $response = $this->actingAs($user)
            ->get('/admin/ruc/imports');

        // Esperará 403 o redirección
        $this->assertIn($response->status(), [403, 302]);
    }

    /**
     * Test: Validación de archivo
     */
    public function test_upload_file_validation(): void
    {
        // Archivo con extensión incorrecta
        $file = UploadedFile::fake()->create('test.csv', 1024, 'text/csv');

        $response = $this->actingAs($this->user)->post('/admin/ruc/imports', [
            'file' => $file,
        ]);

        $this->assertStatus([422, 302]);
    }

    /**
     * Test: Estadísticas de importación
     */
    public function test_import_statistics(): void
    {
        $import = RucImport::factory()->create([
            'total_lines' => 1000,
            'processed_lines' => 1000,
            'inserted_records' => 950,
            'invalid_rows' => 50,
            'duplicate_records' => 10,
        ]);

        $this->assertEquals(95.0, $import->getProgressPercentage());
    }
}
