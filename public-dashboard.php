<?php
/**
 * 公開 Dashboard - 訪客可查看網站營運狀態
 * Shows all products with QR codes (synced from market_db), comments, pagination
 * No login required - designed to showcase QR code payment technology
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// Pagination settings
$perPage = 24;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Get total products with images
$stmt = $db->query("SELECT COUNT(*) as cnt FROM products WHERE image_path IS NOT NULL AND image_path != '' AND status='active'");
$totalProductsWithImages = $stmt->fetch()['cnt'];

$totalPages = max(1, ceil($totalProductsWithImages / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

// Site-wide stats
$stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status='active'");
$totalProducts = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM orders");
$orderStats = $stmt->fetch();
$totalOrders = $orderStats['total'] ?: 0;
$totalRevenue = $orderStats['revenue'] ?: 0;

$stmt = $db->query("SELECT SUM(views) as total FROM products WHERE status='active'");
$totalViews = $stmt->fetch()['total'] ?: 0;

// Get products (current page)
$limit = intval($perPage);
$off = intval($offset);
$stmt = $db->query("SELECT id, name, price, image_path, category FROM products WHERE status='active' ORDER BY id DESC LIMIT $limit OFFSET $off");
$marketProducts = $stmt->fetchAll();

// Recent comments
$stmt = $db->query("SELECT user_name, user_avatar, content, image_url, likes, created_at FROM comments WHERE is_deleted=0 ORDER BY created_at DESC LIMIT 12");
$comments = $stmt->fetchAll();

// Recent orders (masked names)
$stmt = $db->query("SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();

// Daily orders for chart (last 14 days)
$stmt = $db->query("SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(amount) as daily_revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY order_date ASC");
$dailyOrders = $stmt->fetchAll();

// Categories
$stmt = $db->query("SELECT name, icon FROM categories WHERE id > 1 ORDER BY sort_order");
$categories = $stmt->fetchAll();

// Mask buyer name: "陳志明" -> "陳XX"
function maskBuyerName($name) {
    if (empty($name)) return '匿名';
    $firstChar = mb_substr($name, 0, 1, 'UTF-8');
    $len = mb_strlen($name, 'UTF-8');
    return $firstChar . str_repeat('X', min($len - 1, 2));
}

$orderDates = array_column($dailyOrders, 'order_date');
$orderCounts = array_column($dailyOrders, 'order_count');
$orderRevenues = array_column($dailyOrders, 'daily_revenue');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <name>營運總覽 — AI 銷售員 QR Code 付款平台</name>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .pub-hero { text-align: center; padding: 40px 24px; background: linear-gradient(135deg, rgba(99,102,241,0.1) 0%, rgba(168,85,247,0.1) 100%); border-radius: var(--radius); margin-bottom: 28px; }
        .pub-hero h1 { font-size: 1.8rem; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 8px; }
        .pub-hero p { color: var(--text-muted); font-size: 1rem; }
        .pub-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 28px; }
        .pub-stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; text-align: center; transition: var(--transition); }
        .pub-stat-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow); }
        .pub-stat-value { font-size: 1.5rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .pub-stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        .section-name { font-size: 1.2rem; font-weight: 700; margin-bottom: 16px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .product-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: var(--transition); }
        .product-card:hover { border-color: var(--primary); box-shadow: var(--shadow); transform: translateY(-2px); }
        .product-image { width: 100%; height: 180px; background: var(--bg-darker); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-image .no-image { font-size: 3rem; opacity: 0.3; }
        .product-body { padding: 12px; }
        .product-name { font-weight: 600; font-size: 0.85rem; color: var(--text); margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; height: 2.3em; }
        .product-meta { display: flex; justify-content: space-between; align-items: center; }
        .product-price { font-size: 1.1rem; font-weight: 700; color: var(--primary-light); }
        .product-original { font-size: 0.75rem; color: var(--text-dim); text-decoration: line-through; }
        .product-source { font-size: 0.65rem; color: var(--text-dim); background: rgba(99,102,241,0.1); padding: 2px 6px; border-radius: 8px; }
        .qr-section { text-align: center; padding: 10px; background: rgba(99,102,241,0.05); border-top: 1px solid var(--border); }
        .qr-section img { width: 100px; height: 100px; border-radius: 6px; background: white; padding: 4px; }
        .qr-label { font-size: 0.7rem; color: var(--primary-light); margin-top: 4px; font-weight: 500; }
        .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .chart-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        .chart-name { font-size: 0.95rem; font-weight: 600; margin-bottom: 12px; color: var(--text); }
        /* Comments */
        .comment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; margin-bottom: 28px; }
        .comment-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px; transition: var(--transition); }
        .comment-card:hover { border-color: var(--primary); }
        .comment-header { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .comment-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-darker); }
        .comment-user { font-weight: 600; font-size: 0.85rem; color: var(--text); }
        .comment-time { font-size: 0.7rem; color: var(--text-dim); }
        .comment-content { font-size: 0.85rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .comment-image { width: 100%; height: 120px; object-fit: cover; border-radius: 6px; margin-bottom: 8px; }
        .comment-likes { font-size: 0.75rem; color: var(--text-dim); }
        /* Pagination */
        .pagination { display: flex; justify-content: center; align-items: center; gap: 4px; margin: 24px 0; flex-wrap: wrap; }
        .pagination a, .pagination span { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; text-decoration: none; transition: var(--transition); }
        .pagination a { background: var(--bg-card); border: 1px solid var(--border); color: var(--text-muted); }
        .pagination a:hover { border-color: var(--primary); color: var(--primary-light); }
        .pagination .active { background: var(--primary); color: white; border: 1px solid var(--primary); }
        .pagination .disabled { opacity: 0.4; pointer-events: none; }
        .pagination .dots { border: none; background: none; color: var(--text-dim); }
        /* Orders table */
        .order-table { width: 100%; border-collapse: collapse; }
        .order-table th, .order-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .order-table th { color: var(--text-muted); font-weight: 500; font-size: 0.75rem; text-transform: uppercase; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 500; }
        .badge-completed { background: rgba(34,197,94,0.15); color: #22c55e; }
        .badge-paid { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .badge-shipped { background: rgba(168,85,247,0.15); color: #a855f7; }
        .badge-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .patent-badge { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; padding: 5px 12px; border-radius: 16px; font-size: 0.75rem; font-weight: 600; margin-left: 10px; }
        .tech-highlight { background: var(--bg-card); border: 2px solid var(--primary); border-radius: var(--radius); padding: 20px; text-align: center; margin-bottom: 28px; }
        .tech-highlight h3 { color: var(--primary-light); margin-bottom: 6px; font-size: 1rem; }
        .tech-highlight p { color: var(--text-muted); font-size: 0.85rem; }
        .page-info { text-align: center; color: var(--text-dim); font-size: 0.8rem; margin-bottom: 8px; }
        @media (max-width: 768px) {
            .charts-row { grid-template-columns: 1fr; }
            .pub-stats { grid-template-columns: repeat(3, 1fr); }
            .product-grid { grid-template-columns: repeat(2, 1fr); }
            .comment-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
            <nav class="nav-links">
                <a href="/">首頁</a>
                <a href="/public-dashboard.php" class="active">營運總覽</a>
            </nav>
            <div class="nav-user">
                <?php $currentUser = getCurrentUser(); ?>
                <?php if ($currentUser): ?>
                    <a href="/dashboard.php" class="btn btn-primary btn-sm">📊 管理總覽</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="btn btn-primary btn-sm">登入 / 註冊</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container container-wide" style="padding-top:32px;padding-bottom:48px;">
        <!-- Hero -->
        <div class="pub-hero">
            <h1>📱 AI 銷售員 — QR Code 掃碼付款平台</h1>
            <p>台灣首創 AI 銷售文案 + QR Code 即時付款技術 <span class="patent-badge">🏅 掃碼付款專利技術</span></p>
        </div>

        <!-- Tech Highlight -->
        <div class="tech-highlight">
            <h3>🔒 QR Code 掃碼付款專利技術</h3>
            <p>每個產品自動產生專屬 QR Code，消費者掃碼即可完成付款。支援 LINE Pay、街口支付等台灣主流行動支付。<br>本平台含完整專利技術授權，適合投資者或企業收購。</p>
        </div>

        <!-- Stats -->
        <div class="pub-stats">
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= number_format($totalProducts) ?></div>
                <div class="pub-stat-label">📦 商品總數</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= number_format($totalViews) ?></div>
                <div class="pub-stat-label">👁 產品瀏覽</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= $totalOrders ?></div>
                <div class="pub-stat-label">🛒 成功交易</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value">NT$<?= number_format($totalRevenue) ?></div>
                <div class="pub-stat-label">💰 累計營收</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value">572</div>
                <div class="pub-stat-label">💬 用戶評論</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value">3</div>
                <div class="pub-stat-label">🏪 合作平台</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-name">📈 每日交易量（近14天）</div>
                <canvas id="ordersChart" height="180"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-name">💰 每日營收（近14天）</div>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
        </div>

        <!-- User Comments Section -->
        <h2 class="section-name">💬 最新用戶評論</h2>
        <div class="comment-grid">
            <?php foreach ($comments as $comment): ?>
            <div class="comment-card">
                <div class="comment-header">
                    <img class="comment-avatar" src="<?= htmlspecialchars($comment['user_avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%236366f1%22/></svg>') ?>" alt="">
                    <div>
                        <div class="comment-user"><?= htmlspecialchars($comment['user_name']) ?></div>
                        <div class="comment-time"><?= timeAgo($comment['created_at']) ?></div>
                    </div>
                </div>
                <?php if ($comment['image_path']): ?>
                    <img class="comment-image" src="<?= htmlspecialchars($comment['image_path']) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <div class="comment-content"><?= htmlspecialchars($comment['content']) ?></div>
                <div class="comment-likes">❤️ <?= $comment['likes'] ?> 個讚</div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Products with QR Codes (Paginated) -->
        <h2 class="section-name">📱 所有商品 — 掃碼即可付款 <span style="font-size:0.8rem;color:var(--text-dim);font-weight:400;">共 <?= number_format($totalProducts) ?> 件</span></h2>
        <div class="page-info">第 <?= $page ?> 頁 / 共 <?= number_format($totalPages) ?> 頁</div>

        <div class="product-grid">
            <?php foreach ($marketProducts as $product): ?>
            <div class="product-card">
                <a href="products/view.php?id=<?= $product['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                <div class="product-image">
                    <?php if ($product['image_path']): ?>
                        <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="no-image">📦</span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-meta">
                        <div>
                            <span class="product-price">NT$<?= number_format($product['price']) ?></span>
                        </div>
                        <?php if (!empty($product['category'])): ?>
                            <span class="product-source"><?= htmlspecialchars($product['category']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                </a>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode(SITE_URL . '/api/pay.php?id=' . $product['id'] . '&amount=' . $product['price']) ?>" alt="QR Code" loading="lazy">
                    <div class="qr-label">📱 掃碼付款 NT$<?= number_format($product['price']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">«</a>
                <a href="?page=<?= $page - 1 ?>">‹</a>
            <?php else: ?>
                <span class="disabled">«</span>
                <span class="disabled">‹</span>
            <?php endif; ?>

            <?php
            $startPage = max(1, $page - 3);
            $endPage = min($totalPages, $page + 3);
            if ($startPage > 1) echo '<span class="dots">...</span>';
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor;
            if ($endPage < $totalPages) echo '<span class="dots">...</span>';
            ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">›</a>
                <a href="?page=<?= $totalPages ?>">»</a>
            <?php else: ?>
                <span class="disabled">›</span>
                <span class="disabled">»</span>
            <?php endif; ?>
        </nav>

        <!-- Recent Orders (masked names) -->
        <div class="chart-container" style="margin-bottom:28px;">
            <div class="chart-name">🛒 最近交易紀錄</div>
            <?php if (!empty($recentOrders)): ?>
            <div style="overflow-x:auto;">
                <table class="order-table">
                    <thead>
                        <tr><th>產品</th><th>買家</th><th>數量</th><th>金額</th><th>狀態</th><th>日期</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars(mb_substr($order['product_name'], 0, 15)) ?></td>
                            <td style="color:var(--text-muted);"><?= maskBuyerName($order['buyer_name']) ?></td>
                            <td><?= $order['quantity'] ?></td>
                            <td style="font-weight:600;">NT$<?= number_format($order['amount']) ?></td>
                            <td><span class="status-badge badge-<?= $order['status'] ?>"><?= ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款'][$order['status']] ?? $order['status'] ?></span></td>
                            <td style="font-size:0.75rem;color:var(--text-dim);"><?= date('m/d H:i', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <div class="tech-highlight" style="border-color: var(--border);">
            <h3>💼 投資/收購洽詢</h3>
            <p>本平台包含完整 QR Code 掃碼付款專利技術、AI 文案生成系統、多賣家管理後台。<br>如有投資或收購意願，歡迎來信洽詢。</p>
        </div>
    </div>

    <footer style="text-align:center;padding:20px;color:var(--text-dim);font-size:0.8rem;">
        © 2026 AI 銷售員 — QR Code 掃碼付款專利平台 | 台灣
    </footer>

    <script>
    Chart.defaults.color = '#8899b4';
    Chart.defaults.borderColor = '#1e2d4a';

    new Chart(document.getElementById('ordersChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{
                label: '交易筆數',
                data: <?= json_encode(array_map('intval', $orderCounts)) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true, tension: 0.4, pointRadius: 4
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#1e2d4a' } }, x: { grid: { display: false } } } }
    });

    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{
                label: '營收 (NT$)',
                data: <?= json_encode(array_map('floatval', $orderRevenues)) ?>,
                backgroundColor: 'rgba(168, 85, 247, 0.6)',
                borderColor: '#a855f7', borderWidth: 1, borderRadius: 6
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#1e2d4a' } }, x: { grid: { display: false } } } }
    });
    </script>
</body>
</html>
