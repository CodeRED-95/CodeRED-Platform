<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MobileDevice;
use Illuminate\Console\Command;
use Kreait\Firebase\Contract\Messaging;
use Throwable;

/**
 * Comprueba que el envío de push puede funcionar, sin enviar nada a nadie.
 *
 * Existe porque cuando un push no llega hay cuatro sospechosos —la credencial,
 * el interruptor, la conectividad con Google y la ausencia de dispositivos— y
 * mirarlos de uno en uno a mano es lento. No imprime el token de ningún
 * dispositivo ni ningún dato de la credencial.
 */
class PushDiagnoseCommand extends Command
{
    protected $signature = 'push:diagnose';

    protected $description = 'Diagnostica la configuración de notificaciones push (no envía nada)';

    public function handle(): int
    {
        $ok = true;

        $this->line('Interruptor  : '.(config('push.fcm.enabled') ? 'activado' : 'DESACTIVADO'));

        $ruta = (string) config('firebase.projects.app.credentials');
        $this->line('Credencial   : '.($ruta !== '' ? $ruta : '(no configurada)'));

        if ($ruta === '' || ! is_file($ruta)) {
            $this->error('  No existe el archivo de credenciales. Ver docs/FIREBASE_SETUP.md');
            $ok = false;
        } elseif (is_writable($ruta)) {
            $this->warn('  El archivo es escribible: debería montarse de sólo lectura.');
        } else {
            $this->info('  Presente y de sólo lectura.');
        }

        $this->line('Dispositivos : '.MobileDevice::query()->count().' registrados');

        // Una llamada real a Google que no envía nada: valida la credencial y
        // la salida a Internet de una sola vez.
        try {
            app(Messaging::class)->validateRegistrationTokens(['token-de-prueba-invalido']);
            $this->info('Firebase     : credencial aceptada y API alcanzable.');
        } catch (Throwable $exception) {
            $this->error('Firebase     : '.class_basename($exception).' — '.mb_substr($exception->getMessage(), 0, 160));
            $ok = false;
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
