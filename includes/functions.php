<?php
/**
 * AI Salesbot - Utility Functions
 */

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeHTML($input) {
    return strip_tags($input, '<b><strong><i><em><u><a><br><p><ul><ol><li><span><div>');
}

function escapeJS($string) {
    return str_replace(['\\', "'", '"', "\n", "\r"], ['\\\\', "\\'", '\\"', '\\n', '\\r'], $string);
}

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

function uploadFile($file, $targetDir) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return null;
    }
    
    $filename = uniqid('prod_') . '.' . $ext;
    $targetPath = rtrim($targetDir, '/') . '/' . $filename;
    $fullPath = __DIR__ . '/../' . $targetPath;
    
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return $targetPath;
    }
    return null;
}

function generateQRCode($productId, $price, $productName) {
    $paymentUrl = SITE_URL . '/api/pay.php?id=' . $productId . '&amount=' . $price;
    $qrApi = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' . urlencode($paymentUrl);
    $qrFilename = 'qr_' . $productId . '_' . uniqid() . '.png';
    $qrPath = 'uploads/qrcodes/' . $qrFilename;
    $fullPath = __DIR__ . '/../' . $qrPath;
    
    $dir = dirname($fullPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Download QR code
    $ch = curl_init($qrApi);
    $fp = fopen($fullPath, 'w');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    
    return $qrPath;
}

function generateAICopy($productName, $description, $productPrice = null) {
    $apiKey = getSetting('ai_api_key');
    $model = getSetting('ai_model') ?: 'gpt-4o-mini';
    
    // If no API key configured, use template
    if (empty($apiKey)) {
        return generateTemplateCopy($productName, $description, $productPrice);
    }
    
    $prompt = "你是一位專業的台灣電商文案寫手。請為以下產品撰寫吸引人的銷售文案（使用繁體中文）：\n\n"
        . "產品名稱：{$productName}\n"
        . "產品描述：{$description}\n"
        . ($productPrice ? "價格：NT\${$productPrice}\n" : "")
        . "\n請提供：\n"
        . "1. **一句話標語**（20字以內，吸引人點擊）\n"
        . "2. **產品特色**（3-5點，條列式）\n"
        . "3. **完整銷售文案**（100-150字，含 emoji，適合社群媒體）\n"
        . "4. **SEO關鍵字**（5個，逗號分隔）\n\n"
        . "格式：\n===標語===\n...\n===特色===\n- ...\n===文案===\n...\n===關鍵字===\n...";
    
    $data = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.8,
        'max_tokens' => 1000,
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        return $result['choices'][0]['message']['content'] ?? generateTemplateCopy($productName, $description, $productPrice);
    }
    
    return generateTemplateCopy($productName, $description, $productPrice);
}

function generateTemplateCopy($productName, $description, $productPrice = null) {
    $priceText = $productPrice ? " 💰 特價 **NT\$" . number_format($productPrice, 0) . "**" : "";
    return "===標語===\n🔥 **{$productName}** 限時優惠中！\n\n===特色===\n- ✨ 台灣優質商品，品質保證\n- 📦 現貨供應，快速出貨\n- 💯 滿意保證，安心購買\n{$priceText}\n\n===文案===\n🔥 **{$productName}** 重磅來襲！\n\n" . mb_substr($description, 0, 80) . "...\n\n{$priceText}\n📱 立即掃碼購買，享限量優惠！\n\n#台灣好物 #限時優惠 #人氣推薦\n\n===關鍵字===\n{$productName}, 台灣, 優惠, 人氣商品, 限時";
}

function getSetting($key) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

function updateSetting($key, $value) {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?");
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function formatDate($timestamp) {
    return date('Y/m/d H:i', strtotime($timestamp));
}

function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    if ($diff < 60) return '剛剛';
    if ($diff < 3600) return floor($diff / 60) . '分鐘前';
    if ($diff < 86400) return floor($diff / 3600) . '小時前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y/m/d', strtotime($timestamp));
}

function generateSlug($string) {
    $string = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $string);
    $string = preg_replace('/\s+/', '-', trim($string));
    return mb_strtolower($string);
}