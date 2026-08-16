<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispositivos móviles registrados para recibir notificaciones push.
 *
 * Lo que se guarda es deliberadamente poco: quién, en qué plataforma, con qué
 * token de entrega y cuándo se le vio por última vez. Nada de IMEI, número de
 * serie, Android ID ni huella de hardware — un identificador que sobrevive a la
 * desinstalación convierte esta tabla en un rastreador, y para enviar un push no
 * hace falta.
 *
 * El token viaja cifrado (cast `encrypted`, con APP_KEY) y a su lado va un
 * SHA-256 que es el que lleva el índice único: permite reconocer el mismo
 * dispositivo entre instalaciones sin poder leer el token desde la base.
 *
 * Ese índice es único a nivel global, no por usuario, y esa es la decisión
 * importante: un token FCM identifica una instalación concreta de la app, así
 * que sólo puede pertenecer a una persona a la vez. Si alguien inicia sesión en
 * un teléfono donde ya había otra cuenta, el registro cambia de dueño y el
 * usuario anterior deja de recibir avisos ahí — incluso si su logout nunca
 * llegó a completarse por falta de red.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('platform', 20);

            // Cifrado en reposo; su longitud crece respecto al original.
            $table->text('push_token');

            // SHA-256 en hexadecimal del token: identidad estable y buscable
            // sin exponer el valor.
            $table->string('push_token_hash', 64)->unique();

            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            // El envío parte siempre de "los dispositivos de este usuario".
            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
