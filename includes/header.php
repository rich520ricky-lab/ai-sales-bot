<?php
/**
 * Page Header - matching www.market.com.tw style
 */
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF']);
?>
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
            <?php if ($currentUser): ?>
            <div class="user-dropdown">
                <img src="<?= htmlspecialchars($currentUser['avatar'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><defs><linearGradient id=%22ag%22 x1=%220%22 y1=%220%22 x2=%221%22 y2=%221%22><stop offset=%220%25%22 stop-color=%22%236d28d9%22/><stop offset=%22100%25%22 stop-color=%22%23db2777%22/></linearGradient></defs><circle cx=%2250%22 cy=%2250%22 r=%2248%22 fill=%22url(%23ag)%22/><circle cx=%2250%22 cy=%2238%22 r=%2214%22 fill=%22white%22 opacity=%220.9%22/><ellipse cx=%2250%22 cy=%2280%22 rx=%2224%22 ry=%2218%22 fill=%22white%22 opacity=%220.9%22/></svg>') ?>" class="nav-avatar" alt="">
                <div class="user-dropdown-menu">
                    <a href="/products/list.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg> 我的產品</a>
                    <a href="/products/add.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> 上傳產品</a>
                    <a href="/dashboard.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 20V10"/><path d="M12 20V4"/><path d="M6 20v-6"/></svg> 數據總覽</a>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <div class="divider"></div>
                    <a href="/admin-dashboard.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg> 管理後台</a>
                    <?php endif; ?>
                    <div class="divider"></div>
                    <a href="/auth/logout.php"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> 登出</a>
                </div>
            </div>
            <?php else: ?>
            <a href="/auth/login.php" class="btn-login">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                登入 / 註冊
            </a>
            <?php endif; ?>
        </div>
    </div>
    <nav class="nav-tabs">
        <a href="/" class="nav-tab <?= $currentPage === 'index.php' ? 'active' : '' ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>首頁</a>
        <a href="/products/list.php" class="nav-tab <?= $currentPage === 'list.php' ? 'active' : '' ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a4 4 0 00-8 0v2"/></svg>產品列表</a>
        <a href="/products/add.php" class="nav-tab <?= $currentPage === 'add.php' ? 'active' : '' ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>上傳產品</a>
        <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
        <a href="/admin-dashboard.php" class="nav-tab <?= $currentPage === 'admin-dashboard.php' ? 'active' : '' ?>"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:middle;margin-right:4px;"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>管理後台</a>
        <?php endif; ?>
    </nav>
</header>
<div class="toast" id="toast"></div>
