<?php

namespace Tests\Feature;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Support\RucPadronParser;
use Database\Seeders\UbigeoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class RucImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_streams_valid_rows_and_keeps_duplicates_unchanged(): void
    {
        Storage::fake('local');
        config()->set('ruc.import_chunk_size', 2);
        Storage::disk('local')->put('private/imports/ruc/sample.txt', "RUC|RAZON_SOCIAL\n20123456789|ORIGINAL\n20123456789|REPETIDA\n123|INVALIDA\n");
        $import = RucImport::query()->create([
            'uuid' => fake()->uuid(), 'original_filename' => 'sample.txt', 'stored_filename' => 'sample.txt', 'disk' => 'local',
            'path' => 'private/imports/ruc/sample.txt', 'file_size' => 100, 'file_hash' => str_repeat('a', 64),
            'status' => RucImportStatus::Queued, 'encoding' => 'UTF-8', 'delimiter' => '|',
        ]);

        (new ProcessRucImportJob($import->id))->handle(app(RucPadronParser::class));

        $this->assertDatabaseHas('ruc_records', ['ruc' => '20123456789', 'razon_social' => 'ORIGINAL']);
        $this->assertDatabaseMissing('ruc_records', ['razon_social' => 'REPETIDA']);
        $this->assertDatabaseHas('ruc_import_errors', ['line_number' => 4, 'reason' => 'RUC inválido.']);
        $this->assertSame(RucImportStatus::CompletedWithErrors, $import->fresh()->status);
        $this->assertSame(100.0, (float) $import->fresh()->progress_percentage);
    }

    public function test_forty_row_fixture_rewinds_stream_reaches_one_hundred_percent_and_is_idempotent(): void
    {
        Storage::fake('local');
        config()->set('ruc.import_progress_interval', 10);
        $contents = file_get_contents(base_path('tests/Fixtures/ruc/padron-40.txt'));
        $this->assertIsString($contents);
        Storage::disk('local')->put('ruc-imports/padron-40.txt', $contents);

        $first = $this->import('ruc-imports/padron-40.txt', 'first-40.txt');
        (new ProcessRucImportJob($first->id))->handle(app(RucPadronParser::class));

        $first->refresh();
        $this->assertSame(RucImportStatus::CompletedWithErrors, $first->status);
        $this->assertSame(40, $first->total_rows);
        $this->assertSame(40, $first->processed_rows);
        $this->assertSame(38, $first->inserted_rows);
        $this->assertSame(1, $first->ignored_rows);
        $this->assertSame(1, $first->invalid_rows);
        $this->assertSame('100.00', $first->progress_percentage);
        $this->assertNotNull($first->last_heartbeat_at);
        Storage::disk('local')->assertExists($first->path);

        $second = $this->import('ruc-imports/padron-40.txt', 'second-40.txt');
        (new ProcessRucImportJob($second->id))->handle(app(RucPadronParser::class));

        $second->refresh();
        $this->assertSame(40, $second->processed_rows);
        $this->assertSame(0, $second->inserted_rows);
        $this->assertSame(39, $second->ignored_rows);
        $this->assertSame(1, $second->invalid_rows);
        $this->assertSame('100.00', $second->progress_percentage);
        $this->assertDatabaseCount('ruc_records', 38);
    }

    public function test_missing_private_file_marks_import_failed_immediately(): void
    {
        Storage::fake('local');
        $import = $this->import('ruc-imports/missing.txt', 'missing.txt');

        try {
            (new ProcessRucImportJob($import->id))->handle(app(RucPadronParser::class));
            $this->fail('El Job debía fallar porque el archivo no existe.');
        } catch (RuntimeException) {
            $import->refresh();
            $this->assertSame(RucImportStatus::Failed, $import->status);
            $this->assertNotNull($import->failed_at);
            $this->assertStringContainsString('No existe el archivo', (string) $import->error_message);
        }
    }

    public function test_real_sunat_file_builds_address_and_resolves_ubigeo_without_per_row_queries(): void
    {
        Storage::fake('local');
        $this->seed(UbigeoSeeder::class);
        $contents = file_get_contents(base_path('tests/Fixtures/ruc/sunat-real-format.txt'));
        $this->assertIsString($contents);
        Storage::disk('local')->put('ruc-imports/sunat-real.txt', $contents);
        $import = $this->import('ruc-imports/sunat-real.txt', 'sunat-real.txt');

        (new ProcessRucImportJob($import->id))->handle(app(RucPadronParser::class));

        $this->assertDatabaseHas('ruc_records', [
            'ruc' => '20512805478',
            'ubigeo' => '150140',
            'departamento' => 'LIMA',
            'provincia' => 'LIMA',
            'distrito' => 'SANTIAGO DE SURCO',
            'direccion' => 'BL. 51 URB. LA CRUCETA 51 402',
        ]);
        $import->refresh();
        $this->assertSame(RucImportStatus::Completed, $import->status);
        $this->assertSame(2, $import->processed_rows);
        $this->assertSame(1, $import->resolved_ubigeo_rows);
        $this->assertSame(0, $import->unknown_ubigeo_rows);
    }

    private function import(string $path, string $filename): RucImport
    {
        return RucImport::query()->create([
            'uuid' => fake()->uuid(),
            'original_filename' => $filename,
            'stored_filename' => basename($path),
            'disk' => 'local',
            'path' => $path,
            'file_size' => Storage::disk('local')->exists($path) ? Storage::disk('local')->size($path) : 0,
            'file_hash' => hash('sha256', $filename),
            'status' => RucImportStatus::Queued,
            'encoding' => 'UTF-8',
            'delimiter' => '|',
            'queue_name' => 'ruc-imports',
        ]);
    }
}
