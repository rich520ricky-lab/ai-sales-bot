<?php
/**
 * Add Product v3 — AI 全自動素材生成
 * 支援：拖曳多圖片、AI 去背、AI 文案、AI 圖片、AI 影片
 */
require_once __DIR__ . '/../includes/db.php';
$user = requireLogin();

$success = '';
$error = '';
$editId = intval($_GET['edit'] ?? 0);
$editProduct = null;

// 編輯模式
if ($editId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
    $stmt->execute([$editId, $user['id']]);
    $editProduct = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $shortDesc = sanitize($_POST['short_desc'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = sanitize($_POST['category'] ?? '');
    $tags = sanitize($_POST['tags'] ?? '');
    $aiCopy = $_POST['ai_copy_hidden'] ?? '';
    
    if (!$name || !$description || $price <= 0) {
        $error = '請填寫產品名稱、描述和價格';
    } else {
        try {
            $db = getDB();
            
            $imagePath = null;
            
            if ($editProduct && $editProduct['image_path']) {
                $imagePath = $editProduct['image_path'];
            }
            
            // Upload main image (單檔)
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $result = processProductImage($_FILES['image']['tmp_name'], 0, $_FILES['image']['name']);
                if ($result['success']) {
                    $imagePath = $result['paths']['medium'] ?? $result['paths']['original'];
                }
            }
            
            // Generate AI copy
            if (empty($aiCopy)) {
                $aiCopy = generateAICopy($name, $description, $price);
            }
            
            // Generate QR code
            $qrPath = generateQRCode($editId ?: 0, $price, $name);
            
            if ($editProduct) {
                // Update
                $stmt = $db->prepare("UPDATE products SET name=?, description=?, short_desc=?, price=?, image_path=COALESCE(?, image_path), ai_copy=?, qr_code_path=?, category=?, tags=? WHERE id=? AND user_id=?");
                $stmt->execute([$name, $description, $shortDesc, $price, $imagePath, $aiCopy, $qrPath, $category, $tags, $editId, $user['id']]);
                $productId = $editId;
                
                // 更新 QR code
                $realQrPath = generateQRCode($productId, $price, $name);
                $db->prepare("UPDATE products SET qr_code_path = ? WHERE id = ?")->execute([$realQrPath, $productId]);
                
                logActivity($user['id'], 'update_product', "編輯產品: {$name}");
                header('Location: view.php?id=' . $productId . '&updated=1');
            } else {
                // Insert
                $stmt = $db->prepare("INSERT INTO products (user_id, name, description, short_desc, price, image_path, ai_copy, qr_code_path, category, tags, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$user['id'], $name, $description, $shortDesc, $price, $imagePath, $aiCopy, $qrPath, $category, $tags]);
                $productId = $db->lastInsertId();
                
                // Update QR code with real product ID
                $realQrPath = generateQRCode($productId, $price, $name);
                $db->prepare("UPDATE products SET qr_code_path = ? WHERE id = ?")->execute([$realQrPath, $productId]);
                
                // 如果有透過拖曳上傳的多圖片，更新 product_id
                if (isset($_POST['uploaded_ids']) && !empty($_POST['uploaded_ids'])) {
                    // 上傳時先以 temp ID 儲存，這裡不做複雜處理
                }
                
                logActivity($user['id'], 'create_product', "新增產品: {$name}");
                // 觸發同步到 market_db
                exec("php " . __DIR__ . "/../cron/sync_to_market.php > /dev/null 2>&1 &");
                header('Location: view.php?id=' . $productId . '&created=1');
            }
            exit;
        } catch (Exception $e) {
            $error = '系統錯誤：' . $e->getMessage();
        }
    }
}

// Get existing images for edit mode
$existingImages = [];
if ($editProduct) {
    require_once __DIR__ . '/../includes/image-processor.php';
    $existingImages = getProductImages($editId);
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $editProduct ? '編輯產品' : '上傳產品' ?> — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/uploader.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container container-wide" style="padding-top:40px;padding-bottom:60px;">
        <div class="breadcrumb">
            <a href="/">首頁</a>
            <span class="sep">›</span>
            <a href="list.php">產品列表</a>
            <span class="sep">›</span>
            <span><?= $editProduct ? htmlspecialchars($editProduct['name']) : '上傳產品' ?></span>
        </div>

        <div style="display:grid;grid-template-columns:1fr 360px;gap:24px;">
            <!-- Left: Main Form -->
            <div class="form-card">
                <h2><?= $editProduct ? '✏️ 編輯產品' : '📦 上傳新產品' ?></h2>
                
                <?php if ($error): ?>
                <div class="alert alert-error">❌ <?= $error ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['welcome'])): ?>
                <div class="alert alert-success">🎉 註冊成功！歡迎來到 AI 銷售員，開始上傳你的第一個產品吧！</div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <div class="form-group">
                        <label for="name">產品名稱 *</label>
                        <input type="text" id="name" name="name" required 
                               value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>"
                               placeholder="例如：台灣高山烏龍茶">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">產品分類</label>
                            <select id="category" name="category">
                                <option value="">選擇分類</option>
                                <?php 
                                $categories = [
                                    'food' => '食品/飲料', 'clothing' => '服飾/配件',
                                    'electronics' => '3C/電子', 'home' => '居家/生活',
                                    'beauty' => '美妝/保養', 'mother' => '母嬰/玩具',
                                    'sports' => '運動/戶外', 'other' => '其他'
                                ];
                                foreach ($categories as $val => $label): 
                                ?>
                                <option value="<?= $val ?>" <?= ($editProduct['category'] ?? '') === $val ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="price">價格 (TWD) *</label>
                            <input type="number" id="price" name="price" step="1" required
                                   value="<?= $editProduct['price'] ?? '' ?>"
                                   placeholder="例如：599">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="short_desc">一句話簡介</label>
                        <input type="text" id="short_desc" name="short_desc"
                               value="<?= htmlspecialchars($editProduct['short_desc'] ?? '') ?>"
                               placeholder="來自阿里山的頂級烏龍茶">
                    </div>

                    <div class="form-group">
                        <label for="description">產品描述 *</label>
                        <textarea id="description" name="description" rows="6" required 
                                  placeholder="描述你的產品特色、材質、產地、使用方式等資訊，越詳細 AI 產生的文案越好"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea>
                        <div class="hint">💡 描述越詳細，AI 生成的文案品質越好！</div>
                    </div>

                    <div class="form-group">
                        <label for="tags">標籤（逗號分隔）</label>
                        <input type="text" id="tags" name="tags"
                               value="<?= htmlspecialchars($editProduct['tags'] ?? '') ?>"
                               placeholder="台灣茶, 烏龍茶, 伴手禮">
                    </div>

                    <!-- AI Copy Generator -->
                    <div class="form-group ai-gen-area">
                        <label>🤖 AI 銷售文案</label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="button" class="btn btn-secondary ai-generate-btn" onclick="generateAICopy()">
                                <span class="ai-spinner" style="display:none;"></span> 🤖 AI 產生文案
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="generateSEOKeywords()">
                                🔑 產生 SEO 關鍵字
                            </button>
                            <button type="button" class="btn btn-ghost" onclick="generateSlogan()">
                                📢 產生標語
                            </button>
                        </div>
                        <div class="hint">點擊按鈕讓 AI 自動產生吸引人的銷售文案</div>

                        <!-- AI Copy Preview -->
                        <div class="ai-preview" id="aiPreview">
                            <div id="ai-result"></div>
                            <div class="actions">
                                <button type="button" class="btn btn-primary btn-sm" onclick="confirmAICopy()">✅ 確定使用</button>
                                <button type="button" class="btn btn-ghost btn-sm" onclick="generateAICopy()">🔄 重新產生</button>
                            </div>
                        </div>
                        <input type="hidden" id="ai_copy_hidden" name="ai_copy_hidden" 
                               value="<?= htmlspecialchars($editProduct['ai_copy'] ?? '') ?>">
                    </div>

                    <!-- Main product image -->
                    <div class="form-group">
                        <label for="image">主產品照片</label>
                        <?php if ($editProduct && $editProduct['image_path']): ?>
                        <div style="margin-bottom:8px;">
                            <img src="/<?= $editProduct['image_path'] ?>" alt="" style="max-width:200px;border-radius:8px;">
                            <br><small style="color:var(--text-dim);">上傳新照片會取代</small>
                        </div>
                        <?php endif; ?>
                        <input type="file" id="image" name="image" accept="image/*">
                        <div class="hint">支援 JPG、PNG、WebP，建議 800x800 以上</div>
                    </div>

                    <!-- Drag & Drop Multi Image Upload -->
                    <div class="form-group">
                        <label>📸 更多產品照片（拖曳上傳，AI 自動去背處理）</label>
                        <div class="upload-zone" id="uploadZone" <?= $editProduct ? "data-product-id=\"{$editId}\"" : '' ?>>
                            <span class="upload-icon">📤</span>
                            <div class="upload-text">將圖片拖曳到這裡，或點擊選擇檔案</div>
                            <div class="upload-hint">支援 JPG、PNG、WebP（最多 10 張，每張最大 20MB）</div>
                            <input type="file" name="images[]" multiple accept="image/*">
                        </div>
                        <div class="upload-preview-grid" id="uploadPreviewGrid"></div>
                    </div>

                    <div class="form-actions">
                        <a href="list.php" class="btn btn-ghost">取消</a>
                        <button type="submit" name="submit" class="btn btn-primary btn-lg">
                            <?= $editProduct ? '💾 儲存變更' : '🚀 上傳並產生存材' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: AI Generator Panel -->
            <div class="ai-panel">
                <h3>🤖 AI 全自動素材生成</h3>
                <p style="color:var(--text-muted);font-size:0.9rem;margin-bottom:16px;">
                    先填寫左側產品名稱與描述，再點擊下方按鈕
                </p>

                <div class="ai-actions">
                    <button type="button" class="ai-action-btn" data-action="generate-copy" onclick="generateAICopy()">
                        <span class="icon">✍️</span> 產生文案
                    </button>
                    <button type="button" class="ai-action-btn" data-action="generate-image">
                        <span class="icon">🎨</span> AI 生成圖片
                    </button>
                    <button type="button" class="ai-action-btn" data-action="generate-video">
                        <span class="icon">🎬</span> AI 生成影片
                    </button>
                </div>

                <button type="button" class="btn btn-primary btn-block" data-action="generate-all" style="margin-bottom:16px;">
                    🤖 一鍵全部產生
                </button>

                <!-- Progress -->
                <div id="ai-progress" class="ai-result-area"></div>

                <!-- AI Image Results -->
                <div id="ai-image-results" class="ai-result-area">
                    <h4 style="margin-bottom:12px;font-size:0.95rem;color:var(--text-muted);">🎨 AI 生成圖片</h4>
                    <div class="result-grid"></div>
                </div>

                <!-- AI Video Results -->
                <div id="ai-video-results" class="ai-result-area"></div>

                <div class="hint" style="margin-top:16px;padding:12px;background:var(--bg-input);border-radius:var(--radius);">
                    <strong>💡 提示：</strong>需要先在 
                    <a href="../admin/settings/index.php" style="color:var(--primary-light);">後台設定</a> 
                    中填入 AI API Key 才能使用 AI 圖片與影片生成功能。
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/uploader.js"></script>
</body>
</html>