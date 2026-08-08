# Development Guide - RUC Tool v2.2.0

Guía para desarrolladores que deseen extender o modificar RUC Tool.

## Setup de desarrollo

### Requisitos
- PHP 8.3+
- Composer
- Git
- SQLite3 (o PostgreSQL/MySQL)

### Instalación
```bash
git clone <repo-url>
cd ruc-tool
composer install
chmod +x bin/ruc-tool
php bin/ruc-tool init
```

## Estructura del proyecto

```
src/
├── Commands/              # Comandos CLI
├── Services/              # Lógica de negocio
├── Models/                # Modelos de datos
├── Database/              # Conexión y esquema
└── Helpers/               # Utilidades (Logger, Config, etc.)

config/                     # Configuración (database, validation)
templates/                  # Plantillas
tests/                      # Tests unitarios
examples/                   # Ejemplos de uso
```

## Agregar un nuevo comando

### 1. Crear la clase del comando

Crear `src/Commands/MyCommand.php`:

```php
<?php

namespace RucTool\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MyCommand extends Command
{
    protected static $defaultName = 'my-command';
    protected static $defaultDescription = 'Description of what this does';

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::REQUIRED, 'Description')
            ->addOption('option1', 'o', InputOption::VALUE_REQUIRED, 'Description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $arg1 = $input->getArgument('arg1');
        $option1 = $input->getOption('option1');

        try {
            $io->title('My Command');
            $io->info('Processing...');

            // Your logic here

            $io->success('Completed!');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

### 2. Registrar el comando

En `bin/ruc-tool`, agregar:

```php
use RucTool\Commands\MyCommand;

$application->add(new MyCommand());
```

### 3. Probar el comando

```bash
php bin/ruc-tool help my-command
php bin/ruc-tool my-command --help
php bin/ruc-tool my-command arg1_value
```

## Crear un nuevo servicio

### Ejemplo: Servicio de reportes

Crear `src/Services/ReportService.php`:

```php
<?php

namespace RucTool\Services;

use RucTool\Database\Connection;

class ReportService
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function generateReport(): array
    {
        // Implementar lógica
        return [];
    }
}
```

Usarlo en un comando:

```php
$connection = new Connection($config['database']);
$reportService = new ReportService($connection);
$report = $reportService->generateReport();
```

## Escribir tests

### Ejecutar tests
```bash
composer test
```

### Crear un test

Crear `tests/MyServiceTest.php`:

```php
<?php

namespace RucTool\Tests;

use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomething()
    {
        $this->assertTrue(true);
    }
}
```

## Base de datos

### Ejecutar migraciones

Las migraciones se ejecutan automáticamente en `init`:

```bash
php bin/ruc-tool init
```

### Agregar nueva tabla

En `src/Database/Schema.php`, método `createSqlite()`:

```php
$pdo->exec('
    CREATE TABLE IF NOT EXISTS my_table (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
');
```

Repetir para `createPostgres()` y `createMysql()`.

### Consultas

```php
$connection = new Connection($config);

// Select
$records = $connection->select('table_name', ['estado' => 'ACTIVO']);

// Insert
$id = $connection->insert('table_name', ['name' => 'value']);

// Update
$connection->update('table_name', ['name' => 'new'], ['id' => 1]);

// Delete
$connection->delete('table_name', ['id' => 1]);

// Count
$count = $connection->count('table_name');

// Raw query
$stmt = $connection->query('SELECT * FROM table WHERE id = ?', [1]);
$result = $stmt->fetch();
```

## Configuración

### Leer configuración

```php
$configManager = new ConfigManager();
$workers = $configManager->get('workers', 4);
$host = $configManager->get('database.host', 'localhost');
```

### Guardar configuración

```php
$configManager = new ConfigManager();
$configManager->set('workers', 8);
$configManager->set('database.host', 'remote.server');
$configManager->save();
```

## Logging

```php
use RucTool\Helpers\Logger;

Logger::info("Something happened");
Logger::warning("Be careful");
Logger::error("Error occurred");
Logger::debug("Debug info");
```

Los logs se guardan en `~/.ruc-tool/logs/ruc-tool.log`.

## Validación

### Crear validador personalizado

Extender `ValidationService`:

```php
class CustomValidation extends ValidationService
{
    public function validateCustom(RucRecord $record): bool
    {
        // Tu lógica aquí
        return true;
    }
}
```

## Performance

### Streaming de archivos grandes

La clase `ImportService` maneja automáticamente streaming:

```php
$importService = new ImportService($connection, $validationService);
$importService->importFile('large_file.csv', function ($stats) {
    echo "Processed: {$stats['total']}\n";
});
```

### Batch processing

Configurable en config:

```php
$configManager->set('batch_size', 2000);
```

## Style guide

### Nombres
- Clases: `PascalCase`
- Métodos: `camelCase`
- Constantes: `UPPER_SNAKE_CASE`
- Variables: `camelCase` o `$snake_case` para propiedades

### Ejemplo
```php
class MyService
{
    private const MAX_RETRIES = 3;
    private int $timeout;

    public function processRecord(Record $record): bool
    {
        $isValid = $this->validateRecord($record);
        return $isValid;
    }

    private function validateRecord(Record $record): bool
    {
        // Implementation
    }
}
```

## Troubleshooting de desarrollo

### Error: "Class not found"
Ejecutar:
```bash
composer dump-autoload
```

### Tests failing
```bash
composer test -- --verbose
```

### Database locked
SQLite en WAL mode puede tener conflictos. Para reset:
```bash
rm ~/.ruc-tool/ruc.db*
php bin/ruc-tool init
```

## Release

### Versioning
Usar [Semantic Versioning](https://semver.org):
- MAJOR: Cambios incompatibles
- MINOR: Nuevas características compatibles
- PATCH: Bug fixes

### Actualizar versión

1. En `composer.json`: actualizar `"version": "2.2.0"`
2. En `bin/ruc-tool`: actualizar en `Application()`
3. Crear tag en git: `git tag v2.2.0`

### Compilar a PHAR (opcional)

```bash
composer install --no-dev
box compile  # Requiere box configurado
```

## Contribuir

1. Fork el repositorio
2. Crear rama: `git checkout -b feature/my-feature`
3. Hacer cambios
4. Ejecutar tests: `composer test`
5. Commit: `git commit -am 'Add my feature'`
6. Push: `git push origin feature/my-feature`
7. Crear Pull Request

---

¿Preguntas? Revisar código existente en `src/` para ejemplos.
