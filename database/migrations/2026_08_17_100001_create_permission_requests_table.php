<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de acceso a un módulo móvil.
 *
 * Deliberadamente separada de `api_token_requests`: aquélla pide un token para
 * una integración, ésta pide un permiso para una persona. Comparten forma pero
 * no significado, y mezclarlas obligaría a que cada consulta distinguiera de
 * cuál de las dos cosas habla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /** Permiso RBAC solicitado. Sólo los de la lista blanca. */
            $table->string('permission', 100);

            $table->string('status', 20)->default('pending');

            /** Motivo que escribe quien solicita. Opcional. */
            $table->text('reason')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            // La bandeja del administrador filtra por estado y ordena por
            // antigüedad; el móvil pregunta "lo mío, de este permiso".
            $table->index(['status', 'requested_at']);
            $table->index(['user_id', 'permission', 'status']);
        });

        // Una sola solicitud pendiente por usuario y permiso. Es la regla de
        // negocio -no se duplican solicitudes- y aquí se vuelve imposible de
        // saltar, incluso con dos peticiones simultáneas.
        DB::statement(
            'CREATE UNIQUE INDEX permission_requests_one_pending
             ON permission_requests (user_id, permission)
             WHERE status = \'pending\''
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_requests');
    }
};
