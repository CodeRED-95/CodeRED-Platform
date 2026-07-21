<?php

namespace Database\Seeders;

use App\Modules\Ruc\Models\Ubigeo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class UbigeoSeeder extends Seeder
{
    public function run(): void
    {
        $rows = json_decode(File::get(database_path('data/ubigeos_alanube.json')), true, 512, JSON_THROW_ON_ERROR);
        $now = now();
        $rows = array_map(fn (array $row): array => $row + [
            'source' => 'alanube',
            'source_url' => 'https://developer.alanube.co/v1.0-PER/docs/ubigeo-table',
            'source_updated_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);
        foreach (array_chunk($rows, 500) as $chunk) {
            Ubigeo::query()->upsert($chunk, ['codigo'], ['departamento', 'provincia', 'distrito', 'capital', 'source', 'source_url', 'updated_at']);
        }
    }
}
