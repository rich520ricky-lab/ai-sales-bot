<?php
/**
 * Add Product - v2 with AI copy generation
 */
require_once __DIR__ . '/../includes/db.php';
$user = requireLogin();

$success = '';
$error = '';

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
            
            // Upload image
            $imagePath = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $imagePath = uploadFile($_FILES['image'], 'uploads/products');
            }
            
            // Generate AI copy if not already generated
            if (empty($aiCopy)) {
                $aiCopy = generateAICopy($name, $description, $price);
            }
            
            // Generate QR code
            $qrPath = generateQRCode(0, $price, $name); // temp ID, will update
            
            $stmt = $db->prepare("INSERT INTO products (user_id, name, description, short_desc, price, image_path, ai_copy, qr_code_path, category, tags, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$user['id'], $name, $description, $shortDesc, $price, $imagePath, $aiCopy, $qrPath, $category, $tags]);
            $productId = $db->lastInsertId();
            
            // Update QR code with real product ID
            $realQrPath = generateQRCode($productId, $price, $name);
            $db->prepare("UPDATE products SET qr_code_path = ? WHERE id = ?")->execute([$realQrPath, $productId]);
            
            logActivity($user['id'], 'create_product', "新增產品: {$name}");
            
            header('Location: view.php?id=' . $productId . '&created=1');
            exit;
        } catch (Exception $e) {
            $error = '系統錯誤：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上傳產品 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <div class="container container-narrow" style="padding-top:40px;padding-bottom:60px;">
        <div class="breadcrumb">
            <a href="/">首頁</a>
            <span class="sep">›</span>
            <a href="list.php">產品列表</a>
            <span class="sep">›</span>
            <span>上傳產品</span>
        </div>

        <div class="form-card">
            <h2>📦 上傳新產品</h2>
            
            <?php if ($error): ?>
            <div class="alert alert-error">❌ <?= $error ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['welcome'])): ?>
            <div class="alert alert-success">🎉 註冊成功！歡迎來到 AI 銷售員，開始上傳你的第一個產品吧！</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="name">產品名稱 *</label>
                    <input type="text" id="name" name="name" required placeholder="例如：台灣高山烏龍茶">
                </div>

                <div class="form-group">
                    <label for="category">產品分類</label>
                    <select id="category" name="category">
                        <option value="">選擇分類</option>
                        <option value="food">食品/飲料</option>
                        <option value="clothing">服飾/配件</option>
                        <option value="electronics">3C/電子</option>
                        <option value="home">居家/生活</option>
                        <option value="beauty">美妝/保養</option>
                        <option value="mother">母嬰/玩具</option>
                        <option value="sports">運動/戶外</option>
                        <option value="other">其他</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">產品描述 *</label>
                    <textarea id="description" name="description" rows="5" required placeholder="描述你的產品特色、材質、產地、使用方式等資訊，越詳細 AI 產生的文案越好"></textarea>
                </div>

                <div class="form-group">
                    <label for="short_desc">一句話簡介（選填）</label>
                    <input type="text" id="short_desc" name="short_desc" placeholder="用一句話介紹你的產品，例如：來自阿里山的頂級烏龍茶">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="price">價格 (TWD) *</label>
                        <input type="number" id="price" name="price" step="1" required placeholder="例如：599">
                    </div>
                    <div class="form-group">
                        <label for="tags">標籤（逗號分隔）</label>
                        <input type="text" id="tags" name="tags" placeholder="例如：台灣茶, 烏龍茶, 伴手禮">
                    </div>
                </div>

                <div class="form-group">
                    <label for="image">產品照片</label>
                    <input type="file" id="image" name="image" accept="image/*">
                    <div class="hint">支援 JPG、PNG、WebP，建議 800x800 以上</div>
                </div>

                <!-- AI Copy Generator -->
                <div class="form-group ai-gen-area">
                    <label>🤖 AI 銷售文案</label>
                    <button type="button" class="btn btn-secondary ai-generate-btn" onclick="generateAICopy()">
                        <span class="ai-spinner" style="display:none;"></span> 🤖 AI 產生文案
                    </button>
                    <div class="hint">點擊按鈕讓 AI 自動產生吸引人的銷售文案，你也可以手動修改</div>

                    <div class="ai-preview">
                        <div id="ai-result"></div>
                        <div class="actions">
                            <button type="button" class="btn btn-primary btn-sm" onclick="document.querySelector('.ai-preview').classList.remove('show')">確定，使用此文案</button>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="generateAICopy()">重新產生</button>
                        </div>
                    </div>
                    <input type="hidden" id="ai_copy_hidden" name="ai_copy_hidden" value="">
                </div>

                <div class="form-actions">
                    <a href="list.php" class="btn btn-ghost">取消</a>
                    <button type="submit" name="submit" class="btn btn-primary btn-lg">🚀 上傳並產生素材</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
</body>
</html>