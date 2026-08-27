<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Services\DniNameSearch\DniNameSearchAvailability;

/**
 * Módulos opcionales que el servidor tiene encendidos, para los clientes
 * oficiales (Platform, Mobile, Desktop).
 *
 * Un permiso RBAC dice lo que la PERSONA puede hacer; esto dice lo que la
 * INSTALACIÓN tiene disponible. Son cosas distintas y el cliente necesita las
 * dos: con `dni-records.view` pero la búsqueda por nombres apagada, Desktop
 * pintaría la opción y el endpoint respondería 503. Un cliente no debería
 * descubrir la configuración del servidor a base de errores.
 *
 * La clave es estable y en snake_case porque forma parte del contrato público
 * de /auth/login y /auth/me. Añadir un módulo opcional es añadir una línea a
 * all(): los tres clientes lo reciben sin más cambios en el servidor.
 */
final class ClientFeatures
{
    public function __construct(private readonly DniNameSearchAvailability $dniNameSearch) {}

    /**
     * @return array<string, bool>
     */
    public function all(): array
    {
        return [
            'dni_name_search' => $this->dniNameSearch->enabled(),
        ];
    }
}
