<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>上傳產品 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📦 上傳新產品</h1>
            <nav>
                <a href="../index.php">首頁</a>
                <a href="list.php">產品列表</a>
            </nav>
        </header>

        <main>
            <form action="add.php" method="POST" enctype="multipart/form-data" class="product-form">
                <div class="form-group">
                    <label for="name">產品名稱 *</label>
                    <input type="text" id="name" name="name" required placeholder="例如：台灣高山烏龍茶">
                </div>

                <div class="form-group">
                    <label for="description">產品描述 *</label>
                    <textarea id="description" name="description" rows="5" required placeholder="描述你的產品特色、材質、產地等資訊"></textarea>
                </div>

                <div class="form-group">
                    <label for="price">價格 (TWD) *</label>
                    <input type="number" id="price" name="price" step="0.01" required placeholder="例如：599">
                </div>

                <div class="form-group">
                    <label for="image">產品照片</label>
                    <input type="file" id="image" name="image" accept="image/*">
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit" class="btn-primary">🚀 AI 自動產生素材</button>
                </div>
            </form>

            <?php
            if (isset($_POST['submit'])) {
                require_once '../includes/db.php';
                require_once '../includes/functions.php';

                $name = sanitize($_POST['name']);
                $description = sanitize($_POST['description']);
                $price = floatval($_POST['price']);

                // Upload image
                $imagePath = null;
                if (isset($_FILES['image'])) {
                    $imagePath = uploadFile($_FILES['image'], '../uploads/products');
                }

                // For now, use a placeholder seller_id = 1
                $sellerId = 1;

                try {
                    $db = getDB();
                    $stmt = $db->prepare("INSERT INTO products (seller_id, name, description, price, image_path) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$sellerId, $name, $description, $price, $imagePath]);
                    $productId = $db->lastInsertId();

                    // Auto-generate AI copy and QR code
                    $aiCopy = generateAICopy($name, $description);
                    $qrCode = generateQRCode($productId, $price);

                    // Update product with generated content
                    $update = $db->prepare("UPDATE products SET ai_copy = ?, qr_code_path = ? WHERE id = ?");
                    $update->execute([$aiCopy, $qrCode, $productId]);

                    echo '<div class="success">✅ 產品已上傳！AI 素材已自動產生。<br><a href="view.php?id=' . $productId . '" class="btn-secondary">查看素材</a></div>';
                } catch (Exception $e) {
                    echo '<div class="error">❌ 錯誤：' . $e->getMessage() . '</div>';
                }
            }
            ?>
        </main>
    </div>
</body>
</html>