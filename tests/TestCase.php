<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Segunda barrera: comprueba la base de datos REALMENTE resuelta.
     *
     * tests/bootstrap.php descarta el caso conocido (configuración cacheada),
     * pero el objetivo puede desviarse por otras vías —un `.env` editado, una
     * conexión distinta— y `RefreshDatabase` borra la base contra la que
     * apunte. Aquí se compara el valor efectivo con el que pide phpunit.xml y
     * se aborta si no coinciden, antes de que ninguna migración se ejecute.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->guardTestDatabase();
    }

    private function guardTestDatabase(): void
    {
        $connection = (string) config('database.default');
        $resolved = (string) config("database.connections.{$connection}.database");
        $expected = (string) ($_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');

        if ($expected === '' || $resolved === $expected) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Las pruebas apuntan a la base de datos "%s" cuando phpunit.xml exige "%s". '
            .'Se aborta para no destruir datos reales. Revisa si la configuración está cacheada '
            .'(php artisan config:clear) o si el entorno define DB_DATABASE.',
            $resolved,
            $expected
        ));
    }
}
