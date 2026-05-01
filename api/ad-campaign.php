<?php
// Ad campaign creation endpoint
require_once '../includes/db.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = intval($_POST['product_id'] ?? 0);
    $platform = sanitize($_POST['platform'] ?? '');
    $budget = floatval($_POST['budget'] ?? 0);

    if ($productId && $platform && $budget > 0) {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO ad_campaigns (product_id, platform, budget, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$productId, $platform, $budget]);
        
        $campaignId = $db->lastInsertId();
        
        // TODO: Integrate with actual ad platforms
        // - Facebook Ads API
        // - Google Ads API
        // - LINE Ads API
        // - TV ad booking
        
        header('Location: ../products/view.php?id=' . $productId . '&campaign=created');
        exit;
    }
}

header('Location: ../products/list.php');