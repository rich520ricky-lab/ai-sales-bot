<?php
/**
 * Admin - Products Management
 */
require_once __DIR__ . '/../includes/db.php';
$admin = requireAdmin();

$db = getDB();

// Handle actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    try {
        if ($action === 'approve') {
            $db->prepare("UPDATE products SET status='active' WHERE id=?")->execute([$id]);
            logActivity($admin['id'], 'approve_product', "審核通過產品 #{$id}");
        } elseif ($action === 'reject') {
            $db->prepare("UPDATE products SET status='rejected' WHERE id=?")->execute([$id]);
            logActivity($admin['id'], 'reject_product', "拒絕產品 #{$id}");
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
            logActivity($admin['id'], 'delete_product', "刪除產品 #{$id}");
        }
        header('Location: index.php');
        exit;
    } catch (Exception $e) {}
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$status = $_GET['status'] ?? '';
$where = "WHERE 1=1";
$params = [];
if ($status && in_array($status, ['draft', 'active', 'archived', 'rejected'])) {
    $where .= " AND p.status = ?";
    $params[] = $status;
}

$total = $db->query("SELECT COUNT(*) FROM products p $where")->fetchColumn();
$totalPages = ceil($total / $perPage);

$sql = "SELECT p.*, u.store_name, u.email FROM products p JOIN users u ON p.user_id = u.id $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品管理 — 管理後台 · AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <div class="breadcrumb">
            <a href="dashboard.php">管理後台</a>
            <span class="sep">›</span>
            <span>產品管理</span>
        </div>

        <div class="page-header">
            <div>
                <h1>📦 產品管理</h1>
                <p>共 <?= $total ?> 個產品</p>
            </div>
        </div>

        <div class="tabs">
            <a href="index.php" class="tab <?= !$status ? 'active' : '' ?>">全部</a>
            <a href="index.php?status=active" class="tab <?= $status === 'active' ? 'active' : '' ?>">已上架</a>
            <a href="index.php?status=draft" class="tab <?= $status === 'draft' ? 'active' : '' ?>">草稿</a>
            <a href="index.php?status=rejected" class="tab <?= $status === 'rejected' ? 'active' : '' ?>">已拒絕</a>
            <a href="index.php?status=archived" class="tab <?= $status === 'archived' ? 'active' : '' ?>">已封存</a>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>產品名稱</th>
                        <th>賣家</th>
                        <th>價格</th>
                        <th>瀏覽</th>
                        <th>狀態</th>
                        <th>建立時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td style="color:var(--text-dim);">#<?= $p['id'] ?></td>
                        <td><a href="/products/view.php?id=<?= $p['id'] ?>" style="color:var(--text);text-decoration:none;font-weight:500;"><?= htmlspecialchars(mb_substr($p['name'], 0, 30)) ?></a></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($p['store_name'] ?: $p['email']) ?></td>
                        <td>NT$ <?= number_format($p['price'], 0) ?></td>
                        <td><?= $p['views'] ?></td>
                        <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                        <td style="font-size:0.85rem;color:var(--text-dim);"><?= formatDate($p['created_at']) ?></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <?php if ($p['status'] === 'draft' || $p['status'] === 'rejected'): ?>
                                <a href="?action=approve&id=<?= $p['id'] ?>" class="btn btn-primary btn-sm">✅ 通過</a>
                                <?php endif; ?>
                                <?php if ($p['status'] !== 'rejected'): ?>
                                <a href="?action=reject&id=<?= $p['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('確定拒絕？')">❌ 拒絕</a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-ghost btn-sm" onclick="return confirm('確定刪除？')">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--text-muted);padding:40px;">暫無產品</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="text-align:center;margin-top:24px;display:flex;gap:8px;justify-content:center;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $status ? '&status='.$status : '' ?>" class="btn btn-<?= $i === $page ? 'primary' : 'secondary' ?> btn-sm" style="min-width:40px;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>