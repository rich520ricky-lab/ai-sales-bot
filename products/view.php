<?php
/**
 * Product View v3 — 完整素材展示 + 多圖片輪播 + 影片播放
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image-processor.php';
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

// Increment view
$db->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);

// Get multi images
$images = getProductImages($id);

// Get video info
$hasVideo = $p['video_path'] && file_exists(__DIR__ . '/../' . $p['video_path']);
$videoStatus = $p['video_status'];

// Parse AI copy
$aiCopy = $p['ai_copy'] ?? '尚未產生';
$slogan = ''; $features = []; $copyText = ''; $keywords = '';

if (preg_match('/===標語===([\s\S]*?)===特色===/', $aiCopy, $m)) $slogan = trim($m[1]);
if (preg_match('/===特色===([\s\S]*?)===文案===/', $aiCopy, $m)) {
    $features = array_filter(array_map('trim', explode("\n", trim($m[1]))));
}
if (preg_match('/===文案===([\s\S]*?)===關鍵字===/', $aiCopy, $m)) $copyText = trim($m[1]);
if (preg_match('/===關鍵字===([\s\S]*?)$/', $aiCopy, $m)) $keywords = trim($m[1]);

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($p['name']) ?> — 素材 · AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/uploader.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <?php if ($created): ?>
        <div class="alert alert-success">🎉 產品已上傳！以下是 AI 為你產生的行銷素材</div>
        <?php endif; ?>
        <?php if ($updated): ?>
        <div class="alert alert-info">💾 產品已更新</div>
        <?php endif; ?>

        <div class="breadcrumb">
            <a href="/">首頁</a>
            <span class="sep">›</span>
            <a href="list.php">產品列表</a>
            <span class="sep">›</span>
            <span><?= htmlspecialchars($p['name']) ?></span>
        </div>

        <!-- Product Header -->
        <div class="card" style="margin-bottom:24px;">
            <div class="card-body" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                <!-- Main Image / Gallery -->
                <div style="flex:0 0 280px;">
                    <?php if (!empty($images)): ?>
                    <div class="gallery-main" style="position:relative;">
                        <img id="galleryMain" src="/<?= $images[0]['image_path'] ?>" alt="" 
                             style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);cursor:pointer;"
                             onclick="openLightbox(this.src)">
                        <?php if (count($images) > 1): ?>
                        <div class="gallery-thumbs" style="display:flex;gap:6px;margin-top:8px;overflow-x:auto;">
                            <?php foreach ($images as $img): ?>
                            <img src="/<?= $img['image_path'] ?>" alt="" 
                                 style="width:60px;height:60px;object-fit:cover;border-radius:6px;cursor:pointer;border:2px solid transparent;"
                                 onmouseenter="document.getElementById('galleryMain').src=this.src"
                                 onclick="openLightbox('/<?= $img['image_path'] ?>')">
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php elseif ($p['image_path']): ?>
                    <img src="/<?= $p['image_path'] ?>" alt="" 
                         style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--radius);cursor:pointer;"
                         onclick="openLightbox(this.src)">
                    <?php else: ?>
                    <div style="width:100%;aspect-ratio:1;background:var(--bg-input);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;font-size:3rem;">📸</div>
                    <?php endif; ?>
                </div>

                <div style="flex:1;">
                    <h1 style="font-size:1.5rem;"><?= htmlspecialchars($p['name']) ?></h1>
                    <?php if ($slogan): ?>
                    <p style="color:var(--primary-light);font-size:1.1rem;margin:8px 0;">🔥 <?= nl2br(htmlspecialchars($slogan)) ?></p>
                    <?php endif; ?>
                    <div style="display:flex;gap:16px;margin-top:12px;flex-wrap:wrap;">
                        <span style="font-size:1.5rem;font-weight:700;background:var(--gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;">
                            NT$ <?= number_format($p['price'], 0) ?>
                        </span>
                        <span class="badge badge-<?= $p['status'] ?>">
                            <?= ['draft'=>'草稿','active'=>'銷售中','archived'=>'已封存','rejected'=>'已拒絕'][$p['status']] ?? $p['status'] ?>
                        </span>
                        <span style="color:var(--text-dim);font-size:0.9rem;">👁️ <?= $p['views'] ?> 次瀏覽</span>
                        <?php if ($aiVideoStatus = $videoStatus): ?>
                            <?php if ($aiVideoStatus === 'done'): ?><span style="color:var(--success);font-size:0.9rem;">🎬 有影片</span>
                            <?php elseif ($aiVideoStatus === 'processing'): ?><span style="color:var(--accent);font-size:0.9rem;">🎬 影片處理中...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($p['ai_images_generated'] > 0): ?>
                            <span style="color:var(--primary-light);font-size:0.9rem;">🎨 AI 圖片 x<?= $p['ai_images_generated'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['category']): ?>
                    <span style="display:inline-block;margin-top:8px;padding:4px 12px;background:var(--bg-input);border-radius:20px;font-size:0.8rem;color:var(--text-muted);">
                        📂 <?= htmlspecialchars($p['category']) ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($p['description']): ?>
                    <p style="margin-top:12px;color:var(--text-muted);font-size:0.9rem;line-height:1.7;">
                        <?= nl2br(htmlspecialchars(mb_substr($p['description'], 0, 200))) ?>
                        <?= mb_strlen($p['description']) > 200 ? '...' : '' ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Materials Grid -->
        <div class="materials-grid">
            <!-- AI Copy -->
            <div class="material-card">
                <h3>✍️ 銷售文案</h3>
                <div class="copy-display" id="fullCopyText"><?= nl2br(htmlspecialchars($aiCopy)) ?></div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <button onclick="copyToClipboard(document.getElementById('fullCopyText').innerText, this)" class="btn btn-secondary btn-sm">📋 複製全部</button>
                    <?php if ($slogan): ?><button onclick="copyToClipboard('<?= escapeJS($slogan) ?>', this)" class="btn btn-ghost btn-sm">複製標語</button><?php endif; ?>
                    <?php if ($copyText): ?><button onclick="copyToClipboard('<?= escapeJS($copyText) ?>', this)" class="btn btn-ghost btn-sm">複製內文</button><?php endif; ?>
                </div>
                <div class="hint" style="margin-top:12px;">
                    <a href="add.php?edit=<?= $p['id'] ?>" style="color:var(--primary-light);font-size:0.85rem;">
                        ✏️ 編輯文案
                    </a>
                </div>
            </div>

            <!-- QR Code -->
            <div class="material-card">
                <h3>📱 銷售 QR Code</h3>
                <?php if ($p['qr_code_path']): ?>
                <div style="text-align:center;padding:16px 0;">
                    <img src="/<?= $p['qr_code_path'] ?>" alt="QR Code" style="max-width:240px;border-radius:12px;">
                    <p style="color:var(--text-muted);font-size:0.85rem;margin-top:8px;">掃碼即可付款購買</p>
                </div>
                <a href="/<?= $p['qr_code_path'] ?>" download class="btn btn-primary btn-sm btn-block">⬇️ 下載 QR Code</a>
                <?php else: ?>
                <p style="color:var(--text-dim);">QR Code 尚未產生，請重新儲存產品</p>
                <?php endif; ?>
            </div>

            <!-- AI Images Gallery -->
            <?php if (!empty($images) || $p['ai_images_generated'] > 0): ?>
            <div class="material-card">
                <h3>🎨 產品圖片集</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;">
                    <?php foreach ($images as $img): ?>
                    <img src="/<?= $img['image_path'] ?>" alt="" 
                         style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px;cursor:pointer;"
                         onclick="openLightbox('/<?= $img['image_path'] ?>')">
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Video -->
            <?php if ($hasVideo): ?>
            <div class="material-card">
                <h3>🎬 產品展示影片</h3>
                <div class="video-preview" style="max-width:100%;">
                    <video controls style="width:100%;border-radius:var(--radius);" <?= $created ? 'autoplay' : '' ?>>
                        <source src="/<?= $p['video_path'] ?>" type="video/mp4">
                    </video>
                    <div style="margin-top:12px;display:flex;gap:8px;">
                        <a href="/<?= $p['video_path'] ?>" download class="btn btn-primary btn-sm">⬇️ 下載影片</a>
                        <button class="btn btn-secondary btn-sm" onclick="showToast('📋 分享連結已複製')">📋 複製連結</button>
                    </div>
                </div>
            </div>
            <?php elseif ($p['video_status'] === 'processing'): ?>
            <div class="material-card">
                <h3>🎬 產品展示影片</h3>
                <div style="text-align:center;padding:24px;">
                    <span class="ai-spinner" style="display:inline-block;width:32px;height:32px;border-width:3px;"></span>
                    <p style="margin-top:12px;color:var(--text-muted);">影片正在生成中...</p>
                </div>
            </div>
            <?php endif; ?>

            <!-- SEO Keywords -->
            <div class="material-card">
                <h3>🔑 SEO 關鍵字</h3>
                <?php if ($keywords): ?>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
                    <?php foreach (explode(',', $keywords) as $kw): ?>
                    <span style="padding:6px 14px;background:var(--bg-input);border:1px solid var(--border);border-radius:20px;font-size:0.85rem;">
                        <?= htmlspecialchars(trim($kw)) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <button onclick="copyToClipboard('<?= escapeJS($keywords) ?>', this)" class="btn btn-secondary btn-sm">📋 複製關鍵字</button>
                <?php else: ?>
                <p style="color:var(--text-dim);">無關鍵字資訊</p>
                <?php endif; ?>
            </div>

            <!-- Ad Campaign -->
            <div class="material-card">
                <h3>📢 廣告投放</h3>
                <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:16px;">選擇平台與預算，一鍵開始投放</p>
                <form action="../api/ad-campaign.php" method="POST">
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

            <!-- Product Features -->
            <?php if (!empty($features)): ?>
            <div class="material-card">
                <h3>✨ 產品特色</h3>
                <ul style="list-style:none;padding:0;">
                    <?php foreach ($features as $f): ?>
                    <li style="padding:8px 0;border-bottom:1px solid var(--border);font-size:0.9rem;">
                        <?= nl2br(htmlspecialchars($f)) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- Management -->
            <div class="material-card">
                <h3>⚙️ 管理</h3>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="add.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">✏️ 編輯產品</a>
                    <a href="add.php?edit=<?= $p['id'] ?>" class="btn btn-secondary btn-sm">🎨 重新生成素材</a>
                    <?php if ($p['status'] === 'active'): ?>
                    <a href="?id=<?= $p['id'] ?>&action=archive" class="btn btn-ghost btn-sm" onclick="return confirm('確定封存此產品？')">📦 封存</a>
                    <?php else: ?>
                    <a href="?id=<?= $p['id'] ?>&action=activate" class="btn btn-primary btn-sm">🚀 上架</a>
                    <?php endif; ?>
                    <a href="?id=<?= $p['id'] ?>&action=delete" class="btn btn-danger btn-sm" onclick="return confirm('確定刪除？無法復原。')">🗑️ 刪除</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="this.classList.remove('show')">
        <button class="close-btn" onclick="document.getElementById('lightbox').classList.remove('show')">✕</button>
        <img id="lightboxImg" src="" alt="">
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/uploader.js"></script>
    <script>
    function openLightbox(src) {
        document.getElementById('lightboxImg').src = src;
        document.getElementById('lightbox').classList.add('show');
    }
    </script>
</body>
</html>