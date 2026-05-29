<?php
/**
 * Admin - Settings
 */
session_start();
require_once __DIR__ . '/../../includes/db.php';
$admin = requireAdmin();

$db = getDB();
$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        http_response_code(403);
        die('無效的請求令牌');
    }

    $fields = ['site_name', 'site_description', 'admin_email', 'google_client_id', 'google_client_secret', 'ai_api_key', 'ai_model'];

    $adminEmail = trim($_POST['admin_email'] ?? '');
    if ($adminEmail !== '' && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = '管理員 Email 格式不正確';
    }

    $allowedModels = ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo'];
    $aiModel = $_POST['ai_model'] ?? '';
    if ($aiModel !== '' && !in_array($aiModel, $allowedModels, true)) {
        $errors[] = '無效的 AI 模型選擇';
    }

    if (empty($errors)) {
        foreach ($fields as $key) {
            $value = trim($_POST[$key] ?? '');
            updateSetting($key, $value);
        }

        $saved = true;
        logActivity($admin['id'], 'update_settings', '更新系統設定');
    }
}

// Load current settings
$settings = [];
$stmt = $db->query("SELECT * FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key']] = $row['value'];
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系統設定 — 管理後台 · AI 銷售員</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container container-narrow" style="padding-top:40px;padding-bottom:60px;">
        <div class="breadcrumb">
            <a href="../dashboard.php">管理後台</a>
            <span class="sep">›</span>
            <span>系統設定</span>
        </div>

        <div class="form-card">
            <h2>⚙️ 系統設定</h2>
            
            <?php if ($saved): ?>
            <div class="alert alert-success">設定已儲存</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?>
                <p><?= htmlspecialchars($err) ?></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

                <h3 style="font-size:1.1rem;margin-bottom:16px;color:var(--text-muted);">📌 基本設定</h3>
                
                <div class="form-group">
                    <label for="site_name">網站名稱</label>
                    <input type="text" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="site_description">網站描述</label>
                    <textarea id="site_description" name="site_description" rows="2"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="admin_email">管理員 Email</label>
                    <input type="email" id="admin_email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>">
                </div>

                <hr style="border-color:var(--border);margin:24px 0;">

                <h3 style="font-size:1.1rem;margin-bottom:16px;color:var(--text-muted);">🔵 Google 登入設定</h3>
                <p style="font-size:0.85rem;color:var(--text-dim);margin-bottom:16px;">
                    前往 <a href="https://console.cloud.google.com/apis/credentials" target="_blank" style="color:var(--primary-light);">Google Cloud Console</a> 
                    建立 OAuth 2.0 憑證，將授權重新導向 URI 設為：<br>
                    <code style="background:var(--bg-input);padding:4px 8px;border-radius:4px;font-size:0.8rem;"><?= htmlspecialchars(SITE_URL) ?>/auth/google-callback.php</code>
                </p>
                
                <div class="form-group">
                    <label for="google_client_id">Google Client ID</label>
                    <input type="text" id="google_client_id" name="google_client_id" value="<?= htmlspecialchars($settings['google_client_id'] ?? '') ?>" placeholder="123456789-xxxxx.apps.googleusercontent.com">
                </div>
                
                <div class="form-group">
                    <label for="google_client_secret">Google Client Secret</label>
                    <input type="password" id="google_client_secret" name="google_client_secret" value="<?= htmlspecialchars($settings['google_client_secret'] ?? '') ?>" placeholder="GOCSPX-xxxxx">
                </div>

                <hr style="border-color:var(--border);margin:24px 0;">

                <h3 style="font-size:1.1rem;margin-bottom:16px;color:var(--text-muted);">🤖 AI 文案設定</h3>
                <p style="font-size:0.85rem;color:var(--text-dim);margin-bottom:16px;">
                    使用 OpenAI API 產生更優質的銷售文案。不設定則使用預設範本。
                </p>
                
                <div class="form-group">
                    <label for="ai_api_key">OpenAI API Key</label>
                    <input type="password" id="ai_api_key" name="ai_api_key" value="<?= htmlspecialchars($settings['ai_api_key'] ?? '') ?>" placeholder="sk-...">
                    <div class="hint">在 <a href="https://platform.openai.com/api-keys" target="_blank" style="color:var(--primary-light);">platform.openai.com</a> 取得</div>
                </div>
                
                <div class="form-group">
                    <label for="ai_model">AI 模型</label>
                    <select id="ai_model" name="ai_model">
                        <option value="gpt-4o-mini" <?= ($settings['ai_model'] ?? '') === 'gpt-4o-mini' ? 'selected' : '' ?>>GPT-4o Mini（快速經濟）</option>
                        <option value="gpt-4o" <?= ($settings['ai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o（高品質）</option>
                        <option value="gpt-4-turbo" <?= ($settings['ai_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-lg">💾 儲存設定</button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
