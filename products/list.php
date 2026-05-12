<?php
/**
 * Product List - v2
 */
require_once __DIR__ . '/../includes/db.php';
$user = requireLogin();

$db = getDB();

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Filter
$status = $_GET['status'] ?? '';
$where = "WHERE user_id = ?";
$params = [$user['id']];

if ($status && in_array($status, ['draft', 'active', 'archived'])) {
    $where .= " AND status = ?";
    $params[] = $status;
}

// Count total
$stmt = $db->prepare("SELECT COUNT(*) as total FROM products $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$totalPages = ceil($total / $perPage);

// Get products
$stmt = $db->prepare("SELECT * FROM products $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$params[] = $perPage;
$params[] = $offset;
$stmt->execute($params);
$products = $stmt->fetchAll();

// Stats
$stmt = $db->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active_count,
    SUM(views) as total_views
    FROM products WHERE user_id = ?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品列表 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container" style="padding-top:40px;padding-bottom:60px;">
        <div class="page-header">
            <div>
                <h1>📦 我的產品</h1>
                <p>共 <?= $total ?> 個產品</p>
            </div>
            <a href="add.php" class="btn btn-primary">➕ 上傳新產品</a>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total'] ?: 0 ?></div>
                <div class="stat-label">全部產品</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_count'] ?: 0 ?></div>
                <div class="stat-label">已上架</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_views'] ?: 0) ?></div>
                <div class="stat-label">總瀏覽次數</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalPages ?></div>
                <div class="stat-label">頁數</div>
            </div>
        </div>

        <!-- Filter tabs -->
        <div class="tabs">
            <a href="list.php" class="tab <?= !$status ? 'active' : '' ?>">全部</a>
            <a href="list.php?status=active" class="tab <?= $status === 'active' ? 'active' : '' ?>">已上架</a>
            <a href="list.php?status=draft" class="tab <?= $status === 'draft' ? 'active' : '' ?>">草稿</a>
            <a href="list.php?status=archived" class="tab <?= $status === 'archived' ? 'active' : '' ?>">已封存</a>
        </div>

        <?php if (empty($products)): ?>
        <div class="empty-state">
            <span class="icon">📦</span>
            <h3>還沒有產品</h3>
            <p>上傳你的第一個產品，讓 AI 幫你產生存銷素材</p>
            <a href="add.php" class="btn btn-primary">🚀 上傳產品</a>
        </div>
        <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $p): ?>
            <div class="product-card">
                <?php if ($p['image_path']): ?>
                <img src="/<?= $p['image_path'] ?>" alt="<?= htmlspecialchars($p['name']) ?>" class="product-card-image" loading="lazy">
                <?php else: ?>
                <div class="product-card-image" style="background:var(--bg-input);display:flex;align-items:center;justify-content:center;font-size:3rem;">📸</div>
                <?php endif; ?>
                <div class="product-card-body">
                    <h3><?= htmlspecialchars($p['name']) ?></h3>
                    <div class="price">NT$ <?= number_format($p['price'], 0) ?></div>
                    <div style="margin-top:8px;display:flex;gap:6px;">
                        <span class="badge badge-<?= $p['status'] ?>"><?= 
                            ['draft'=>'草稿','active'=>'銷售中','archived'=>'已封存','rejected'=>'已拒絕'][$p['status']] ?? $p['status']
                        ?></span>
                        <span style="font-size:0.8rem;color:var(--text-dim);">👁️ <?= $p['views'] ?: 0 ?></span>
                    </div>
                </div>
                <div class="product-card-footer">
                    <span style="font-size:0.8rem;color:var(--text-dim);"><?= timeAgo($p['created_at']) ?></span>
                    <a href="view.php?id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">查看素材</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="text-align:center;margin-top:40px;display:flex;gap:8px;justify-content:center;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $status ? '&status='.$status : '' ?>" 
               class="btn btn-<?= $i === $page ? 'primary' : 'secondary' ?> btn-sm"
               style="min-width:40px;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/app.js"></script>
</body>
</html>