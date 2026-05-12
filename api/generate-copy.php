<?php
/**
 * AI Copy Generation API (AJAX endpoint)
 */
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => '請先登入']);
    exit;
}

$name = sanitize($_POST['name'] ?? '');
$description = sanitize($_POST['description'] ?? '');
$price = floatval($_POST['price'] ?? 0);

if (!$name || !$description) {
    echo json_encode(['success' => false, 'error' => '請填寫產品名稱和描述']);
    exit;
}

$copy = generateAICopy($name, $description, $price);
echo json_encode(['success' => true, 'copy' => $copy]);