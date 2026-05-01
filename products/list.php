<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>產品列表 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📋 我的產品</h1>
            <nav>
                <a href="../index.php">首頁</a>
                <a href="add.php">上傳新產品</a>
            </nav>
        </header>

        <main>
            <?php
            require_once '../includes/db.php';
            $db = getDB();
            $products = $db->query("SELECT * FROM products ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
            ?>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <p>還沒有產品 🫙</p>
                    <a href="add.php" class="btn-primary">上傳第一個產品</a>
                </div>
            <?php else: ?>
                <div class="product-grid">
                    <?php foreach ($products as $p): ?>
                        <div class="product-card">
                            <?php if ($p['image_path']): ?>
                                <img src="../<?= $p['image_path'] ?>" alt="<?= $p['name'] ?>" class="product-thumb">
                            <?php endif; ?>
                            <h3><?= htmlspecialchars($p['name']) ?></h3>
                            <p class="price">NT$ <?= number_format($p['price'], 0) ?></p>
                            <span class="status <?= $p['status'] ?>"><?= $p['status'] ?></span>
                            <a href="view.php?id=<?= $p['id'] ?>" class="btn-secondary">查看素材</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>