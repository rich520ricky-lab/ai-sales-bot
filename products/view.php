<?php
/**
 * Product View - v2 with full materials display
 */
require_once __DIR__ . '/../includes/db.php';
$user = requireLogin();

$id = intval($_GET['id'] ?? 0);
$db = getDB();

$stmt = $db->prepare("SELECT p.*, u.store_name, u.email FROM products p JOIN users u ON p.user_id = u.id WHERE p.id = ? AND p.user_id = ?");
$stmt->execute([$id, $user['id']]);
$p = $stmt->fetch();

if (!$p) {
    header('Location: list.php');
    exit;
}

// Increment view count
$db->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);

$created = isset($_GET['created']);

// Parse AI copy sections
$aiCopy = $p['ai_copy'] ?? '尚未產生';
$slogan = '';
$features = [];
$copyText = '';
$keywords = '';

if (preg_match('/===標語===([\s\S]*?)===特色===/', $aiCopy, $m)) {
    $slogan = trim($m[1]);
}
if (preg_match('/===特色===([\s\S]*?)===文案===/', $aiCopy, $m)) {
    $features = array_filter(explode("\n", trim($m[1])));
    $features = array_map('trim', $features);
    $features = array_filter($features);
}
if (preg_match('/===文案===([\s\S]*?)===關鍵字===/', $aiCopy, $m)) {
    $copyText = trim($m[1]);
}
if (preg_match('/===關鍵字===([\s\S]*?)$/', $aiCopy, $m)) {
    $keywords = trim($m[1]);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['name']) ?> — 素材 · AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container" style="padding-top:40px;padding-bottom:60px;">
        <?php if ($created): ?>
        <div class="alert alert-success">🎉 產品已上傳！以下是 AI 為你產生的行銷素材</div>
        <?php endif; ?>

        <div class="breadcrumb">
            <a href="/">首頁</a>
            <span class="sep">›</span>
            <a href="list.php">產品列表</a>
            <span class="sep">›</span>
            <span><?= htmlspecialchars($p['name']) ?></span>
        </div>

        <!-- Product Info -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                <?php if ($p['image_path']): ?>
                <img src="/<?= $p['image_path'] ?>" alt="" style="width:200px;height:200px;object-fit:cover;border-radius:var(--radius);flex-shrink:0;">
                <?php endif; ?>
                <div style="flex:1;">
                    <h1 style="font-size:1.5rem;"><?= htmlspecialchars($p['name']) ?></h1>
                    <?php if ($slogan): ?>
                    <p style="color:var(--primary-light);font-size:1.1rem;margin:8px 0;">🔥 <?= nl2br(htmlspecialchars($slogan)) ?></p>
                    <?php endif; ?>
                    <div style="display:flex;gap:16px;margin-top:12px;flex-wrap:wrap;">
                        <span style="font-size:1.5rem;font-weight:700;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">NT$ <?= number_format($p['price'], 0) ?></span>
                        <span class="badge badge-<?= $p['status'] ?>"><?= ['draft'=>'草稿','active'=>'銷售中','archived'=>'已封存','rejected'=>'已拒絕'][$p['status']] ?></span>
                        <span style="color:var(--text-dim);font-size:0.9rem;">👁️ <?= $p['views'] ?> 次瀏覽</span>
                    </div>
                    <?php if ($p['category']): ?>
                    <span style="display:inline-block;margin-top:8px;padding:4px 12px;background:var(--bg-input);border-radius:20px;font-size:0.8rem;color:var(--text-muted);">📂 <?= htmlspecialchars($p['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Materials -->
        <div class="materials-grid">
            <!-- AI Copy -->
            <div class="material-card">
                <h3>✍️ 銷售文案</h3>
                <div class="copy-display" id="fullCopyText"><?= nl2br(htmlspecialchars($aiCopy)) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button onclick="copyToClipboard(document.getElementById('fullCopyText').innerText, this)" class="btn btn-secondary btn-sm">📋 複製全部文案</button>
                    <button onclick="copyToClipboard('<?= escapeJS($slogan) ?>', this)" class="btn btn-ghost btn-sm">複製標語</button>
                    <button onclick="copyToClipboard('<?= escapeJS($copyText) ?>', this)" class="btn btn-ghost btn-sm">複製內文</button>
                </div>
            </div>

            <!-- QR Code -->
            <div class="material-card">
                <h3>📱 銷售 QR Code</h3>
                <?php if ($p['qr_code_path']): ?>
                <div style="text-align:center;padding:16px 0;">
                    <img src="/<?= $p['qr_code_path'] ?>" alt="QR Code" style="max-width:240px;border-radius:12px;">
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-top:8px;">掃碼即可付款購買</p>
                    <p style="color:var(--text-dim);font-size:0.8rem;">支援 LINE Pay / 街口 / 台灣 Pay</p>
                </div>
                <a href="/<?= $p['qr_code_path'] ?>" download class="btn btn-primary btn-sm btn-block">⬇️ 下載 QR Code</a>
                <?php else: ?>
                <p style="color:var(--text-dim);">QR Code 尚未產生</p>
                <?php endif; ?>
            </div>

            <!-- Product Photo -->
            <div class="material-card">
                <h3>📸 產品照片</h3>
                <?php if ($p['image_path']): ?>
                <div style="text-align:center;padding:16px 0;">
                    <img src="/<?= $p['image_path'] ?>" alt="" style="max-width:100%;max-height:300px;border-radius:12px;">
                </div>
                <a href="/<?= $p['image_path'] ?>" download class="btn btn-secondary btn-sm btn-block">⬇️ 下載照片</a>
                <?php else: ?>
                <p style="color:var(--text-dim);">尚未上傳產品照片</p>
                <?php endif; ?>
            </div>

            <!-- Ad Campaign -->
            <div class="material-card">
                <h3>📢 廣告投放</h3>
                <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:16px;">選擇平台與預算，一鍵開始投放</p>
                <form action="../api/ad-campaign.php" method="POST" class="ad-form">
                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                    <div class="form-group">
                        <select name="platform" required>
                            <option value="">選擇投放平台</option>
                            <option value="facebook">Facebook / Instagram</option>
                            <option value="google">Google Ads</option>
                            <option value="line">LINE 廣告</option>
                            <option value="tv">電視廣告</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="number" name="budget" placeholder="預算 (TWD)" step="100" min="100" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">🚀 建立廣告活動</button>
                </form>
            </div>

            <!-- SEO Keywords -->
            <div class="material-card">
                <h3>🔑 SEO 關鍵字</h3>
                <?php if ($keywords): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                    <?php foreach (explode(',', $keywords) as $kw): ?>
                    <span style="padding:6px 14px;background:var(--bg-input);border:1px solid var(--border);border-radius:20px;font-size:0.85rem;"><?= htmlspecialchars(trim($kw)) ?></span>
                    <?php endforeach; ?>
                </div>
                <button onclick="copyToClipboard('<?= escapeJS($keywords) ?>', this)" class="btn btn-secondary btn-sm">📋 複製關鍵字</button>
                <?php else: ?>
                <p style="color:var(--text-dim);">無關鍵字資訊</p>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="material-card">
                <h3>⚙️ 管理</h3>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="add.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">✏️ 編輯產品</a>
                    <?php if ($p['status'] === 'active'): ?>
                    <a href="?id=<?= $p['id'] ?>&action=archive" class="btn btn-ghost btn-sm" onclick="return confirm('確定封存此產品？')">📦 封存產品</a>
                    <?php else: ?>
                    <a href="?id=<?= $p['id'] ?>&action=activate" class="btn btn-primary btn-sm">🚀 上架產品</a>
                    <?php endif; ?>
                    <a href="?id=<?= $p['id'] ?>&action=delete" class="btn btn-danger btn-sm" onclick="return confirm('確定刪除此產品？此操作無法復原。')">🗑️ 刪除產品</a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/app.js"></script>
</body>
</html>