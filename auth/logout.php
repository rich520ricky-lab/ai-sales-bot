<?php
/**
 * Logout
 */
require_once __DIR__ . '/../includes/db.php';

$token = $_COOKIE['auth_token'] ?? '';
if ($token) {
    try {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM sessions WHERE token = ?");
        $stmt->execute([$token]);
    } catch (Exception $e) {}
    
    setcookie('auth_token', '', time() - 3600, '/', '', false, true);
}

header('Location: /');
exit;