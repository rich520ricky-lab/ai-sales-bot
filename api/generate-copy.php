<?php
// AI Copy Generation API endpoint
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $customPrompt = sanitize($_POST['prompt'] ?? '');

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        // TODO: Integrate real AI API (OpenAI / Claude / Gemini)
        $aiCopy = "🔥 **" . $product['name'] . "** 限時熱賣中！\n\n"
                . "✨ 台灣在地優質商品\n"
                . "💰 特價 NT$ " . number_format($product['price'], 0) . "\n"
                . "📦 數量有限，售完為止\n\n"
                . "👇 掃描 QR Code 立即購買！";

        $update = $db->prepare("UPDATE products SET ai_copy = ? WHERE id = ?");
        $update->execute([$aiCopy, $productId]);

        echo json_encode(['success' => true, 'copy' => $aiCopy]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Product not found']);
    }
}