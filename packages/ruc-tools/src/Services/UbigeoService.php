<?php

namespace RucTool\Services;

use RucTool\Database\Connection;

/**
 * Siembra y resuelve el catálogo de ubigeos (departamento/provincia/distrito),
 * equivalente al UbigeoSeeder + tabla `ubigeos` de CodeRED-Platform.
 */
class UbigeoService
{
    private Connection $connection;

    /** @var array<string, array{departamento: string, provincia: string, distrito: string}>|null */
    private ?array $lookup = null;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Carga el catálogo de ubigeos desde el JSON de Alanube (idempotente).
     */
    public function seed(string $jsonPath): int
    {
        if (! file_exists($jsonPath)) {
            throw new \Exception("Archivo de ubigeos no encontrado: $jsonPath");
        }

        $rows = json_decode(file_get_contents($jsonPath), true);
        if (! is_array($rows)) {
            throw new \Exception("El archivo de ubigeos no contiene JSON válido: $jsonPath");
        }

        $pdo = $this->connection->getPdo();
        $now = date('Y-m-d H:i:s');

        $sql = '
            INSERT INTO ubigeos (codigo, departamento, provincia, distrito, capital, source, source_url, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, \'alanube\', \'https://developer.alanube.co/v1.0-PER/docs/ubigeo-table\', ?, ?)
            ON CONFLICT (codigo) DO UPDATE SET
                departamento = EXCLUDED.departamento,
                provincia = EXCLUDED.provincia,
                distrito = EXCLUDED.distrito,
                capital = EXCLUDED.capital,
                updated_at = EXCLUDED.updated_at
        ';
        $stmt = $pdo->prepare($sql);

        $pdo->beginTransaction();
        $count = 0;
        foreach ($rows as $row) {
            $stmt->execute([
                $row['codigo'],
                $row['departamento'],
                $row['provincia'],
                $row['distrito'],
                $row['capital'] ?? null,
                $now,
                $now,
            ]);
            $count++;
        }
        $pdo->commit();

        $this->lookup = null;

        return $count;
    }

    /**
     * Carga el catálogo completo en memoria para resolución O(1) durante el import,
     * igual que Ubigeo::query()->get()->keyBy('codigo') en RucRebuildAddressesCommand.
     */
    private function loadLookup(): void
    {
        if ($this->lookup !== null) {
            return;
        }

        $rows = $this->connection->query('SELECT codigo, departamento, provincia, distrito FROM ubigeos')->fetchAll();

        $this->lookup = [];
        foreach ($rows as $row) {
            $this->lookup[$row['codigo']] = [
                'departamento' => $row['departamento'],
                'provincia' => $row['provincia'],
                'distrito' => $row['distrito'],
            ];
        }
    }

    /**
     * Resuelve departamento/provincia/distrito a partir del código de ubigeo.
     * Retorna null en los 3 campos si el ubigeo es desconocido.
     */
    public function resolve(?string $ubigeo): array
    {
        if ($ubigeo === null) {
            return ['departamento' => null, 'provincia' => null, 'distrito' => null];
        }

        $this->loadLookup();

        return $this->lookup[$ubigeo] ?? ['departamento' => null, 'provincia' => null, 'distrito' => null];
    }

    public function count(): int
    {
        return $this->connection->count('ubigeos');
    }
}
