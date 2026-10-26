<?php
// ============================================================
// Ganeshman Singh Academy - Database Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // Change to your MySQL username
define('DB_PASS', '');             // Change to your MySQL password
define('DB_NAME', 'ganeshman_school');
define('DB_CHARSET', 'utf8mb4');

define('SITE_NAME', 'Ganeshman Singh Academy');
define('SITE_LOCATION', 'Jhiljhille, Jhapa, Nepal');
define('BASE_URL', 'http://gms-sms.42web.io/');

// -----------------------------------------------
// Database Connection (Singleton Pattern)
// -----------------------------------------------
class Database {
    private static $instance = null;
    private $connection;
 
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->connection->connect_error) {
            die(json_encode([
                'success' => false,
                'message' => 'Database connection failed: ' . $this->connection->connect_error
            ]));
        }
        $this->connection->set_charset(DB_CHARSET);
    }
 
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
 
    public function getConnection(): mysqli {
        return $this->connection;
    }
 
    // Prepared statement helper - returns statement
    public function prepare(string $sql): mysqli_stmt|false {
        return $this->connection->prepare($sql);
    }
 
    // Execute a query with optional bound parameters
    // Returns mysqli_result or true/false
    public function query(string $sql): mysqli_result|bool {
        return $this->connection->query($sql);
    }
 
    // Fetch all rows as associative array
    public function fetchAll(string $sql, string $types = '', array $params = []): array {
        if (!empty($params)) {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) return [];
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $this->connection->query($sql);
        }
        if (!$result) return [];
        return $result->fetch_all(MYSQLI_ASSOC);
    }
 
    // Fetch single row
    public function fetchOne(string $sql, string $types = '', array $params = []): array|null {
        $rows = $this->fetchAll($sql, $types, $params);
        return !empty($rows) ? $rows[0] : null;
    }
 
    public string $lastError = '';
 
    // Execute INSERT/UPDATE/DELETE with prepared statement
    public function execute(string $sql, string $types = '', array $params = []): bool {
        try {
            $stmt = $this->connection->prepare($sql);
            if (!$stmt) {
                $this->lastError = $this->connection->error;
                return false;
            }
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $result = $stmt->execute();
            if (!$result) {
                $this->lastError = $stmt->error;
            }
            return $result;
        } catch (\mysqli_sql_exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }
 
    // Get last inserted ID
    public function lastInsertId(): int {
        return $this->connection->insert_id;
    }
 
    // Get affected rows
    public function affectedRows(): int {
        return $this->connection->affected_rows;
    }
 
    // Escape string (fallback)
    public function escape(string $str): string {
        return $this->connection->real_escape_string($str);
    }
 
    // Prevent cloning
    private function __clone() {}
}
 
// Global helper to get DB instance
function db(): Database {
    return Database::getInstance();
}