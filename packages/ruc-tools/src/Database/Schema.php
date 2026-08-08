<?php

namespace RucTool\Database;

class Schema
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Crea el esquema exacto de CodeRED-Platform (tablas ruc_records, ubigeos,
     * ruc_staging) para que los backups sean restaurables 1:1 en producción.
     */
    public function create(): void
    {
        $pdo = $this->connection->getPdo();

        $pdo->exec('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ruc_records (
                id BIGSERIAL PRIMARY KEY,
                ruc VARCHAR(11) NOT NULL UNIQUE,
                razon_social TEXT NOT NULL,
                estado VARCHAR(60),
                condicion VARCHAR(60),
                ubigeo VARCHAR(12),
                tipo_via VARCHAR(30),
                nombre_via TEXT,
                codigo_zona VARCHAR(30),
                tipo_zona VARCHAR(60),
                numero VARCHAR(30),
                interior VARCHAR(30),
                lote VARCHAR(30),
                departamento_direccion VARCHAR(30),
                manzana VARCHAR(30),
                kilometro VARCHAR(30),
                departamento VARCHAR(120),
                provincia VARCHAR(120),
                distrito VARCHAR(120),
                direccion TEXT,
                created_at TIMESTAMP(0),
                updated_at TIMESTAMP(0)
            )
        ');

        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_estado_index ON ruc_records(estado)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_condicion_index ON ruc_records(condicion)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_ubigeo_index ON ruc_records(ubigeo)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_departamento_index ON ruc_records(departamento)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_provincia_index ON ruc_records(provincia)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_records_distrito_index ON ruc_records(distrito)');
        $pdo->exec('
            CREATE INDEX IF NOT EXISTS ruc_records_razon_social_trgm_index
            ON ruc_records USING gin (razon_social gin_trgm_ops)
        ');

        // Tabla ubigeos: catálogo de departamento/provincia/distrito por código (fuente Alanube)
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ubigeos (
                id BIGSERIAL PRIMARY KEY,
                codigo CHAR(6) NOT NULL UNIQUE,
                departamento VARCHAR(120) NOT NULL,
                provincia VARCHAR(120) NOT NULL,
                distrito VARCHAR(120) NOT NULL,
                capital VARCHAR(120),
                source VARCHAR(30) DEFAULT \'alanube\',
                source_url VARCHAR(500) DEFAULT \'https://developer.alanube.co/v1.0-PER/docs/ubigeo-table\',
                source_updated_at TIMESTAMP(0),
                created_at TIMESTAMP(0),
                updated_at TIMESTAMP(0)
            )
        ');

        // Tabla ruc_staging: carga vía COPY antes del merge a ruc_records (igual a producción)
        $pdo->exec('
            CREATE UNLOGGED TABLE IF NOT EXISTS ruc_staging (
                import_id BIGINT NOT NULL,
                row_number BIGINT NOT NULL,
                ruc VARCHAR(11) NOT NULL,
                razon_social TEXT NOT NULL,
                estado VARCHAR(60),
                condicion VARCHAR(60),
                ubigeo VARCHAR(6),
                departamento VARCHAR(120),
                provincia VARCHAR(120),
                distrito VARCHAR(120),
                direccion TEXT,
                tipo_via VARCHAR(30),
                nombre_via TEXT,
                codigo_zona VARCHAR(30),
                tipo_zona VARCHAR(60),
                numero VARCHAR(30),
                interior VARCHAR(30),
                lote VARCHAR(30),
                departamento_direccion VARCHAR(30),
                manzana VARCHAR(30),
                kilometro VARCHAR(30),
                created_at TIMESTAMP(0),
                updated_at TIMESTAMP(0),
                PRIMARY KEY (import_id, row_number)
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_staging_import_ruc_index ON ruc_staging(import_id, ruc)');

        // Metadatos locales de cada corrida de importación (equivalente reducido a ruc_imports)
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ruc_tool_imports (
                id BIGSERIAL PRIMARY KEY,
                filename VARCHAR(255) NOT NULL,
                total_lines BIGINT DEFAULT 0,
                valid_lines BIGINT DEFAULT 0,
                error_lines BIGINT DEFAULT 0,
                duplicate_lines BIGINT DEFAULT 0,
                inserted_records BIGINT DEFAULT 0,
                unknown_ubigeo_lines BIGINT DEFAULT 0,
                status VARCHAR(20) DEFAULT \'pending\',
                started_at TIMESTAMP(0),
                completed_at TIMESTAMP(0),
                duration_seconds INTEGER,
                lines_per_second NUMERIC(12,2),
                created_at TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ruc_tool_import_errors (
                id BIGSERIAL PRIMARY KEY,
                import_id BIGINT REFERENCES ruc_tool_imports(id) ON DELETE CASCADE,
                line_number BIGINT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                line_preview TEXT,
                created_at TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP
            )
        ');
        $pdo->exec('CREATE INDEX IF NOT EXISTS ruc_tool_import_errors_import_id_index ON ruc_tool_import_errors(import_id)');

        // Metadatos de backups pg_dump generados localmente (equivalente reducido a ruc_backups)
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS ruc_tool_backups (
                id BIGSERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                total_records BIGINT,
                file_size_bytes BIGINT,
                storage_path VARCHAR(500) NOT NULL,
                checksum_sha256 VARCHAR(64),
                duration_seconds INTEGER,
                status VARCHAR(20) DEFAULT \'completed\',
                created_at TIMESTAMP(0) DEFAULT CURRENT_TIMESTAMP
            )
        ');
    }
}
