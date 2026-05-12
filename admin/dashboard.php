<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../includes/db.php';
$admin = requireAdmin();

$db = getDB();

// Stats
$stats = [];
$stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['total_products'] = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$stats['active_products'] = $db->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn();
$stats['total_views'] = $db->query("SELECT SUM(views) FROM products")->fetchColumn() ?: 0;
$stats['total_campaigns'] = $db->query("SELECT COUNT(*) FROM ad_campaigns")->fetchColumn();

// Recent products
$recentProducts = $db->query("SELECT p.*, u.store_name, u.email FROM products p JOIN users u ON p.user_id = u.id ORDER BY p.created_at DESC LIMIT 10")->fetchAll();

// Recent users
$recentUsers = $db->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();

// Recent activity
$recentActivity = $db->query("SELECT a.*, u.email, u.store_name FROM activity_log a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理後台 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <div class="page-header">
            <div>
                <h1>⚙️ 管理後台</h1>
                <p>系統管理與數據總覽</p>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="users/index.php" class="btn btn-secondary btn-sm">👥 會員管理</a>
                <a href="products/index.php" class="btn btn-secondary btn-sm">📦 產品管理</a>
                <a href="settings/index.php" class="btn btn-secondary btn-sm">⚙️ 系統設定</a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_users'] ?></div>
                <div class="stat-label">👥 會員數</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_products'] ?></div>
                <div class="stat-label">📦 總產品數</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['active_products'] ?></div>
                <div class="stat-label">🚀 銷售中</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['total_views']) ?></div>
                <div class="stat-label">👁️ 總瀏覽</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['total_campaigns'] ?></div>
                <div class="stat-label">📢 廣告活動</div>
            </div>
        </div>

        <!-- Recent Products & Users -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
            <!-- Recent Products -->
            <div class="card">
                <div class="card-header">
                    <strong>📦 最新產品</strong>
                    <a href="products/index.php" style="color:var(--primary-light);font-size:0.85rem;">查看全部 →</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>產品</th>
                                <th>賣家</th>
                                <th>狀態</th>
                                <th>時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProducts as $p): ?>
                            <tr>
                                <td><a href="/products/view.php?id=<?= $p['id'] ?>" style="color:var(--text);text-decoration:none;"><?= htmlspecialchars(mb_substr($p['name'], 0, 20)) ?></a></td>
                                <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($p['store_name'] ?: $p['email']) ?></td>
                                <td><span class="badge badge-<?= $p['status'] ?>"><?= $p['status'] ?></span></td>
                                <td style="font-size:0.8rem;color:var(--text-dim);"><?= timeAgo($p['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <strong>👥 最新會員</strong>
                    <a href="users/index.php" style="color:var(--primary-light);font-size:0.85rem;">查看全部 →</a>
                </div>
                <div class="card-body" style="padding:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>商店</th>
                                <th>Email</th>
                                <th>登入方式</th>
                                <th>時間</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['store_name'] ?: '未設定') ?></td>
                                <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= $u['google_id'] ? '🔵 Google' : '📧 Email' ?></td>
                                <td style="font-size:0.8rem;color:var(--text-dim);"><?= timeAgo($u['created_at']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <div class="card-header">
                <strong>📋 活動紀錄</strong>
            </div>
            <div class="card-body" style="padding:0;">
                <table>
                    <thead>
                        <tr>
                            <th>使用者</th>
                            <th>動作</th>
                            <th>詳細</th>
                            <th>IP</th>
                            <th>時間</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentActivity as $a): ?>
                        <tr>
                            <td style="font-size:0.85rem;"><?= htmlspecialchars($a['store_name'] ?: $a['email'] ?: '訪客') ?></td>
                            <td><span class="badge badge-pending"><?= htmlspecialchars($a['action']) ?></span></td>
                            <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars(mb_substr($a['details'] ?? '', 0, 40)) ?></td>
                            <td style="font-size:0.8rem;color:var(--text-dim);"><?= $a['ip_address'] ?></td>
                            <td style="font-size:0.8rem;color:var(--text-dim);"><?= timeAgo($a['created_at']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>