<?php
// QR Code generation API
require_once '../includes/db.php';

$productId = intval($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    // Generate payment URL (placeholder - will integrate with Taiwan payment gateways)
    $paymentData = [
        'product_id' => $productId,
        'name' => $product['name'],
        'price' => $product['price'],
        'merchant' => 'AI Salesbot',
        'timestamp' => time()
    ];
    
    $paymentUrl = "https://pay.ai-salesbot.tw/checkout?" . http_build_query($paymentData);
    $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($paymentUrl);
    
    header('Location: ' . $qrUrl);
} else {
    http_response_code(404);
    echo 'Product not found';
}