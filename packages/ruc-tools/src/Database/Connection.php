<?php

namespace RucTool\Database;

use PDO;
use PDOException;

class Connection
{
    private static ?PDO $instance = null;
    private array $config;
    private PDO $pdo;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->connect();
    }

    private function connect(): void
    {
        try {
            $dsn = $this->buildPostgresDsn();

            $this->pdo = new PDO(
                $dsn,
                $this->config['username'] ?? null,
                $this->config['password'] ?? null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    private function buildPostgresDsn(): string
    {
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 5432;
        $database = $this->config['database'] ?? 'ruc_db';
        return "pgsql:host=$host;port=$port;dbname=$database";
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function query(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $columnList = implode(',', $columns);

        $sql = "INSERT INTO $table ($columnList) VALUES ($placeholders)";
        $this->query($sql, $values);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $setClause = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));

        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        $values = array_merge(array_values($data), array_values($where));

        $stmt = $this->query($sql, $values);
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $whereClause";

        $stmt = $this->query($sql, array_values($where));
        return $stmt->rowCount();
    }

    public function select(string $table, array $where = [], array $orderBy = []): array
    {
        $sql = "SELECT * FROM $table";

        if (!empty($where)) {
            $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
            $sql .= " WHERE $whereClause";
        }

        if (!empty($orderBy)) {
            $orderClauses = array_map(fn($k, $v) => "$k $v", array_keys($orderBy), $orderBy);
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        $stmt = $this->query($sql, array_values($where));
        return $stmt->fetchAll();
    }

    public function count(string $table, array $where = []): int
    {
        $sql = "SELECT COUNT(*) as count FROM $table";

        if (!empty($where)) {
            $whereClause = implode(' AND ', array_map(fn($k) => "$k = ?", array_keys($where)));
            $sql .= " WHERE $whereClause";
        }

        $stmt = $this->query($sql, array_values($where));
        $result = $stmt->fetch();
        return $result['count'] ?? 0;
    }

    public function beginTransaction(): void
    {
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }
    }

    public function commit(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function exec(string $sql): int
    {
        return $this->pdo->exec($sql);
    }

    public static function getInstance(array $config): PDO
    {
        if (self::$instance === null) {
            $conn = new self($config);
            self::$instance = $conn->getPdo();
        }
        return self::$instance;
    }
}
