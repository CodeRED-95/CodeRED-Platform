<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$environment = Dotenv\Dotenv::parse(
    is_file(__DIR__.'/../.env') ? file_get_contents(__DIR__.'/../.env') : ''
);

foreach (['DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
    if (getenv($key) === false && isset($environment[$key])) {
        putenv($key.'='.$environment[$key]);
        $_ENV[$key] = $environment[$key];
        $_SERVER[$key] = $environment[$key];
    }
}

$connection = getenv('DB_CONNECTION') ?: 'pgsql';

if ($connection === 'pgsql') {
    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $database = getenv('DB_DATABASE') ?: 'codered_testing';
    $username = getenv('DB_USERNAME') ?: 'codered';
    $password = getenv('DB_PASSWORD') ?: '';

    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=postgres', $host, $port);
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $statement = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = :database');
        $statement->execute(['database' => $database]);

        if (! $statement->fetchColumn()) {
            $identifier = static fn (string $value): string => '"'.str_replace('"', '""', $value).'"';
            $pdo->exec(sprintf(
                'CREATE DATABASE %s OWNER %s',
                $identifier($database),
                $identifier($username)
            ));
        }
    } catch (Throwable $throwable) {
        fwrite(STDERR, '[tests/bootstrap] No se pudo preparar la base de datos de pruebas: '.$throwable->getMessage().PHP_EOL);
    }
}
