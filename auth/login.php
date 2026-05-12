<?php
/**
 * AI Salesbot - Login Page
 * Supports: Email/Password login, Google OAuth
 */
require_once __DIR__ . '/../includes/db.php';

// Redirect if already logged in
$user = getCurrentUser();
if ($user) {
    header('Location: /');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Create session
                $token = generateToken();
                $expires = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
                
                $stmt = $db->prepare("INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$user['id'], $token, $expires]);
                
                setcookie('auth_token', $token, time() + SESSION_LIFETIME, '/', '', false, true);
                
                // Update last login
                $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                logActivity($user['id'], 'login', 'Email登入');
                
                header('Location: /');
                exit;
            } else {
                $error = 'Email 或密碼錯誤';
            }
        } catch (Exception $e) {
            $error = '系統錯誤，請稍後再試';
        }
    } else {
        $error = '請填寫 Email 和密碼';
    }
}

// Google OAuth URL
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
    <title>登入 — AI 銷售員</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-card">
        <a href="/" class="logo">🤖 <span>AI</span>銷售員</a>
        <h1>歡迎回來</h1>
        <p class="subtitle">登入你的帳號開始銷售</p>
        
        <?php if ($error): ?>
        <div class="alert alert-error">❌ <?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($googleLoginUrl): ?>
        <a href="<?= $googleLoginUrl ?>" class="btn btn-google btn-block" style="justify-content:center;">
            <img src="https://www.google.com/favicon.ico" alt=""> 使用 Google 帳號登入
        </a>
        
        <div class="auth-divider">或使用 Email 登入</div>
        <?php endif; ?>
        
        <form method="POST" style="text-align:left;">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required placeholder="your@email.com">
            </div>
            <div class="form-group">
                <label for="password">密碼</label>
                <input type="password" id="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" name="login" class="btn btn-primary btn-block">登入</button>
        </form>
        
        <p style="margin-top:20px;color:var(--text-muted);font-size:0.9rem;">
            還沒有帳號？ <a href="register.php" style="color:var(--primary-light);">立即註冊</a>
        </p>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>