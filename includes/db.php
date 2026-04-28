<?php
// includes/db.php — Singleton Pattern for DB Connection

class DatabaseConnection {
    private static ?DatabaseConnection $instance = null;
    private mysqli $conn;

    private string $host   = 'localhost';
    private string $dbname = 'bhms';
    private string $user   = 'root';
    private string $pass   = '';
    private string $charset = 'utf8mb4';

    // Private constructor — prevents direct instantiation
    private function __construct() {
        $this->conn = new mysqli(
            $this->host,
            $this->user,
            $this->pass,
            $this->dbname
        );

        if ($this->conn->connect_error) {
            error_log('DB Connection failed: ' . $this->conn->connect_error);
            die(json_encode(['error' => 'Database connection failed']));
        }

        $this->conn->set_charset($this->charset);
    }

    // Static method — returns the single instance
    public static function getInstance(): DatabaseConnection {
        if (self::$instance === null) {
            self::$instance = new DatabaseConnection();
        }
        return self::$instance;
    }

    // Get the raw mysqli connection
    public function getConnection(): mysqli {
        return $this->conn;
    }

    // Prevent cloning
    private function __clone() {}

    // Helper: safe query with prepared statement
    public function query(string $sql, string $types = '', ...$params): mysqli_result|bool {
        if (empty($params)) {
            return $this->conn->query($sql);
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        return $result ?: true;
    }

    // Helper: fetch all rows
    public function fetchAll(string $sql, string $types = '', ...$params): array {
        $result = $this->query($sql, $types, ...$params);
        if (!$result || $result === true) return [];
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    // Helper: fetch single row
    public function fetchOne(string $sql, string $types = '', ...$params): ?array {
        $result = $this->query($sql, $types, ...$params);
        if (!$result || $result === true) return null;
        return $result->fetch_assoc() ?: null;
    }

    // Helper: insert and return last ID
    public function insert(string $sql, string $types = '', ...$params): int {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return 0;
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    // Helper: execute (UPDATE/DELETE)
    public function execute(string $sql, string $types = '', ...$params): int {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return 0;
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }

    public function lastInsertId(): int {
        return $this->conn->insert_id;
    }

    public function escape(string $str): string {
        return $this->conn->real_escape_string($str);
    }
}

// Convenience alias
function db(): DatabaseConnection {
    return DatabaseConnection::getInstance();
}
