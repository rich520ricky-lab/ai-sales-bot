<?php
// Simple admin login
session_start();

if (isset($_POST['login'])) {
    $password = $_POST['password'] ?? '';
    // Simple password check - change this in production!
    if ($password === 'admin123') {
        $_SESSION['admin'] = true;
        header('Location: ../index.php');
        exit;
    } else {
        $error = '密碼錯誤';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理登入 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>🔐 管理登入</h1>
        </header>
        <main>
            <form method="POST" class="product-form" style="max-width: 400px;">
                <div class="form-group">
                    <label for="password">管理密碼</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <?php if (isset($error)): ?>
                    <div class="error">❌ <?= $error ?></div>
                <?php endif; ?>
                <div class="form-actions">
                    <button type="submit" name="login" class="btn-primary">登入</button>
                </div>
            </form>
        </main>
    </div>
</body>
</html>