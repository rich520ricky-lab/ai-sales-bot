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
$stmt = $db->query("SELECT id, name, title, price, image_path, image_url, product_url, source, category FROM products WHERE status='active' ORDER BY id DESC LIMIT $limit OFFSET $off");
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
    <title>AI 銷售員 — 台灣賣家專屬行銷平台</title>
    <meta name="description" content="台灣賣家專屬！一鍵上傳產品，AI 自動產生銷售文案、產品照片、QR Code 銷售碼，還能投放廣告到各大平台。">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><defs><linearGradient id=%22g%22 x1=%220%22 y1=%220%22 x2=%221%22 y2=%221%22><stop offset=%220%25%22 stop-color=%22%236d28d9%22/><stop offset=%22100%25%22 stop-color=%22%23db2777%22/></linearGradient></defs><rect rx=%2220%22 width=%22100%22 height=%22100%22 fill=%22url(%23g)%22/><path d=%22M30 65V40a20 20 0 0140 0v25%22 fill=%22none%22 stroke=%22white%22 stroke-width=%226%22 stroke-linecap=%22round%22/><circle cx=%2242%22 cy=%2252%22 r=%224%22 fill=%22white%22/><circle cx=%2258%22 cy=%2252%22 r=%224%22 fill=%22white%22/><path d=%22M25 35c-5-15 10-25 25-25s30 10 25 25%22 fill=%22none%22 stroke=%22white%22 stroke-width=%224%22 stroke-linecap=%22round%22/></svg>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- Header (matching www.market.com.tw) -->
    <header class="header">
        <div class="header-top">
            <a href="/" class="logo">
                <svg class="logo-icon" width="32" height="32" viewBox="0 0 100 100" fill="none"><defs><linearGradient id="lg" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#6d28d9"/><stop offset="100%" stop-color="#db2777"/></linearGradient></defs><rect rx="20" width="100" height="100" fill="url(#lg)"/><path d="M30 65V40a20 20 0 0140 0v25" stroke="white" stroke-width="6" stroke-linecap="round"/><circle cx="42" cy="52" r="4" fill="white"/><circle cx="58" cy="52" r="4" fill="white"/><path d="M25 35c-5-15 10-25 25-25s30 10 25 25" stroke="white" stroke-width="4" stroke-linecap="round" fill="none"/></svg>
                <div>
                    <span class="logo-text">AI 銷售員</span>
                    <span class="logo-sub">sale.market.com.tw</span>
                </div>
            </a>
            <div class="user-area">
                <?php if ($user): ?>
                <div class="user-dropdown">
                    <img src="<?= htmlspecialchars($user['avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><defs><linearGradient id=%22ag%22 x1=%220%22 y1=%220%22 x2=%221%22 y2=%221%22><stop offset=%220%25%22 stop-color=%22%236d28d9%22/><stop offset=%22100%25%22 stop-color=%22%23db2777%22/></linearGradient></defs><circle cx=%2250%22 cy=%2250%22 r=%2248%22 fill=%22url(%23ag)%22/><circle cx=%2250%22 cy=%2238%22 r=%2214%22 fill=%22white%22 opacity=%220.9%22/><ellipse cx=%2250%22 cy=%2280%22 rx=%2224%22 ry=%2218%22 fill=%22white%22 opacity=%220.9%22/></svg>') ?>" class="nav-avatar" alt="">
                    <div class="user-dropdown-menu">
                        <a href="products/list.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg> 我的產品</a>
                        <a href="products/add.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> 上傳產品</a>
                        <a href="dashboard.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg> 數據總覽</a>
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="divider"></div>
                        <a href="admin-dashboard.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg> 管理後台</a>
                        <?php endif; ?>
                        <div class="divider"></div>
                        <a href="auth/logout.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 登出</a>
                    </div>
                </div>
                <?php else: ?>
                <a href="auth/login.php" class="btn-login">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    登入 / 註冊
                </a>
                <?php endif; ?>
            </div>
        </div>
        <nav class="nav-tabs">
            <a href="/" class="nav-tab active"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>首頁</a>
            <a href="products/list.php" class="nav-tab"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>產品列表</a>
            <a href="products/add.php" class="nav-tab"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>上傳產品</a>
            <?php if ($user && $user['role'] === 'admin'): ?>
            <a href="admin-dashboard.php" class="nav-tab"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>管理後台</a>
            <?php endif; ?>
        </nav>
    </header>

    <!-- Hero -->
    <section class="hero">
        <h2>賣家只需上傳產品<br>剩下的 AI 幫你搞定</h2>
        <p>上傳產品照片與描述，AI 自動產生銷售文案、QR Code 銷售碼，一鍵複製、下載、投放廣告。</p>
        <div style="display:flex; gap:16px; justify-content:center; position:relative; z-index:1;">
            <a href="products/add.php" class="btn btn-primary btn-lg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg> 開始免費使用</a>
            <a href="products/list.php" class="btn btn-secondary btn-lg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg> 產品列表</a>
        </div>
    </section>

    <div class="container container-wide" style="padding-top:32px;">
        <!-- Tech Highlight -->
        <div class="tech-highlight">
            <h3><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:6px;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>QR Code 掃碼付款專利技術</h3>
            <p>每個產品自動產生專屬 QR Code，消費者掃碼即可完成付款。支援 LINE Pay、街口支付等台灣主流行動支付。<br>本平台含完整專利技術授權，適合投資者或企業收購。</p>
            <span class="patent-badge"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 專利保護</span>
        </div>

        <!-- Stats -->
        <div class="pub-stats">
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= number_format($totalProducts) ?></div>
                <div class="pub-stat-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg> 商品總數</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= number_format($totalViews) ?></div>
                <div class="pub-stat-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg> 產品瀏覽</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value"><?= $totalOrders ?></div>
                <div class="pub-stat-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg> 成功交易</div>
            </div>
            <div class="pub-stat-card">
                <div class="pub-stat-value">NT$<?= number_format($totalRevenue) ?></div>
                <div class="pub-stat-label"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg> 累計營收</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <div class="chart-container">
                <div class="chart-name"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg> 每日交易量（近14天）</div>
                <canvas id="ordersChart" height="180"></canvas>
            </div>
            <div class="chart-container">
                <div class="chart-name"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg> 每日營收（近14天）</div>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
        </div>

        <!-- Product Grid -->
        <h2 class="section-name"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg> 熱門商品展示</h2>
        <div class="product-grid">
            <?php foreach ($marketProducts as $p): 
                $displayName = !empty($p['name']) ? $p['name'] : ($p['title'] ?? '未命名商品');
                $displayImage = !empty($p['image_path']) ? $p['image_path'] : ($p['image_url'] ?? '');
                $productLink = !empty($p['product_url']) ? $p['product_url'] : 'products/detail.php?id=' . $p['id'];
                $isExternal = !empty($p['product_url']);
            ?>
            <div class="product-card">
                <a href="<?= htmlspecialchars($productLink) ?>" <?= $isExternal ? 'target="_blank" rel="noopener"' : '' ?> style="text-decoration:none;color:inherit;">
                <div class="product-image">
                    <?php if (!empty($displayImage)): ?>
                        <img src="<?= htmlspecialchars($displayImage) ?>" alt="<?= htmlspecialchars($displayName) ?>" loading="lazy">
                    <?php else: ?>
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    <?php endif; ?>
                </div>
                <div class="product-body">
                    <div class="product-name"><?= htmlspecialchars($displayName) ?></div>
                    <div class="product-price">NT$<?= number_format($p['price']) ?></div>
                    <?php if (!empty($p['source'])): ?>
                    <div style="font-size:0.7rem;color:var(--text-dim);margin-top:4px;">來源: <?= htmlspecialchars(ucfirst($p['source'])) ?></div>
                    <?php endif; ?>
                </div>
                </a>
                <div class="qr-section">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode(!empty($p['product_url']) ? $p['product_url'] : SITE_URL . '/api/pay.php?id=' . $p['id'] . '&amount=' . $p['price']) ?>" alt="QR Code" loading="lazy">
                    <div class="qr-label"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12.01" y2="18"/></svg> 掃碼付款 NT$<?= number_format($p['price']) ?></div>
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
        <h2 class="section-name"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg> 最近成交紀錄</h2>
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
                        <td style="font-weight:600;color:var(--primary);">NT$<?= number_format($order['amount']) ?></td>
                        <td><span class="status-badge badge-<?= $order['status'] ?>"><?= ['pending'=>'待付款','paid'=>'已付款','shipped'=>'已出貨','completed'=>'已完成'][$order['status']] ?? $order['status'] ?></span></td>
                        <td style="color:var(--text-dim);"><?= date('m/d H:i', strtotime($order['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <footer class="footer">
        <p>© <?= date('Y') ?> AI 銷售員 — sale.market.com.tw &nbsp;|&nbsp; <a href="https://www.market.com.tw">www.market.com.tw</a></p>
    </footer>

    <script>
    const chartOptions = {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(109,40,217,0.08)' }, ticks: { color: '#7c6fad' } },
            x: { grid: { display: false }, ticks: { color: '#7c6fad' } }
        }
    };
    new Chart(document.getElementById('ordersChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{ data: <?= json_encode(array_map('intval', $orderCounts)) ?>, borderColor: '#6d28d9', backgroundColor: 'rgba(109, 40, 217, 0.1)', fill: true, tension: 0.4, borderWidth: 2 }]
        },
        options: chartOptions
    });
    new Chart(document.getElementById('revenueChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_map(fn($d) => date('m/d', strtotime($d)), $orderDates)) ?>,
            datasets: [{ data: <?= json_encode(array_map('floatval', $orderRevenues)) ?>, backgroundColor: 'rgba(219, 39, 119, 0.5)', borderRadius: 8 }]
        },
        options: chartOptions
    });
    </script>
</body>
</html>
