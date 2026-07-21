<?php

namespace Tests\Feature;

use App\Modules\Ruc\Enums\RucImportStatus;
use App\Modules\Ruc\Jobs\ProcessRucImportJob;
use App\Modules\Ruc\Models\RucImport;
use App\Modules\Ruc\Support\RucPadronParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
}
