<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AgencyExternalIdentifiersMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_backfills_safe_legacy_values_without_losing_ambiguous_text(): void
    {
        $migration = require database_path('migrations/2026_07_19_055312_add_external_identifiers_to_agencies_table.php');
        $migration->down();

        foreach ([
            [610, '610 - TERRESTRE'],
            [611, '611 - AÉREO'],
            [612, 'IDENTIFICADOR AMBIGUO'],
        ] as [$reference, $sourceText]) {
            DB::table('agencies')->insert([
                'code' => 'SHA-000'.$reference,
                'name' => 'Agencia '.$reference,
                'slug' => 'agencia-'.$reference,
                'department' => 'Lima',
                'province' => 'Lima',
                'district' => 'Lima',
                'address' => 'Dirección',
                'status' => 'active',
                'source' => 'github_gist',
                'source_reference' => (string) $reference,
                'source_text' => $sourceText,
            ]);
        }

        $migration->up();

        $this->assertDatabaseHas('agencies', ['external_id' => 610, 'texto_chosen_terrestre' => '610 - TERRESTRE']);
        $this->assertDatabaseHas('agencies', ['external_id' => 611, 'texto_chosen_aereo' => '611 - AÉREO']);
        $this->assertDatabaseHas('agencies', ['external_id' => 612, 'source_text' => 'IDENTIFICADOR AMBIGUO', 'texto_chosen_terrestre' => null, 'texto_chosen_aereo' => null]);
    }
}
