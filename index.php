<?php require_once __DIR__ . '/includes/db.php'; ?>
<?php $user = getCurrentUser(); ?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI 銷售員 — 台灣賣家專屬行銷平台</title>
    <meta name="description" content="台灣賣家專屬！一鍵上傳產品，AI 自動產生銷售文案、產品照片、QR Code 銷售碼，還能投放廣告到各大平台。">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🤖</text></svg>">
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
            <button class="mobile-menu-btn" aria-label="選單">☰</button>
            <nav class="nav-links">
                <a href="/" class="active">首頁</a>
                <a href="products/list.php">產品列表</a>
                <a href="products/add.php">上傳產品</a>
                <?php if ($user && $user['role'] === 'admin'): ?>
                <a href="admin/dashboard.php">管理後台</a>
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
                        <?php if ($user['role'] === 'admin'): ?>
                        <div class="divider"></div>
                        <a href="admin/dashboard.php">⚙️ 管理後台</a>
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

    <!-- Toast -->
    <div class="toast" id="toast"></div>

    <!-- Hero -->
    <section class="hero">
        <h2>賣家只需上傳產品<br>剩下的 AI 幫你搞定</h2>
        <p>上傳產品照片與描述，AI 自動產生銷售文案、QR Code 銷售碼，一鍵複製、下載、投放廣告。</p>
        <a href="products/add.php" class="btn btn-primary btn-lg">🚀 開始免費使用</a>
    </section>

    <!-- Features -->
    <div class="container">
        <div class="features-grid">
            <div class="feature-card">
                <span class="icon">✍️</span>
                <h3>AI 自動文案</h3>
                <p>輸入產品資訊，AI 立即產生吸引人的標語、特色與完整銷售文案</p>
            </div>
            <div class="feature-card">
                <span class="icon">📸</span>
                <h3>產品照片管理</h3>
                <p>上傳高品質產品照，一鍵下載使用</p>
            </div>
            <div class="feature-card">
                <span class="icon">📱</span>
                <h3>QR Code 銷售碼</h3>
                <p>自動產生銷售 QR Code，掃碼即買，串接台灣行動支付</p>
            </div>
            <div class="feature-card">
                <span class="icon">📢</span>
                <h3>廣告投放</h3>
                <p>一站式管理 Facebook、IG、Google、LINE、電視廣告投放</p>
            </div>
            <div class="feature-card">
                <span class="icon">👥</span>
                <h3>會員系統</h3>
                <p>支援 Google 帳號快速登入，管理你的產品與銷售數據</p>
            </div>
            <div class="feature-card">
                <span class="icon">📊</span>
                <h3>銷售分析</h3>
                <p>追蹤產品瀏覽次數、廣告成效，數據一目了然</p>
            </div>
        </div>

        <!-- How it works -->
        <section style="text-align:center;padding:60px 0;">
            <h2 style="font-size:1.6rem;margin-bottom:40px;">三步驟開始銷售 🚀</h2>
            <div class="features-grid" style="max-width:800px;margin:0 auto;">
                <div class="feature-card">
                    <span class="icon">1️⃣</span>
                    <h3>註冊帳號</h3>
                    <p>使用 Google 或 Email 快速註冊</p>
                </div>
                <div class="feature-card">
                    <span class="icon">2️⃣</span>
                    <h3>上傳產品</h3>
                    <p>填寫產品名稱、描述、價格與照片</p>
                </div>
                <div class="feature-card">
                    <span class="icon">3️⃣</span>
                    <h3>AI 產生素材</h3>
                    <p>自動產生文案、QR Code，開始銷售！</p>
                </div>
            </div>
        </section>
    </div>

    <footer class="site-footer">
        <p>© <?= date('Y') ?> AI 銷售員 — 台灣賣家專屬行銷平台</p>
    </footer>

    <script src="assets/js/app.js"></script>
</body>
</html>