<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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
        $this->isolateLocalDisk();
        config(['app.testing' => true, 'app.env' => 'testing']);
        Http::preventStrayRequests();
    }

    /**
     * La misma idea que guardTestDatabase, aplicada al disco.
     *
     * La base de datos estaba protegida; el almacenamiento no. Los tests que
     * ejercitan endpoints reales —declaraciones, backups RUC, subidas
     * multiparte— escribían de verdad en `storage/app/private`, y cada
     * ejecucion de la suite dejaba alli documentos y volcados con
     * identificadores de la base de pruebas. Se detecto al encontrar quince
     * directorios de PDFs huerfanos en produccion.
     *
     * Falsear el disco aqui, para toda la suite, hace que eso sea imposible por
     * construccion en vez de depender de que cada test se acuerde. Los tests que
     * ya llaman a Storage::fake por su cuenta siguen funcionando: la segunda
     * llamada simplemente vuelve a vaciar el disco falso.
     */
    private function isolateLocalDisk(): void
    {
        Storage::fake('local');
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
