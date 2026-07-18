<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $duplicates = DB::table('agencies')
            ->select('source', 'source_reference', DB::raw('COUNT(*) as total'))
            ->whereNotNull('source_reference')
            ->groupBy('source', 'source_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates->map(fn ($row) => sprintf('%s + %s (%d)', $row->source, $row->source_reference, $row->total))
                ->implode(', ');

            throw new \RuntimeException('No se puede crear el índice único parcial porque existen duplicados: '.$pairs);
        }

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'agencies_source_reference_unique'
    ) THEN
        ALTER TABLE agencies DROP CONSTRAINT agencies_source_reference_unique;
    ELSIF EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relname = 'agencies_source_reference_unique'
    ) THEN
        DROP INDEX agencies_source_reference_unique;
    END IF;
END $$;
SQL);

        DB::statement('DROP INDEX IF EXISTS agencies_source_source_reference_unique');
        DB::statement('CREATE UNIQUE INDEX agencies_source_source_reference_unique ON agencies (source, source_reference) WHERE source_reference IS NOT NULL');
    }

    public function down(): void
    {
        $duplicates = DB::table('agencies')
            ->select('source_reference', DB::raw('COUNT(*) as total'))
            ->whereNotNull('source_reference')
            ->groupBy('source_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $pairs = $duplicates->map(fn ($row) => sprintf('%s (%d)', $row->source_reference, $row->total))
                ->implode(', ');

            throw new \RuntimeException('No se puede restaurar la restricción única porque existen duplicados: '.$pairs);
        }

        DB::statement('DROP INDEX IF EXISTS agencies_source_source_reference_unique');
        DB::statement('ALTER TABLE agencies ADD CONSTRAINT agencies_source_reference_unique UNIQUE (source, source_reference)');
    }
};
