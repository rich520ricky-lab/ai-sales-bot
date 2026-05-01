<?php
/**
 * AI Salesbot - Register Page
 */
require_once __DIR__ . '/../includes/db.php';

$user = getCurrentUser();
if ($user) {
    header('Location: /');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $storeName = sanitize($_POST['store_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (!$email || !$password || !$storeName) {
        $error = '請填寫所有必填欄位';
    } elseif (strlen($password) < 6) {
        $error = '密碼至少需要6個字元';
    } else {
        try {
            $db = getDB();
            
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = '此 Email 已經註冊過了';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (email, password, store_name, phone) VALUES (?, ?, ?, ?)");
                $stmt->execute([$email, $hashedPassword, $storeName, $phone]);
                $userId = $db->lastInsertId();
                
                // Auto-login
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                $db->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)")
                    ->execute([$userId, $token, $expires]);
                
                setcookie('auth_token', $token, time() + SESSION_LIFETIME, '/', '', false, true);
                logActivity($userId, 'register', 'Email註冊');
                
                header('Location: /products/add.php?welcome=1');
                exit;
            }
        } catch (Exception $e) {
            $error = '系統錯誤，請稍後再試';
        }
    }
}

$googleClientId = getSetting('google_client_id');
$googleLoginUrl = '';
if ($googleClientId) {
    $redirectUri = SITE_URL . '/auth/google-callback.php';
    $googleLoginUrl = 'https://accounts.google.com/o/oauth2/v2/auth?'
        . 'client_id=' . urlencode($googleClientId)
        . '&redirect_uri=' . urlencode($redirectUri)
        . '&response_type=code'
        . '&scope=' . urlencode('email profile')
        . '&access_type=offline';
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>註冊 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
        <h1>建立帳號</h1>
        <p class="subtitle">免費開始使用 AI 銷售員</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($googleLoginUrl): ?>
        <a href="<?= $googleLoginUrl ?>" class="btn btn-google btn-block" style="justify-content:center;">
            <img src="https://www.google.com/favicon.ico" alt=""> 使用 Google 帳號註冊
        </a>
        
        <div class="auth-divider">或使用 Email 註冊</div>
        <?php endif; ?>
        
        <form method="POST" style="text-align:left;">
            <div class="form-group">
                <label for="store_name">商店名稱 *</label>
                <input type="text" id="store_name" name="store_name" required placeholder="例如：阿明茶行">
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" required placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label for="password">密碼 *</label>
                <input type="password" id="password" name="password" required minlength="6" placeholder="至少6個字元">
            </div>
            <div class="form-group">
                <label for="phone">手機號碼</label>
                <input type="tel" id="phone" name="phone" placeholder="例如：0912345678">
            </div>
            <button type="submit" name="register" class="btn btn-primary btn-block">🚀 免費註冊</button>
        </form>
        
        <p style="margin-top:20px;color:var(--text-muted);font-size:0.9rem;">
            已經有帳號？ <a href="login.php" style="color:var(--primary-light);">登入</a>
        </p>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>