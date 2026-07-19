<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_sync_states', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->unsignedBigInteger('minimum_sequence')->default(0);
            $table->timestampTz('updated_at')->nullable();
        });
        DB::table('agency_sync_states')->insert(['id' => 1, 'minimum_sequence' => 0]);

        Schema::create('agency_sync_changes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('agency_internal_id');
            $table->unsignedBigInteger('external_id')->nullable();
            $table->string('code');
            $table->string('operation', 16);
            $table->jsonb('payload')->nullable();
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->timestampTz('changed_at');
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['changed_at', 'id']);
            $table->index(['agency_internal_id', 'id']);
            $table->index(['operation', 'id']);
        });

        DB::table('agencies')->orderBy('id')->chunkById(250, function ($agencies): void {
            $rows = [];
            foreach ($agencies as $agency) {
                $deletedAt = $agency->deleted_at;
                $payload = $deletedAt === null ? [
                    'internal_id' => (int) $agency->id,
                    'id' => $agency->external_id !== null ? (int) $agency->external_id : null,
                    'code' => $agency->code,
                    'agencia' => trim((string) $agency->name),
                    'departamento' => trim((string) $agency->department),
                    'provincia' => trim((string) $agency->province),
                    'distrito' => trim((string) $agency->district),
                    'direccion' => trim((string) $agency->address),
                    'link_mapa' => $agency->map_url,
                    'tamano' => match ($agency->size) {
                        'small' => 'Pequeña',
                        'medium' => 'Mediana',
                        'large' => 'Grande',
                        default => null,
                    },
                    'texto_chosen_terrestre' => $agency->texto_chosen_terrestre,
                    'texto_chosen_aereo' => $agency->texto_chosen_aereo,
                ] : null;

                $rows[] = [
                    'agency_internal_id' => $agency->id,
                    'external_id' => $agency->external_id,
                    'code' => $agency->code,
                    'operation' => $deletedAt === null ? 'upsert' : 'delete',
                    'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'schema_version' => 1,
                    'changed_at' => $deletedAt ?? $agency->updated_at ?? $agency->created_at ?? now(),
                    'created_at' => now(),
                ];
            }

            if ($rows !== []) {
                DB::table('agency_sync_changes')->insert($rows);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_sync_changes');
        Schema::dropIfExists('agency_sync_states');
    }
};
