<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Concede `profile:read` a los tokens de integración que ya existen.
 *
 * Ningún tipo de token la incluía, así que todo token emitido por el flujo de
 * solicitudes recibía un 403 al validarse contra /me y resultaba inservible
 * para cualquier cliente que compruebe la credencial antes de usarla —CodeRED
 * Desktop, entre otros—, por mucho que sus permisos funcionales fueran los
 * correctos.
 *
 * Es una ampliación deliberada y acotada: /me devuelve el propietario del token
 * y su propia lista de abilities, nada más. No abre ningún dato de negocio ni
 * altera lo que cada token ya podía consultar.
 *
 * No toca los tokens de sesión: esos autorizan por el RBAC de la persona, no
 * por abilities declaradas.
 */
return new class extends Migration
{
    private const ABILITY = 'profile:read';

    public function up(): void
    {
        $this->cambiar(añadir: true);
    }

    public function down(): void
    {
        $this->cambiar(añadir: false);
    }

    private function cambiar(bool $añadir): void
    {
        DB::table('personal_access_tokens')
            ->where('kind', 'integration')
            ->orderBy('id')
            // Por lotes: la tabla puede crecer y no hace falta cargarla entera.
            ->chunkById(200, function ($tokens) use ($añadir): void {
                foreach ($tokens as $token) {
                    $abilities = json_decode((string) $token->abilities, true);

                    if (! is_array($abilities)) {
                        continue;
                    }

                    // Un token con comodín ya la tiene de hecho; tocarlo sería
                    // reducir su alcance sin querer.
                    if (in_array('*', $abilities, true)) {
                        continue;
                    }

                    $tiene = in_array(self::ABILITY, $abilities, true);

                    if ($añadir === $tiene) {
                        continue;
                    }

                    $nuevas = $añadir
                        ? [...$abilities, self::ABILITY]
                        : array_values(array_filter($abilities, static fn (mixed $a): bool => $a !== self::ABILITY));

                    DB::table('personal_access_tokens')
                        ->where('id', $token->id)
                        ->update(['abilities' => json_encode(array_values(array_unique($nuevas)))]);
                }
            });
    }
};
