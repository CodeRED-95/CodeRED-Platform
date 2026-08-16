<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla estándar del canal `database` de Laravel Notifications.
 *
 * No se inventa un almacén propio: User ya usa el trait Notifiable, así que con
 * esta tabla el historial, el estado leído/no leído y `markAsRead()` vienen
 * resueltos por el framework. El bus de eventos de plataforma
 * (App\Services\Events) es otra cosa —integración con el agente y n8n, sin
 * destinatario ni lectura— y sigue intacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // El centro de notificaciones pide siempre "las mías, más recientes
            // primero" y "cuántas sin leer": ambos consultan por destinatario.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
