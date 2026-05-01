<?php
/**
 * AI Salesbot - InfinityFree Deployer
 * 
 * Run this script ONCE from your browser after uploading it to InfinityFree.
 * It will clone the entire GitHub repo into your htdocs folder.
 * 
 * Steps:
 * 1. Upload THIS FILE ONLY to InfinityFree via cPanel File Manager
 * 2. Visit https://aisale.gt.tc/deploy.php
 * 3. Delete this file after deployment
 */

// Your GitHub repo info
$GITHUB_REPO = 'rich520ricky-lab/ai-sales-bot';
$BRANCH = 'main';

echo "<!DOCTYPE html><html lang='zh-TW'><head><meta charset='UTF-8'>";
echo "<title>AI Salesbot 部署工具</title>";
echo "<style>
body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 20px; background: #0f172a; color: #e2e8f0; }
h1 { color: #6366f1; }
pre { background: #1e293b; padding: 15px; border-radius: 8px; overflow-x: auto; }
.success { color: #22c55e; }
.error { color: #ef4444; }
.info { color: #94a3b8; }
</style></head><body>";
echo "<h1>🚀 AI Salesbot 部署工具</h1>";

// Check if already deployed
if (file_exists('index.php') && filesize('index.php') > 100) {
    echo "<p class='info'>⚠️ 看起來已經有 index.php 了！</p>";
    echo "<p>如果你要重新部署，請先刪除所有檔案（除了 deploy.php），然後重整此頁。</p>";
    echo "<p><a href='index.php' style='color: #6366f1;'>→ 前往網站</a></p>";
    echo "</body></html>";
    exit;
}

echo "<h2>步驟 1: 從 GitHub 下載原始碼...</h2>";
echo "<pre>";

// Method 1: Download ZIP
$url = "https://github.com/{$GITHUB_REPO}/archive/refs/heads/{$BRANCH}.zip";
$zipFile = 'temp_repo.zip';

echo "下載中: {$url}\n";

$ch = curl_init($url);
$fp = fopen($zipFile, 'w');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
fclose($fp);

if ($httpCode !== 200) {
    echo "<span class='error'>❌ 下載失敗 (HTTP {$httpCode})</span>\n";
    echo "</pre></body></html>";
    exit;
}

echo "<span class='success'>✅ 下載成功！</span>\n";

// Method 2: Extract ZIP
echo "解壓縮中...\n";

$zip = new ZipArchive;
if ($zip->open($zipFile) !== TRUE) {
    echo "<span class='error'>❌ 無法開啟 ZIP 檔案</span>\n";
    echo "</pre></body></html>";
    exit;
}

// Extract to a temp folder first
$extractPath = 'temp_extract';
if (!file_exists($extractPath)) {
    mkdir($extractPath, 0755, true);
}

$zip->extractTo($extractPath);
$zip->close();

// Find the extracted folder (it's usually repo-branch)
$items = scandir($extractPath);
$sourceDir = null;
foreach ($items as $item) {
    if ($item !== '.' && $item !== '..' && is_dir("{$extractPath}/{$item}")) {
        $sourceDir = "{$extractPath}/{$item}";
        break;
    }
}

if (!$sourceDir) {
    echo "<span class='error'>❌ 找不到解壓縮後的資料夾</span>\n";
    echo "</pre></body></html>";
    exit;
}

echo "<span class='success'>✅ 解壓縮成功！</span>\n";

// Move files from sourceDir to current directory
echo "移動檔案中...\n";

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($files as $fileinfo) {
    if ($fileinfo->isDir()) {
        $relativePath = substr($fileinfo->getRealPath(), strlen($sourceDir) + 1);
        $targetDir = __DIR__ . '/' . $relativePath;
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
    } else {
        $relativePath = substr($fileinfo->getRealPath(), strlen($sourceDir) + 1);
        copy($fileinfo->getRealPath(), __DIR__ . '/' . $relativePath);
        $count++;
    }
}

echo "<span class='success'>✅ 已移動 {$count} 個檔案</span>\n";

// Clean up temp files
echo "清理暫存檔案...\n";
array_map('unlink', glob("{$extractPath}/*/*"));
array_map('rmdir', glob("{$extractPath}/*"));
rmdir($extractPath);
unlink($zipFile);

echo "<span class='success'>✅ 清理完成！</span>\n";
echo "</pre>";

echo "<h2>步驟 2: 設定資料庫</h2>";
echo "<p>請在 InfinityFree cPanel 建立 MySQL 資料庫：</p>";
echo "<ol>";
echo "<li>登入 <a href='https://cpanel.ct8.net' target='_blank' style='color: #6366f1;'>cPanel</a></li>";
echo "<li>找到 <strong>MySQL Databases</strong></li>";
echo "<li>建立一個新資料庫 + 使用者</li>";
echo "<li>編輯 <code>includes/db.php</code> 填入資料庫資訊</li>";
echo "<li>在 phpMyAdmin 中執行 <code>sql/schema.sql</code></li>";
echo "</ol>";

echo "<h2>🎉 部署完成！</h2>";
echo "<p><a href='index.php' style='color: #6366f1; font-size: 1.2rem;'>→ 前往 AI 銷售員平台</a></p>";
echo "<p class='info'>💡 安全提示：部署完成後請刪除此 deploy.php 檔案</p>";

echo "</body></html>";