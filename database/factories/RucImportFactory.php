<?php

namespace Database\Factories;

use App\Modules\Ruc\Enums\RucImportStatusV3;
use App\Modules\Ruc\Models\RucImport;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<RucImport> */
class RucImportFactory extends Factory
{
    protected $model = RucImport::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'original_filename' => 'padron_ruc.txt',
            'stored_filename' => Str::uuid().'-padron_ruc.txt',
            'disk' => 'local',
            'path' => 'private/ruc/incoming/'.Str::uuid().'-padron_ruc.txt',
            'file_size' => fake()->numberBetween(1024, 1024 * 1024),
            'file_hash' => hash('sha256', (string) Str::uuid()),
            'status' => RucImportStatusV3::Pending->value,
            'encoding' => 'ISO-8859-1',
            'delimiter' => '|',
            'merge_strategy' => 'insert',
        ];
    }
}
