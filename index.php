<?php
/**
 * 新的首頁 - 整合公開營運數據與賣家功能
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();
$user = getCurrentUser();

// --- 數據獲取（共用 market_db）---
$perPage = 24;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status='active'");
$totalProducts = $stmt->fetch()['total'];

$totalPages = max(1, ceil($totalProducts / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $db->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM orders");
$orderStats = $stmt->fetch();
$totalOrders = $orderStats['total'] ?: 0;
$totalRevenue = $orderStats['revenue'] ?: 0;

$stmt = $db->query("SELECT SUM(views) as total FROM products WHERE status='active'");
$totalViews = $stmt->fetch()['total'] ?: 0;

$limit = intval($perPage);
$off = intval($offset);
$stmt = $db->query("SELECT id, name, price, image_path, category FROM products WHERE status='active' ORDER BY id DESC LIMIT $limit OFFSET $off");
$marketProducts = $stmt->fetchAll();

$stmt = $db->query("SELECT user_name, user_avatar, content, image_url, likes, created_at FROM comments WHERE is_deleted=0 ORDER BY created_at DESC LIMIT 12");
$comments = $stmt->fetchAll();

$stmt = $db->query("SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC LIMIT 10");
$recentOrders = $stmt->fetchAll();

$stmt = $db->query("SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(amount) as daily_revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY order_date ASC");
$dailyOrders = $stmt->fetchAll();

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
    <name>AI 銷售員 — 台灣賣家專屬行銷平台</name>
    <meta name="description" content="台灣賣家專屬！一鍵上傳產品，AI 自動產生銷售文案、產品照片、QR Code 銷售碼，還能投放廣告到各大平台。">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🤖</text></svg>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        /* 整合樣式 */
        .hero { text-align: center; padding: 60px 24px; background: linear-gradient(135deg, rgba(99,102,241,0.1) 0%, rgba(168,85,247,0.1) 100%); border-radius: var(--radius); margin-bottom: 40px; }
        .hero h2 { font-size: 2.2rem; margin-bottom: 16px; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .hero p { font-size: 1.1rem; color: var(--text-muted); max-width: 700px; margin: 0 auto 30px; }
        
        .pub-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 28px; }
        .pub-stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 16px; text-align: center; transition: var(--transition); }
        .pub-stat-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow); }
        .pub-stat-value { font-size: 1.5rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .pub-stat-label { font-size: 0.75rem; color: var(--text-muted); margin-top: 4px; }
        
        .section-name { font-size: 1.4rem; font-weight: 700; margin: 40px 0 20px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .product-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; transition: var(--transition); }
        .product-card:hover { border-color: var(--primary); box-shadow: var(--shadow); transform: translateY(-2px); }
        .product-image { width: 100%; height: 180px; background: var(--bg-darker); display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .product-image img { width: 100%; height: 100%; object-fit: cover; }
        .product-body { padding: 12px; }
        .product-name { font-weight: 600; font-size: 0.85rem; color: var(--text); margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; height: 2.3em; }
        .product-price { font-size: 1.1rem; font-weight: 700; color: var(--primary-light); }
        .qr-section { text-align: center; padding: 10px; background: rgba(99,102,241,0.05); border-top: 1px solid var(--border); }
        .qr-section img { width: 100px; height: 100px; border-radius: 6px; background: white; padding: 4px; }
        .qr-label { font-size: 0.7rem; color: var(--primary-light); margin-top: 4px; font-weight: 500; }
        
        .charts-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
        .chart-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 20px; }
        
        .order-table { width: 100%; border-collapse: collapse; }
        .order-table th, .order-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 0.7rem; font-weight: 500; }
        .badge-completed { background: rgba(34,197,94,0.15); color: #22c55e; }
        .badge-paid { background: rgba(59,130,246,0.15); color: #3b82f6; }
        
        .patent-badge { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, #6366f1, #a855f7); color: white; padding: 5px 12px; border-radius: 16px; font-size: 0.75rem; font-weight: 600; }
        .tech-highlight { background: var(--bg-card); border: 2px solid var(--primary); border-radius: var(--radius); padding: 20px; text-align: center; margin-bottom: 28px; }
        
        .pagination { display: flex; justify-content: center; align-items: center; gap: 4px; margin: 24px 0; }
        .pagination a, .pagination span { min-width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 8px; border: 1px solid var(--border); text-decoration: none; color: var(--text-muted); }
        .pagination .active { background: var(--primary); color: white; border-color: var(--primary); }
        
        @media (max-width: 768px) {
            .charts-row { grid-template-columns: 1fr; }
            .pub-stats { grid-template-columns: repeat(3, 1fr); }
            .product-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
            <nav class="nav-links">
                <a href="/" class="active">首頁</a>
                <a href="products/list.php">產品列表</a>
                <a href="products/add.php">上傳產品</a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="admin-dashboard.php">管理後台</a>
                <?php endif; ?>
            </nav>
            <div class="nav-user">
                <?php if ($user): ?>
                <div class="user-dropdown">
                    <div style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <img src="<?= $user['avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%236366f1%22/><text x=%2250%22 y=%2265%22 font-size=%2245%22 text-anchor=%22middle%22 fill=%22white%22>👤</text></svg>' ?>" class="nav-avatar" alt="">
                        <span style="font-size:0.9rem;"><?= htmlspecialchars($user['store_name'] ?: $user['email']) ?></span>
                    </div>
                    <div class="user-dropdown-menu">
                        <a href="products/list.php">📦 我的產品</a>
                        <a href="products/add.php">➕ 上傳產品</a>
                        <a href="dashboard.php">📊 數據總覽</a>
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="divider"></div>
                        <a href="admin-dashboard.php">⚙️ 管理後台</a>
                        <?php endif; ?>
                        <div class="divider"></div>
                        <a href="auth/logout.php">🚪 登出</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="auth/login.php" class="btn btn-primary btn-sm">登入 / 註冊</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Hero (原 index.php 功能) -->
    <section class="hero">
        <h2>賣家只需上傳產品<br>剩下的 AI 幫你搞定</h2>
        <p>上傳產品照片與描述，AI 自動產生銷售文案、QR Code 銷售碼，一鍵複製、下載、投放廣告。🚀</p>
        <div style="display:flex; gap:16px; justify-content:center;">
            <a href="products/add.php" class="btn btn-primary btn-lg">🚀 開始免費使用</a>
            <a href="products/list.php" class="btn btn-secondary btn-lg">📦 產品列表</a>
        </div>
    </section>

    <div class="container container-wide">
        <!-- Tech Highlight -->
        <div class="tech-highlight">
            <h3>🔒 QR Code 掃碼付款專利技術</h3>
            <p>每個產品自動產生專屬 QR Code，消費者掃碼即可完成付款。支援 LINE Pay、街口支付等台灣主流行動支付。<br>本平台含完整專利技術授權，適合投資者或企業收購。</p>
        </div>

        <!-- Stats (原 public-dashboard.php) -->
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

        <!-- Product Grid -->
        <h2 class="section-name">🛍 熱門商品展示</h2>
        <div class="product-grid">
            <?php foreach ($marketProducts as $p): ?>
            <div class="product-card">
                <a href="products/view.php?id=<?= $p['id'] ?>" style="text-decoration:none;color:inherit;display:block;">
                <div class="product-image">
                    <?php if (!empty($p['image_path'])): ?>
                        <img src="<?= htmlspecialchars($p['image_path'] ?? '') ?>" alt="<?= htmlspecialchars($p['name'] ?? '') ?>" loading="lazy">
                    <?php else: ?>
                        <span style="font-size:2rem;">📦</span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= htmlspecialchars($p['name'] ?? '') ?></div>
                    <div class="product-price">NT$<?= number_format($p['price']) ?></div>
                </div>
                </a>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode(SITE_URL . '/api/pay.php?id=' . $p['id'] . '&amount=' . $p['price']) ?>" alt="QR Code" loading="lazy">
                    <div class="qr-label">📱 掃碼付款 NT$<?= number_format($p['price']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <nav class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>">‹</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?page=<?= $i ?>" class="<?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>">›</a>
            <?php endif; ?>
        </nav>

        <!-- Recent Orders -->
        <h2 class="section-name">🛒 最近成交紀錄</h2>
        <div class="chart-container" style="margin-bottom:40px;">
            <table class="order-table">
                <thead>
                    <tr><th>產品</th><th>買家</th><th>金額</th><th>狀態</th><th>日期</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?= htmlspecialchars(mb_substr($order['product_name'], 0, 15)) ?></td>
                        <td><?= maskBuyerName($order['buyer_name']) ?></td>
                        <td style="font-weight:600;">NT$<?= number_format($order['amount']) ?></td>
                        <td><span class="status-badge badge-<?= $order['status'] ?>"><?= ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成'][$order['status']] ?? $order['status'] ?></span></td>
                        <td style="color:var(--text-dim);"><?= date('m/d H:i', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> AI 銷售員 — 台灣賣家專屬行銷平台</p>
    </footer>

    <script>
    const chartOptions = { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: '#1e2d4a' } }, x: { grid: { display: false } } } };
    new Chart(document.getElementById('ordersChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', $orderCounts)) ?>, borderColor: '#6366f1', backgroundColor: 'rgba(99, 102, 241, 0.1)', fill: true, tension: 0.4 }]
        },
        options: chartOptions
    });
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{ data: <?= json_encode(array_map('floatval', $orderRevenues)) ?>, backgroundColor: 'rgba(168, 85, 247, 0.6)', borderRadius: 6 }]
        },
        options: chartOptions
    });
    </script>
</body>
</html>
