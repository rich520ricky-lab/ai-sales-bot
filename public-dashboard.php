<?php
/**
 * 公開 Dashboard - 訪客可查看網站營運狀態
 * Shows all products with QR codes, site stats, recent orders (masked buyer names)
 * No login required - designed to showcase QR code payment technology
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$db = getDB();

// Site-wide stats
$stmt = $db->query("SELECT COUNT(*) as total FROM products WHERE status='active'");
$totalProducts = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(DISTINCT user_id) as total FROM products WHERE status='active'");
$totalSellers = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM orders");
$orderStats = $stmt->fetch();
$totalOrders = $orderStats['total'] ?: 0;
$totalRevenue = $orderStats['revenue'] ?: 0;

$stmt = $db->query("SELECT SUM(views) as total FROM products WHERE status='active'");
$totalViews = $stmt->fetch()['total'] ?: 0;

$stmt = $db->query("SELECT COUNT(*) as total FROM ad_campaigns WHERE status='running'");
$activeCampaigns = $stmt->fetch()['total'];

// All active products
$stmt = $db->query("SELECT p.*, u.store_name FROM products p LEFT JOIN users u ON p.user_id = u.id WHERE p.status='active' ORDER BY p.views DESC");
$products = $stmt->fetchAll();

// Recent orders (masked buyer names)
$stmt = $db->query("SELECT o.*, p.name as product_name, p.price as product_price FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC LIMIT 20");
$recentOrders = $stmt->fetchAll();

// Daily orders for chart (last 14 days)
$stmt = $db->query("SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(amount) as daily_revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY order_date ASC");
$dailyOrders = $stmt->fetchAll();

// Category distribution
$stmt = $db->query("SELECT category, COUNT(*) as count FROM products WHERE status='active' GROUP BY category ORDER BY count DESC");
$categoryData = $stmt->fetchAll();

// Order status distribution
$stmt = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
$orderStatusData = $stmt->fetchAll();

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
    <title>營運總覽 — AI 銷售員 QR Code 付款平台</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .pub-hero {
            text-align: center;
            padding: 48px 24px;
            background: linear-gradient(135deg, rgba(99,102,241,0.1) 0%, rgba(168,85,247,0.1) 100%);
            border-radius: var(--radius);
            margin-bottom: 32px;
        }
        .pub-hero h1 {
            font-size: 2rem;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        .pub-hero p {
            color: var(--text-muted);
            font-size: 1.05rem;
        }
        .pub-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .pub-stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }
        .pub-stat-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        .pub-stat-value {
            font-size: 1.8rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .pub-stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text);
        }
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .product-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            transition: var(--transition);
        }
        .product-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateY(-3px);
        }
        .product-image {
            width: 100%;
            height: 180px;
            background: var(--bg-darker);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .product-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
        }
        .product-image .no-image {
            font-size: 3rem;
            opacity: 0.3;
        }
        .product-body {
            padding: 16px;
        }
        .product-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text);
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-light);
        }
        .product-views {
            font-size: 0.8rem;
            color: var(--text-dim);
        }
        .product-seller {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-bottom: 12px;
        }
        .qr-section {
            text-align: center;
            padding: 12px;
            background: rgba(99,102,241,0.05);
            border-top: 1px solid var(--border);
        }
        .qr-section img {
            width: 140px;
            height: 140px;
            border-radius: 8px;
            background: white;
            padding: 8px;
        }
        .qr-label {
            font-size: 0.75rem;
            color: var(--primary-light);
            margin-top: 8px;
            font-weight: 500;
        }
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        .chart-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
        }
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-table th,
        .order-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }
        .order-table th {
            color: var(--text-muted);
            font-weight: 500;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .order-table tr:hover td {
            background: var(--bg-card-hover);
        }
        .badge-completed { background: rgba(34,197,94,0.15); color: #22c55e; }
        .badge-paid { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .badge-shipped { background: rgba(168,85,247,0.15); color: #a855f7; }
        .badge-pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .badge-cancelled { background: rgba(239,68,68,0.15); color: #ef4444; }
        .badge-refunded { background: rgba(107,114,128,0.15); color: #6b7280; }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        .patent-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 12px;
        }
        .tech-highlight {
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
            margin-bottom: 32px;
        }
        .tech-highlight h3 {
            color: var(--primary-light);
            margin-bottom: 8px;
        }
        .tech-highlight p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        @media (max-width: 768px) {
            .charts-row { grid-template-columns: 1fr; }
            .pub-stats { grid-template-columns: repeat(3, 1fr); }
            .product-grid { grid-template-columns: 1fr; }
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
                    <a href="/dashboard.php" class="btn btn-primary btn-sm">📊 我的銷售</a>
                <?php else: ?>
                    <a href="/auth/login.php" class="btn btn-primary btn-sm">登入 / 註冊</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <!-- Hero Section -->
        <div class="pub-hero">
            <h1>📱 AI 銷售員 — QR Code 掃碼付款平台</h1>
            <p>台灣首創 AI 銷售文案 + QR Code 即時付款技術 <span class="patent-badge">🏅 掃碼付款專利技術</span></p>
        </div>

        <!-- Technology Highlight -->
        <div class="tech-highlight">
            <h3>🔒 QR Code 掃碼付款專利技術</h3>
            <p>每個產品自動產生專屬 QR Code，消費者掃碼即可完成付款。支援 LINE Pay、街口支付等台灣主流行動支付。<br>本平台含完整專利技術授權，適合投資者或企業收購。</p>
        </div>

        <!-- Site Stats -->
        <div class="pub-stats">
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= $totalProducts ?></div>
                <div class="pub-stat-label">📦 上架產品</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= $totalSellers ?></div>
                <div class="pub-stat-label">🏪 活躍賣家</div>
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
                <div class="pub-stat-value"><?= $activeCampaigns ?></div>
                <div class="pub-stat-label">📢 進行中廣告</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">📈 每日交易量（近14天）</div>
                <canvas id="ordersChart" height="200"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">💰 每日營收（近14天）</div>
                <canvas id="revenueChart" height="200"></canvas>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">📊 產品類別分佈</div>
                <canvas id="categoryChart" height="200"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">📋 訂單狀態統計</div>
                <canvas id="statusChart" height="200"></canvas>
            </div>
        </div>

        <!-- All Products with QR Codes -->
        <h2 class="section-title">📱 所有產品 — 掃碼即可付款</h2>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
            <div class="product-card">
                <div class="product-image">
                    <?php if ($product['image_path']): ?>
                        <img src="<?= htmlspecialchars($product['image_path']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                    <?php else: ?>
                        <span class="no-image">📦</span>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-seller">🏪 <?= htmlspecialchars($product['store_name'] ?: '賣家') ?></div>
                    <div class="product-meta">
                        <span class="product-price">NT$<?= number_format($product['price']) ?></span>
                        <span class="product-views">👁 <?= number_format($product['views']) ?> 次瀏覽</span>
                    </div>
                </div>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=<?= urlencode(SITE_URL . '/api/pay.php?id=' . $product['id'] . '&amount=' . $product['price']) ?>" alt="QR Code 付款">
                    <div class="qr-label">📱 掃碼付款 NT$<?= number_format($product['price']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Recent Orders (masked names) -->
        <div class="chart-container" style="margin-bottom:32px;">
            <div class="chart-title">🛒 最近交易紀錄</div>
            <?php if (empty($recentOrders)): ?>
                <p style="color:var(--text-muted);text-align:center;padding:20px;">尚無交易紀錄</p>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>產品</th>
                            <th>買家</th>
                            <th>數量</th>
                            <th>金額</th>
                            <th>狀態</th>
                            <th>日期</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td><?= htmlspecialchars(mb_substr($order['product_name'], 0, 15)) ?></td>
                            <td style="color:var(--text-muted);"><?= maskBuyerName($order['buyer_name']) ?></td>
                            <td><?= $order['quantity'] ?></td>
                            <td style="font-weight:600;">NT$<?= number_format($order['amount']) ?></td>
                            <td>
                                <span class="status-badge badge-<?= $order['status'] ?>">
                                    <?= ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款'][$order['status']] ?? $order['status'] ?>
                                </span>
                            </td>
                            <td style="font-size:0.8rem;color:var(--text-dim);"><?= date('m/d H:i', strtotime($order['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer info -->
        <div class="tech-highlight" style="border-color: var(--border);">
            <h3>💼 投資/收購洽詢</h3>
            <p>本平台包含完整 QR Code 掃碼付款專利技術、AI 文案生成系統、多賣家管理後台。<br>如有投資或收購意願，歡迎來信洽詢。</p>
        </div>
    </div>

    <footer style="text-align:center;padding:24px;color:var(--text-dim);font-size:0.85rem;">
        © 2026 AI 銷售員 — QR Code 掃碼付款專利平台 | 台灣
    </footer>

    <script>
    Chart.defaults.color = '#8899b4';
    Chart.defaults.borderColor = '#1e2d4a';

    // Orders Chart
    new Chart(document.getElementById('ordersChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{
                label: '交易筆數',
                data: <?= json_encode(array_map('intval', $orderCounts)) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#1e2d4a' } }, x: { grid: { display: false } } }
        }
    });

    // Revenue Chart
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{
                label: '營收 (NT$)',
                data: <?= json_encode(array_map('floatval', $orderRevenues)) ?>,
                backgroundColor: 'rgba(168, 85, 247, 0.6)',
                borderColor: '#a855f7',
                borderWidth: 1,
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, grid: { color: '#1e2d4a' } }, x: { grid: { display: false } } }
        }
    });

    // Category Chart
    const categoryLabels = <?= json_encode(array_map(fn($c) => ['food'=>'食品','electronics'=>'3C電子','fashion'=>'服飾','accessories'=>'配件','home'=>'居家'][$c['category']] ?? $c['category'], $categoryData)) ?>;
    new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: categoryLabels,
            datasets: [{
                data: <?= json_encode(array_map(fn($c) => intval($c['count']), $categoryData)) ?>,
                backgroundColor: ['#6366f1', '#a855f7', '#22d3ee', '#f59e0b', '#22c55e', '#ef4444'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 16 } } } }
    });

    // Order Status Chart
    new Chart(document.getElementById('statusChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($s) => ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款'][$s['status']] ?? $s['status'], $orderStatusData)) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($s) => intval($s['count']), $orderStatusData)) ?>,
                backgroundColor: ['#f59e0b', '#3b82f6', '#a855f7', '#22c55e', '#ef4444', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 16 } } } }
    });
    </script>
</body>
</html>
