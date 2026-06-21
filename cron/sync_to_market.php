<?php
// 同步購買紀錄為用戶評論
// 當 sale.market.com.tw 有新訂單時，自動產生購買評論
// 資料庫已共用 market_db，不需跨庫同步商品

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'Uu88uu88!');
define('SITE_URL', 'https://sale.market.com.tw');

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=market_db;charset=utf8mb4', DB_USER, DB_PASS, $opts);
} catch (PDOException $e) {
    echo "[ERROR] DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[SYNC] Starting order-to-comment sync at " . date('Y-m-d H:i:s') . "\n";

// Sync orders as purchase comments
$stmt = $db->query("SELECT o.id, o.buyer_name, o.amount, o.created_at, p.name as product_name 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.status IN ('paid','shipped','completed')
    ORDER BY o.created_at DESC LIMIT 50");
$recentOrders = $stmt->fetchAll();

$commentsSynced = 0;
foreach ($recentOrders as $order) {
    $cookieId = 'order_comment_' . $order['id'];
    $stmt = $db->prepare("SELECT id FROM comments WHERE cookie_id = ?");
    $stmt->execute([$cookieId]);
    if ($stmt->fetch()) continue;
    
    $content = "在 AI 銷售員平台購買了「{$order['product_name']}」，NT\${$order['amount']}，推薦！";
    $firstChar = mb_substr($order['buyer_name'], 0, 1, 'UTF-8');
    $displayName = $firstChar . 'XX 買家';
    
    $stmt = $db->prepare("INSERT INTO comments 
        (user_name, content, cookie_id, created_at, likes)
        VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $displayName,
        $content,
        $cookieId,
        $order['created_at'],
        rand(1, 8)
    ]);
    $commentsSynced++;
}

echo "[SYNC] Comments: {$commentsSynced} new purchase reviews synced\n";
echo "[SYNC] Complete at " . date('Y-m-d H:i:s') . "\n";
