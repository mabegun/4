<?php
/**
 * ProKB - Конфигурация
 * Проектное Бюро - Система управления проектами
 */

// Настройки базы данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'prokb');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Настройки приложения
define('APP_NAME', 'Проектное Бюро');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost'); // Изменить на ваш домен

// Секретный ключ для сессий
define('SECRET_KEY', 'prokb-secret-key-change-this');

// Настройки PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);

// Часовой пояс
date_default_timezone_set('Europe/Moscow');

/**
 * Подключение к базе данных
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            sendError('Ошибка подключения к БД: ' . $e->getMessage(), 500);
        }
    }
    
    return $pdo;
}

/**
 * Отправка JSON ответа с ошибкой
 */
function sendError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Отправка JSON ответа с успехом
 */
function sendSuccess($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Проверка авторизации
 */
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        sendError('Не авторизован', 401);
    }
    
    return $_SESSION['user_id'];
}

/**
 * Получение текущего пользователя
 */
function getCurrentUser() {
    $userId = requireAuth();
    $db = getDB();
    
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        sendError('Пользователь не найден', 404);
    }
    
    unset($user['password']);
    return $user;
}

/**
 * Очистка входных данных
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Генерация случайной строки
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Хеширование пароля
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Проверка пароля
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * CORS заголовки
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit;
    }
}

/**
 * Получение JSON из тела запроса
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data ?: [];
}
