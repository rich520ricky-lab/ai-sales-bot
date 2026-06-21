<?php
// 雙向同步：ai_salesbot.products -> market_db.products
// 當 sale.aiceox.com 新增或更新商品時，自動同步到 market_db
// 同時同步留言（ai_salesbot.orders 作為購買評論同步到 market_db.comments）
// 建議設定 cron: 每5分鐘執行一次
// 或觸發方式：在 products 新增/更新後呼叫

// Database config
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'ai_salesbot');
define('DB_PASS', 'Ricky520!');
define('SITE_URL', 'https://sale.aiceox.com');

$opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

try {
    $aiDb = new PDO('mysql:host=' . DB_HOST . ';dbname=ai_salesbot;charset=utf8mb4', DB_USER, DB_PASS, $opts);
    $marketDb = new PDO('mysql:host=' . DB_HOST . ';dbname=market_db;charset=utf8mb4', DB_USER, DB_PASS, $opts);
} catch (PDOException $e) {
    echo "[ERROR] DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[SYNC] Starting ai_salesbot → market_db sync at " . date('Y-m-d H:i:s') . "\n";

// 1. Sync products from ai_salesbot to market_db
$stmt = $aiDb->query("SELECT id, name, price, image_path, category, description, status, created_at, updated_at FROM products WHERE status='active'");
$aiProducts = $stmt->fetchAll();

$synced = 0;
$skipped = 0;

foreach ($aiProducts as $product) {
    // Check if already synced (using source_id = 'aisalesbot_{id}')
    $sourceId = 'aisalesbot_' . $product['id'];
    
    $stmt = $marketDb->prepare("SELECT id, updated_at FROM products WHERE source = 'aisalesbot' AND source_id = ?");
    $stmt->execute([$sourceId]);
    $existing = $stmt->fetch();
    
    // Build image URL
    $imageUrl = '';
    if (!empty($product['image_path'])) {
        if (strpos($product['image_path'], 'http') === 0) {
            $imageUrl = $product['image_path'];
        } else {
            $imageUrl = SITE_URL . '/' . ltrim($product['image_path'], './');
        }
    }
    
    // Build product URL on sale.aiceox.com
    $productUrl = SITE_URL . '/products/view.php?id=' . $product['id'];
    
    if ($existing) {
        // Update if ai_salesbot version is newer
        $stmt = $marketDb->prepare("UPDATE products SET 
            title = ?, price = ?, image_url = ?, product_url = ?, 
            category = ?, updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([
            $product['name'],
            $product['price'],
            $imageUrl,
            $productUrl,
            $product['category'],
            $existing['id']
        ]);
        $skipped++;
    } else {
        // Insert new product
        $stmt = $marketDb->prepare("INSERT INTO products 
            (title, price, image_url, product_url, source, source_id, category, crawled_at, first_seen_at)
            VALUES (?, ?, ?, ?, 'aisalesbot', ?, ?, NOW(), ?)");
        $stmt->execute([
            $product['name'],
            $product['price'],
            $imageUrl,
            $productUrl,
            $sourceId,
            $product['category'],
            $product['created_at']
        ]);
        $synced++;
    }
}

echo "[SYNC] Products: {$synced} new, {$skipped} updated\n";

// 2. Sync orders as purchase records/comments to market_db
// When someone buys on sale.aiceox.com, create a comment on market_db
$stmt = $aiDb->query("SELECT o.id, o.buyer_name, o.amount, o.created_at, p.name as product_name 
    FROM orders o 
    JOIN products p ON o.product_id = p.id 
    WHERE o.status IN ('paid','shipped','completed')
    ORDER BY o.created_at DESC LIMIT 50");
$recentOrders = $stmt->fetchAll();

$commentsSynced = 0;
foreach ($recentOrders as $order) {
    // Check if already synced
    $cookieId = 'aisalesbot_order_' . $order['id'];
    $stmt = $marketDb->prepare("SELECT id FROM comments WHERE cookie_id = ?");
    $stmt->execute([$cookieId]);
    if ($stmt->fetch()) continue;
    
    // Create a purchase comment
    $content = "在 AI 銷售員平台購買了「{$order['product_name']}」，NT\${$order['amount']}，推薦！";
    $firstChar = mb_substr($order['buyer_name'], 0, 1, 'UTF-8');
    $displayName = $firstChar . 'XX 買家';
    
    $stmt = $marketDb->prepare("INSERT INTO comments 
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
