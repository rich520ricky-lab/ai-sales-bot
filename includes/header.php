<?php
/**
 * Page Header - reusable component
 */
$currentUser = getCurrentUser();
?>
<header class="site-header">
    <div class="header-inner">
        <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
        <button class="mobile-menu-btn" aria-label="選單">☰</button>
        <nav class="nav-links">
            <a href="/" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">首頁</a>
            <a href="/index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">🌐 營運總覽</a>
            <?php if ($currentUser): ?>
            <a href="/admin-dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'admin-dashboard.php' ? 'active' : '' ?>">📊 管理總覽</a>
            <?php endif; ?>
            <a href="/products/list.php">產品列表</a>
            <a href="/products/add.php">上傳產品</a>
            <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
            <a href="/admin-dashboard.php">管理後台</a>
            <?php endif; ?>
        </nav>
        <div class="nav-user">
            <?php if ($currentUser): ?>
            <div class="user-dropdown">
                <div style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%236366f1%22/><text x=%2250%22 y=%2265%22 font-size=%2245%22 text-anchor=%22middle%22 fill=%22white%22>👤</text></svg>') ?>" class="nav-avatar" alt="">
                    <span style="font-size:0.9rem;"><?= htmlspecialchars($currentUser['store_name'] ?: $currentUser['email']) ?></span>
                </div>
                <div class="user-dropdown-menu">
                    <a href="/admin-dashboard.php">📊 管理總覽</a>
                    <a href="/index.php">🌐 營運總覽</a>
                    <a href="/products/list.php">📦 我的產品</a>
                    <a href="/products/add.php">➕ 上傳產品</a>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="divider"></div>
                    <a href="/admin-dashboard.php">⚙️ 管理後台</a>
                    <?php endif; ?>
                    <div class="divider"></div>
                    <a href="/auth/logout.php">🚪 登出</a>
                </div>
            </div>
            <?php else: ?>
            <a href="/auth/login.php" class="btn btn-primary btn-sm">登入 / 註冊</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="toast" id="toast"></div>