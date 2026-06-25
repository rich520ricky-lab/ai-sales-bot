<?php
// 雙向同步腳本 (sale.market.com.tw ↔ www.market.com.tw)
// 
// 架構: 兩站共用 market_db，商品資料自動一致
// 本腳本處理:
// 1. 訂單 → 購買評論同步
// 2. 確保 image_path 格式一致
// 3. 確保上傳目錄 symlink 存在

define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'Uu88uu88!');
define('SITE_URL', 'https://sale.market.com.tw');
define('SALE_UPLOADS', '/www/wwwroot/sale.market.com.tw/uploads/products');
define('MARKET_UPLOADS_LINK', '/www/wwwroot/www.market.com.tw/uploads/products');

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=market_db;charset=utf8mb4', DB_USER, DB_PASS, $opts);
} catch (PDOException $e) {
    echo "[ERROR] DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[SYNC] Starting bidirectional sync at " . date('Y-m-d H:i:s') . "\n";

// === 1. Order → Comment sync ===
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

// === 2. Ensure image_path consistency ===
// Fix any legacy paths (e.g., ../uploads/products/xxx.jpg → uploads/products/xxx.jpg)
$stmt = $db->query("SELECT id, image_path FROM products WHERE image_path LIKE '../%'");
$fixedPaths = 0;
while ($row = $stmt->fetch()) {
    $newPath = preg_replace('/^\.\.\//', '', $row['image_path']);
    $db->prepare("UPDATE products SET image_path = ? WHERE id = ?")->execute([$newPath, $row['id']]);
    $fixedPaths++;
}
if ($fixedPaths > 0) {
    echo "[SYNC] Fixed {$fixedPaths} legacy image paths\n";
}

// === 3. Ensure uploads symlink exists ===
if (!is_link(MARKET_UPLOADS_LINK) && is_dir(SALE_UPLOADS)) {
    @symlink(SALE_UPLOADS, MARKET_UPLOADS_LINK);
    echo "[SYNC] Created uploads symlink for www.market.com.tw\n";
}

// === 4. Stats ===
$productCount = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$commentCount = $db->query("SELECT COUNT(*) FROM comments WHERE is_deleted=0")->fetchColumn();
$orderCount = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
echo "[SYNC] Stats: {$productCount} active products, {$commentCount} comments, {$orderCount} orders\n";
echo "[SYNC] Complete at " . date('Y-m-d H:i:s') . "\n";
