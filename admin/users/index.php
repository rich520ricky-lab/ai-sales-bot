<?php
/**
 * Admin - Users Management
 */
session_start();
require_once __DIR__ . '/../../includes/db.php';
$admin = requireAdmin();

$db = getDB();

// Handle actions (POST only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!verifyCsrf()) {
        http_response_code(403);
        die('無效的請求令牌');
    }

    $id = intval($_POST['id']);
    $action = $_POST['action'];

    $allowedActions = ['ban', 'unban', 'delete'];
    if (!in_array($action, $allowedActions, true)) {
        $_SESSION['flash_error'] = '無效的操作';
        header('Location: index.php', true, 303);
        exit;
    }

    if ($id <= 0) {
        $_SESSION['flash_error'] = '無效的會員 ID';
        header('Location: index.php', true, 303);
        exit;
    }

    $checkStmt = $db->prepare("SELECT id, role FROM users WHERE id = ?");
    $checkStmt->execute([$id]);
    $targetUser = $checkStmt->fetch();
    if (!$targetUser) {
        $_SESSION['flash_error'] = '會員不存在 (ID: ' . $id . ')';
        header('Location: index.php', true, 303);
        exit;
    }

    if ($targetUser['role'] === 'admin') {
        $_SESSION['flash_error'] = '無法對管理員執行此操作';
        header('Location: index.php', true, 303);
        exit;
    }

    try {
        if ($action === 'ban') {
            $db->prepare("UPDATE users SET status='banned' WHERE id=? AND role!='admin'")->execute([$id]);
            logActivity($admin['id'], 'ban_user', "停用會員 #{$id}");
        } elseif ($action === 'unban') {
            $db->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
            logActivity($admin['id'], 'unban_user', "啟用會員 #{$id}");
        } elseif ($action === 'delete') {
            $db->prepare("DELETE FROM users WHERE id=? AND role!='admin'")->execute([$id]);
            logActivity($admin['id'], 'delete_user', "刪除會員 #{$id}");
        }
        $_SESSION['flash_success'] = '操作成功';
        header('Location: index.php', true, 303);
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_error'] = '操作失敗，請稍後再試';
        header('Location: index.php', true, 303);
        exit;
    }
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalPages = ceil($total / $perPage);

$stmt = $db->prepare("SELECT u.*, (SELECT COUNT(*) FROM products WHERE user_id = u.id) as product_count FROM users u ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>會員管理 — 管理後台 · AI 銷售員</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <div class="breadcrumb">
            <a href="../dashboard.php">管理後台</a>
            <span class="sep">›</span>
            <span>會員管理</span>
        </div>

        <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['flash_error']) ?></div>
        <?php unset($_SESSION['flash_error']); endif; ?>

        <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['flash_success']) ?></div>
        <?php unset($_SESSION['flash_success']); endif; ?>

        <div class="page-header">
            <div>
                <h1>👥 會員管理</h1>
                <p>共 <?= $total ?> 位會員</p>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>商店名稱</th>
                        <th>Email</th>
                        <th>登入方式</th>
                        <th>產品數</th>
                        <th>狀態</th>
                        <th>加入時間</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="color:var(--text-dim);">#<?= $u['id'] ?></td>
                        <td><strong><?= htmlspecialchars($u['store_name'] ?: '未設定') ?></strong></td>
                        <td style="font-size:0.85rem;"><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= $u['google_id'] ? '🔵 Google' : '📧 Email' ?></td>
                        <td><?= $u['product_count'] ?></td>
                        <td>
                            <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge badge-running">管理員</span>
                            <?php else: ?>
                            <span class="badge badge-<?= $u['status'] === 'active' ? 'active' : 'rejected' ?>"><?= htmlspecialchars($u['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:0.85rem;color:var(--text-dim);"><?= formatDate($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <div style="display:flex;gap:4px;">
                                <?php if ($u['status'] === 'active'): ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('確定停用此會員？')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="ban">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">停用</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="unban">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">啟用</button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('確定刪除此會員？')">
                                    <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm">🗑️</button>
                                </form>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-dim);font-size:0.85rem;">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div style="text-align:center;margin-top:24px;display:flex;gap:8px;justify-content:center;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>" class="btn btn-<?= $i === $page ? 'primary' : 'secondary' ?> btn-sm" style="min-width:40px;"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
