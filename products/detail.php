<?php
/**
 * Public Product Detail - accessible without login
 * Shows product info, image, QR code for payment
 */
require_once __DIR__ . '/../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header('Location: /');
    exit;
}

$db = getDB();

// Public view: no user_id filter, only active products
$stmt = $db->prepare("SELECT p.*, u.store_name FROM products p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.status = 'active'");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) {
    header('Location: /');
    exit;
}

// Increment view count
$db->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);

// Generate QR code URL for payment
$qrPayUrl = SITE_URL . '/api/pay.php?id=' . $p['id'] . '&amount=' . $p['price'];
$qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrPayUrl);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['name'] ?? '') ?> — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .product-detail { max-width: 900px; margin: 0 auto; padding: 40px 20px 60px; }
        .product-hero { display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap; margin-bottom: 32px; }
        .product-hero-img { width: 320px; height: 320px; object-fit: cover; border-radius: 12px; flex-shrink: 0; background: var(--bg-card, #1a1a2e); display: flex; align-items: center; justify-content: center; }
        .product-hero-img img { width: 100%; height: 100%; object-fit: cover; border-radius: 12px; }
        .product-hero-info { flex: 1; min-width: 280px; }
        .product-hero-info h1 { font-size: 1.6rem; margin-bottom: 12px; }
        .product-price { font-size: 2rem; font-weight: 700; color: #a78bfa; margin: 16px 0; }
        .product-meta { display: flex; gap: 16px; flex-wrap: wrap; margin: 12px 0; color: #94a3b8; font-size: 0.9rem; }
        .product-desc { margin-top: 16px; line-height: 1.7; color: #cbd5e1; }
        .qr-section { background: var(--bg-card, #1e1e3a); border-radius: 12px; padding: 32px; text-align: center; margin-top: 32px; }
        .qr-section h2 { margin-bottom: 16px; font-size: 1.3rem; }
        .qr-section img { max-width: 280px; border-radius: 8px; background: #fff; padding: 12px; }
        .qr-section .pay-amount { font-size: 1.5rem; font-weight: 700; color: #a78bfa; margin-top: 12px; }
        .back-link { display: inline-block; margin-bottom: 20px; color: #94a3b8; text-decoration: none; }
        .back-link:hover { color: #a78bfa; }
        .store-badge { display: inline-block; padding: 4px 12px; background: rgba(167,139,250,0.15); border-radius: 20px; font-size: 0.85rem; color: #a78bfa; }
        @media (max-width: 768px) {
            .product-hero { flex-direction: column; }
            .product-hero-img { width: 100%; height: 250px; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="product-detail">
        <a href="/" class="back-link">← 返回首頁</a>

        <div class="product-hero">
            <div class="product-hero-img">
                <?php if (!empty($p['image_path'])): ?>
                    <img src="/<?= htmlspecialchars($p['image_path'] ?? '') ?>" alt="<?= htmlspecialchars($p['name'] ?? '') ?>">
                <?php else: ?>
                    <span style="font-size:4rem;">📦</span>
                <?php endif; ?>
            </div>
            <div class="product-hero-info">
                <h1><?= htmlspecialchars($p['name'] ?? '') ?></h1>
                <div class="product-price">NT$ <?= number_format($p['price'], 0) ?></div>
                <div class="product-meta">
                    <?php if (!empty($p['category'])): ?>
                        <span>📂 <?= htmlspecialchars($p['category'] ?? '') ?></span>
                    <?php endif; ?>
                    <span>👁 <?= number_format($p['views']) ?> 次瀏覽</span>
                    <?php if (!empty($p['store_name'])): ?>
                        <span class="store-badge">🏪 <?= htmlspecialchars($p['store_name'] ?? '') ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($p['description'])): ?>
                    <div class="product-desc"><?= nl2br(htmlspecialchars($p['description'] ?? '')) ?></div>
                <?php endif; ?>
                <?php if (!empty($p['short_desc'])): ?>
                    <div class="product-desc"><?= nl2br(htmlspecialchars($p['short_desc'] ?? '')) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- QR Code Payment Section -->
        <div class="qr-section">
            <h2>📱 掃碼付款</h2>
            <p style="color:#94a3b8;margin-bottom:16px;">掃描下方 QR Code 即可完成付款，支援 LINE Pay、街口支付等行動支付</p>
            <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="付款 QR Code">
            <div class="pay-amount">NT$ <?= number_format($p['price'], 0) ?></div>
            <p style="color:#64748b;font-size:0.85rem;margin-top:8px;">QR Code 掃碼付款專利技術</p>
        </div>
    </div>

    <footer style="text-align:center;padding:24px;color:#64748b;font-size:0.85rem;">
        &copy; 2026 AI 銷售員 — 台灣賣家專屬行銷平台
    </footer>
</body>
</html>
