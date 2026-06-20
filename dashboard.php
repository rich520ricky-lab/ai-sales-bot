<?php
/**
 * Seller Dashboard - 賣家銷售數據總覽
 * Shows sales stats, product performance, order trends, and campaign overview
 */
require_once __DIR__ . '/includes/db.php';
$user = requireLogin();

$db = getDB();
$userId = $user['id'];
$isAdmin = ($user['role'] === 'admin');

// For admin, show all data; for sellers, show only their own
$userFilter = $isAdmin ? "" : "WHERE p.user_id = ? OR p.seller_id = ?";
$userParams = $isAdmin ? [] : [$userId, $userId];

// Overall stats
$stmt = $db->prepare("SELECT
    COUNT(*) as total_products,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_products,
    SUM(CASE WHEN status='draft' THEN 1 ELSE 0 END) as draft_products,
    SUM(views) as total_views
    FROM products p " . ($isAdmin ? "" : "WHERE (p.user_id = ? OR p.seller_id = ?)"));
$stmt->execute($userParams);
$productStats = $stmt->fetch();

// Order stats
$orderQuery = $isAdmin
    ? "SELECT COUNT(*) as total_orders, SUM(amount) as total_revenue, SUM(quantity) as total_items FROM orders"
    : "SELECT COUNT(*) as total_orders, SUM(o.amount) as total_revenue, SUM(o.quantity) as total_items FROM orders o JOIN products p ON o.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?)";
$stmt = $db->prepare($orderQuery);
$stmt->execute($userParams);
$orderStats = $stmt->fetch();

// Campaign stats
$campaignQuery = $isAdmin
    ? "SELECT COUNT(*) as total_campaigns, SUM(budget) as total_budget, SUM(CASE WHEN status='running' THEN 1 ELSE 0 END) as running_campaigns FROM ad_campaigns"
    : "SELECT COUNT(*) as total_campaigns, SUM(ac.budget) as total_budget, SUM(CASE WHEN ac.status='running' THEN 1 ELSE 0 END) as running_campaigns FROM ad_campaigns ac JOIN products p ON ac.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?)";
$stmt = $db->prepare($campaignQuery);
$stmt->execute($userParams);
$campaignStats = $stmt->fetch();

// Top products by views
$topProductsQuery = $isAdmin
    ? "SELECT id, name, price, views, status FROM products ORDER BY views DESC LIMIT 5"
    : "SELECT id, name, price, views, status FROM products WHERE (user_id = ? OR seller_id = ?) ORDER BY views DESC LIMIT 5";
$stmt = $db->prepare($topProductsQuery);
$stmt->execute($userParams);
$topProducts = $stmt->fetchAll();

// Recent orders
$recentOrdersQuery = $isAdmin
    ? "SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id ORDER BY o.created_at DESC LIMIT 10"
    : "SELECT o.*, p.name as product_name FROM orders o JOIN products p ON o.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?) ORDER BY o.created_at DESC LIMIT 10";
$stmt = $db->prepare($recentOrdersQuery);
$stmt->execute($userParams);
$recentOrders = $stmt->fetchAll();

// Daily views for chart (last 14 days)
$viewsChartQuery = $isAdmin
    ? "SELECT view_date, SUM(view_count) as total_views FROM product_views_daily WHERE view_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY view_date ORDER BY view_date ASC"
    : "SELECT pvd.view_date, SUM(pvd.view_count) as total_views FROM product_views_daily pvd JOIN products p ON pvd.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?) AND pvd.view_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY pvd.view_date ORDER BY pvd.view_date ASC";
$stmt = $db->prepare($viewsChartQuery);
$stmt->execute($userParams);
$dailyViews = $stmt->fetchAll();

// Daily orders for chart (last 14 days)
$ordersChartQuery = $isAdmin
    ? "SELECT DATE(created_at) as order_date, COUNT(*) as order_count, SUM(amount) as daily_revenue FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY order_date ASC"
    : "SELECT DATE(o.created_at) as order_date, COUNT(*) as order_count, SUM(o.amount) as daily_revenue FROM orders o JOIN products p ON o.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?) AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(o.created_at) ORDER BY order_date ASC";
$stmt = $db->prepare($ordersChartQuery);
$stmt->execute($userParams);
$dailyOrders = $stmt->fetchAll();

// Campaign by platform
$platformQuery = $isAdmin
    ? "SELECT platform, COUNT(*) as count, SUM(budget) as total_budget FROM ad_campaigns GROUP BY platform"
    : "SELECT ac.platform, COUNT(*) as count, SUM(ac.budget) as total_budget FROM ad_campaigns ac JOIN products p ON ac.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?) GROUP BY ac.platform";
$stmt = $db->prepare($platformQuery);
$stmt->execute($userParams);
$platformData = $stmt->fetchAll();

// Order status distribution
$orderStatusQuery = $isAdmin
    ? "SELECT status, COUNT(*) as count FROM orders GROUP BY status"
    : "SELECT o.status, COUNT(*) as count FROM orders o JOIN products p ON o.product_id = p.id WHERE (p.user_id = ? OR p.seller_id = ?) GROUP BY o.status";
$stmt = $db->prepare($orderStatusQuery);
$stmt->execute($userParams);
$orderStatusData = $stmt->fetchAll();

// Prepare chart data
$viewDates = array_column($dailyViews, 'view_date');
$viewCounts = array_column($dailyViews, 'total_views');
$orderDates = array_column($dailyOrders, 'order_date');
$orderCounts = array_column($dailyOrders, 'order_count');
$orderRevenues = array_column($dailyOrders, 'daily_revenue');
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>銷售總覽 — AI 銷售員</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .dash-stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            text-align: center;
            transition: var(--transition);
        }
        .dash-stat-card:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        .dash-stat-value {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .dash-stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .chart-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
        }
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text);
        }
        .charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
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
        .top-product-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .top-product-item:last-child { border-bottom: none; }
        .top-product-name {
            font-weight: 500;
            color: var(--text);
        }
        .top-product-views {
            font-size: 0.85rem;
            color: var(--primary-light);
        }
        @media (max-width: 768px) {
            .charts-row { grid-template-columns: 1fr; }
            .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <div class="page-header">
            <div>
                <h1>📊 銷售總覽</h1>
                <p>歡迎回來，<?= htmlspecialchars($user['store_name'] ?: $user['email']) ?>！以下是您的銷售數據。</p>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="products/list.php" class="btn btn-secondary btn-sm">📦 我的產品</a>
                <a href="products/add.php" class="btn btn-primary btn-sm">➕ 上傳產品</a>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-grid">
            <div class="dash-stat-card">
                <div class="dash-stat-value"><?= $productStats['total_products'] ?: 0 ?></div>
                <div class="dash-stat-label">📦 總產品數</div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-value"><?= $productStats['active_products'] ?: 0 ?></div>
                <div class="dash-stat-label">🚀 銷售中</div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-value"><?= number_format($productStats['total_views'] ?: 0) ?></div>
                <div class="dash-stat-label">👁 總瀏覽數</div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-value"><?= $orderStats['total_orders'] ?: 0 ?></div>
                <div class="dash-stat-label">🛒 總訂單數</div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-value">NT$<?= number_format($orderStats['total_revenue'] ?: 0) ?></div>
                <div class="dash-stat-label">💰 總營收</div>
            </div>
            <div class="dash-stat-card">
                <div class="dash-stat-value"><?= $campaignStats['running_campaigns'] ?: 0 ?></div>
                <div class="dash-stat-label">📢 進行中廣告</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">📈 每日瀏覽趨勢（近14天）</div>
                <canvas id="viewsChart" height="200"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">💰 每日營收趨勢（近14天）</div>
                <canvas id="revenueChart" height="200"></canvas>
            </div>
        </div>

        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-title">📊 廣告平台預算分佈</div>
                <canvas id="platformChart" height="200"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-title">📋 訂單狀態分佈</div>
                <canvas id="orderStatusChart" height="200"></canvas>
            </div>
        </div>

        <!-- Top Products & Recent Orders -->
        <div class="charts-row">
            <!-- Top Products -->
            <div class="chart-container">
                <div class="chart-title">🏆 熱門產品 TOP 5</div>
                <?php if (empty($topProducts)): ?>
                    <p style="color:var(--text-muted);text-align:center;padding:20px;">尚無產品數據</p>
                <?php else: ?>
                    <?php foreach ($topProducts as $i => $tp): ?>
                    <div class="top-product-item">
                        <div>
                            <span style="color:var(--text-dim);font-size:0.8rem;margin-right:8px;">#<?= $i + 1 ?></span>
                            <span class="top-product-name"><?= htmlspecialchars(mb_substr($tp['name'], 0, 20)) ?></span>
                            <span style="color:var(--text-dim);font-size:0.8rem;margin-left:8px;">NT$<?= number_format($tp['price']) ?></span>
                        </div>
                        <span class="top-product-views">👁 <?= number_format($tp['views']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Orders -->
            <div class="chart-container">
                <div class="chart-title">🛒 最近訂單</div>
                <?php if (empty($recentOrders)): ?>
                    <p style="color:var(--text-muted);text-align:center;padding:20px;">尚無訂單</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>產品</th>
                                <th>買家</th>
                                <th>金額</th>
                                <th>狀態</th>
                                <th>日期</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentOrders as $order): ?>
                            <tr>
                                <td><?= htmlspecialchars(mb_substr($order['product_name'], 0, 12)) ?></td>
                                <td style="color:var(--text-muted);"><?= htmlspecialchars($order['buyer_name']) ?></td>
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
        </div>
    </div>

    <script>
    // Chart.js global config for dark theme
    Chart.defaults.color = '#8899b4';
    Chart.defaults.borderColor = '#1e2d4a';

    // Views Chart
    const viewsCtx = document.getElementById('viewsChart').getContext('2d');
    new Chart(viewsCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $viewDates)) ?>,
            datasets: [{
                label: '瀏覽次數',
                data: <?= json_encode(array_map('intval', $viewCounts)) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#1e2d4a' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
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
            scales: {
                y: { beginAtZero: true, grid: { color: '#1e2d4a' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Platform Pie Chart
    const platformCtx = document.getElementById('platformChart').getContext('2d');
    new Chart(platformCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($p) => ucfirst($p['platform']), $platformData)) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($p) => floatval($p['total_budget']), $platformData)) ?>,
                backgroundColor: ['#6366f1', '#a855f7', '#22d3ee', '#f59e0b', '#22c55e'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16 } }
            }
        }
    });

    // Order Status Chart
    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(orderStatusCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_map(fn($s) => ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成','cancelled'=>'已取消','refunded'=>'已退款'][$s['status']] ?? $s['status'], $orderStatusData)) ?>,
            datasets: [{
                data: <?= json_encode(array_map(fn($s) => intval($s['count']), $orderStatusData)) ?>,
                backgroundColor: ['#f59e0b', '#3b82f6', '#a855f7', '#22c55e', '#ef4444', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 16 } }
            }
        }
    });
    </script>
</body>
</html>
