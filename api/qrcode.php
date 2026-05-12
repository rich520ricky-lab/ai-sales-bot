<?php
/**
 * QR Code Generation API
 */
require_once __DIR__ . '/../includes/db.php';

$productId = intval($_GET['id'] ?? 0);
$amount = floatval($_GET['amount'] ?? 0);

$db = getDB();
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if ($product) {
    $paymentUrl = SITE_URL . '/api/pay.php?id=' . $productId . '&amount=' . ($amount ?: $product['price']);
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($paymentUrl);
    header('Location: ' . $qrUrl);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Product not found']);
}