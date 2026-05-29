<?php
/**
 * AI Salesbot - Database & Core Configuration
 */

require_once __DIR__ . '/functions.php';

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'ai_salesbot');
define('DB_USER', 'ai_salesbot');
define('DB_PASS', 'Ricky520!');

// Site
define('SITE_URL', 'https://sale.aiceox.com');
define('SITE_NAME', 'AI 銷售員');
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('UPLOAD_URL', SITE_URL . '/uploads');

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 days

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            die('網站暫時無法連線，請稍後再試。');
        }
    }
    return $pdo;
}

// Auto-load session if token exists
function getCurrentUser() {
    $token = $_COOKIE['auth_token'] ?? '';
    if (!$token) return null;
    
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT u.* FROM users u 
            JOIN sessions s ON u.id = s.user_id 
            WHERE s.token = ? AND s.expires_at > NOW() AND u.status = 'active'");
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}

function requireLogin() {
    $user = getCurrentUser();
    if (!$user) {
        header('Location: ' . SITE_URL . '/auth/login.php');
        exit;
    }
    return $user;
}

function requireAdmin() {
    $user = requireLogin();
    if ($user['role'] !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('權限不足');
    }
    return $user;
}

function logActivity($userId, $action, $details = null) {
    try {
        $db = getDB();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $details, $ip]);
    } catch (Exception $e) {}
}

function csrfToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_POST['csrf_token'] ?? '';
    return $token && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}