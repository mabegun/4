<?php
/**
 * ProKB - База данных
 */

class DB {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die('Ошибка подключения к БД: ' . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function queryOne($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $id, $data) {
        $sets = [];
        foreach (array_keys($data) as $key) {
            $sets[] = "$key = ?";
        }
        
        $sql = "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = ?";
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function delete($table, $id) {
        $sql = "DELETE FROM $table WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    public function execute($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}

// Функции-обёртки
function dbQuery($sql, $params = []) {
    return DB::getInstance()->query($sql, $params);
}

function dbQueryOne($sql, $params = []) {
    return DB::getInstance()->queryOne($sql, $params);
}

function dbInsert($table, $data) {
    return DB::getInstance()->insert($table, $data);
}

function dbUpdate($table, $id, $data) {
    return DB::getInstance()->update($table, $id, $data);
}

function dbDelete($table, $id) {
    return DB::getInstance()->delete($table, $id);
}

// ============================================
// ПОЛЬЗОВАТЕЛИ
// ============================================

function getUserById($id) {
    return dbQueryOne("SELECT * FROM users WHERE id = ?", [$id]);
}

function getUserByEmail($email) {
    return dbQueryOne("SELECT * FROM users WHERE email = ?", [$email]);
}

function getUserByToken($token) {
    return dbQueryOne("SELECT * FROM users WHERE token = ?", [$token]);
}

function getCurrentUserId() {
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? '';
    
    if (empty($token)) {
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    }
    
    $token = str_replace('Bearer ', '', $token);
    
    if (empty($token)) {
        return null;
    }
    
    $user = getUserByToken($token);
    return $user ? $user['id'] : null;
}

// ============================================
// УТИЛИТЫ
// ============================================

function sendSuccess($data) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function sendError($message, $code = 400) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
}
