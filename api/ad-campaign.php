<?php
/**
 * Ad Campaign API
 */
require_once __DIR__ . '/../includes/db.php';
$user = requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $platform = sanitize($_POST['platform'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);
    
    // Verify product belongs to user
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$productId, $user['id']]);
    $product = $stmt->fetch();
    
    if ($product && $platform && $budget > 0) {
        $stmt = $db->prepare("INSERT INTO ad_campaigns (product_id, platform, budget, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$productId, $platform, $budget]);
        
        logActivity($user['id'], 'create_campaign', "建立廣告: {$platform} NT\${$budget}");
        
        header('Location: /products/view.php?id=' . $productId . '&campaign_created=1');
        exit;
    }
}

header('Location: /products/list.php');