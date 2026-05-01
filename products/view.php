<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品素材 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🎯 產品素材</h1>
            <nav>
                <a href="../index.php">首頁</a>
                <a href="list.php">產品列表</a>
                <a href="add.php">上傳新產品</a>
            </nav>
        </header>

        <main>
            <?php
            require_once '../includes/db.php';
            $id = intval($_GET['id'] ?? 0);
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $p = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$p): ?>
                <div class="error">❌ 找不到產品</div>
            <?php else: ?>
                <h2><?= htmlspecialchars($p['name']) ?></h2>
                <p class="price">NT$ <?= number_format($p['price'], 0) ?></p>

                <div class="materials-grid">
                    <!-- 文案 -->
                    <div class="material-card">
                        <h3>✍️ 銷售文案</h3>
                        <div class="copy-box" id="copyText"><?= nl2br(htmlspecialchars($p['ai_copy'] ?? '尚未產生')) ?></div>
                        <button onclick="copyToClipboard()" class="btn-secondary">📋 複製文案</button>
                    </div>

                    <!-- QR Code -->
                    <div class="material-card">
                        <h3>📱 銷售 QR Code</h3>
                        <?php if ($p['qr_code_path']): ?>
                            <img src="<?= $p['qr_code_path'] ?>" alt="QR Code" class="qr-code">
                            <p class="hint">掃碼即可付款購買（LINE Pay / 街口 / 台灣 Pay）</p>
                            <a href="<?= $p['qr_code_path'] ?>" download class="btn-secondary">⬇️ 下載 QR Code</a>
                        <?php else: ?>
                            <p>QR Code 待產生...</p>
                        <?php endif; ?>
                    </div>

                    <!-- 產品照 -->
                    <div class="material-card">
                        <h3>📸 產品照片</h3>
                        <?php if ($p['image_path']): ?>
                            <img src="../<?= $p['image_path'] ?>" alt="產品照" class="product-img">
                            <a href="../<?= $p['image_path'] ?>" download class="btn-secondary">⬇️ 下載照片</a>
                        <?php else: ?>
                            <p>無產品照片</p>
                        <?php endif; ?>
                    </div>

                    <!-- 廣告投放 -->
                    <div class="material-card">
                        <h3>📢 廣告投放</h3>
                        <p>選擇投放平台：</p>
                        <form action="../api/ad-campaign.php" method="POST" class="ad-form">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <select name="platform">
                                <option value="facebook">Facebook / Instagram</option>
                                <option value="google">Google Ads</option>
                                <option value="line">LINE 廣告</option>
                                <option value="tv">電視廣告</option>
                            </select>
                            <input type="number" name="budget" placeholder="預算 (TWD)" step="100" min="100">
                            <button type="submit" class="btn-primary">🚀 投放廣告</button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
    function copyToClipboard() {
        const text = document.getElementById('copyText').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('✅ 文案已複製！');
        });
    }
    </script>
</body>
</html>