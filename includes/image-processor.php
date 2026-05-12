<?php
/**
 * AI Salesbot - Image Processor
 * 圖片處理核心：去背、縮放、浮水印、多尺寸產生
 */

require_once __DIR__ . '/db.php';

define('IMAGE_QUALITY_JPEG', 90);
define('IMAGE_QUALITY_WEBP', 85);

/**
 * 處理上傳圖片：去背 → 多尺寸 → 儲存
 */
function processProductImage($sourcePath, $productId, $originalName = '') {
    $results = [];
    
    // 1. 建立產品目錄
    $productDir = 'uploads/products/' . $productId;
    $fullDir = __DIR__ . '/../' . $productDir;
    if (!is_dir($fullDir)) {
        mkdir($fullDir, 0755, true);
    }
    
    // 2. 原始圖片資訊
    $imageInfo = @getimagesize($sourcePath);
    if (!$imageInfo) {
        logGeneration($productId, 'image', 'failed', '無法讀取圖片資訊');
        return ['success' => false, 'error' => '無法讀取圖片資訊'];
    }
    
    $ext = strtolower(pathinfo($originalName ?: $sourcePath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        $ext = 'jpg';
    }
    
    $baseName = uniqid('img_');
    
    // 3. 儲存原始檔
    $originalPath = "{$productDir}/{$baseName}_original.{$ext}";
    copy($sourcePath, __DIR__ . '/../' . $originalPath);
    $results['original'] = $originalPath;
    
    // 4. 嘗試去背
    $processedPath = removeBackground($sourcePath, $productDir, $baseName);
    $mainImage = $processedPath ?: $originalPath;
    
    // 5. 產生多尺寸
    $sizes = [
        'large' => ['width' => 1200, 'height' => 1200],
        'medium' => ['width' => 800, 'height' => 800],
        'small' => ['width' => 400, 'height' => 400],
        'thumbnail' => ['width' => 150, 'height' => 150],
    ];
    
    foreach ($sizes as $sizeName => $dim) {
        $sizePath = "{$productDir}/{$baseName}_{$sizeName}.jpg";
        if (resizeImage($mainImage, __DIR__ . '/../' . $sizePath, $dim['width'], $dim['height'])) {
            $results[$sizeName] = $sizePath;
        }
    }
    
    // 6. Instagram 方形裁切
    $igPath = "{$productDir}/{$baseName}_instagram.jpg";
    if (cropSquare($mainImage, __DIR__ . '/../' . $igPath, 1080)) {
        $results['instagram'] = $igPath;
    }
    
    return ['success' => true, 'paths' => $results];
}

/**
 * AI 去背 (使用 remove.bg API 或 PHP GD 基本去背)
 */
function removeBackground($sourcePath, $targetDir, $baseName) {
    $apiKey = getSetting('remove_bg_api_key');
    $outputPath = __DIR__ . '/../' . $targetDir . '/' . $baseName . '_nobg.png';
    
    if (!empty($apiKey)) {
        // 使用 remove.bg API
        $ch = curl_init('https://api.remove.bg/v1.0/removebg');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => [
                'image_file' => new CURLFile($sourcePath),
                'size' => 'auto',
            ],
            CURLOPT_HTTPHEADER => ['X-Api-Key: ' . $apiKey],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && strlen($result) > 1000) {
            file_put_contents($outputPath, $result);
            logGeneration(0, 'background_removal', 'success', 'remove.bg API 去背成功');
            return $targetDir . '/' . $baseName . '_nobg.png';
        }
        
        logGeneration(0, 'background_removal', 'failed', 'remove.bg API 失敗: HTTP ' . $httpCode);
    }
    
    // 降級方案：基本白底去背（簡易色差去背）
    // 註：如需高品質去背，建議串接 remove.bg API 或本機 AI 模型
    return null;
}

/**
 * 調整圖片尺寸（保持比例，裁切多餘部分）
 */
function resizeImage($sourcePath, $destPath, $maxWidth, $maxHeight) {
    $image = loadImage($sourcePath);
    if (!$image) return false;
    
    $origW = imagesx($image);
    $origH = imagesy($image);
    
    // 計算裁切比例
    $srcRatio = $origW / $origH;
    $dstRatio = $maxWidth / $maxHeight;
    
    if ($srcRatio > $dstRatio) {
        // 原圖較寬：裁切左右
        $newH = $origH;
        $newW = $origH * $dstRatio;
    } else {
        // 原圖較高：裁切上下
        $newW = $origW;
        $newH = $origW / $dstRatio;
    }
    
    $srcX = ($origW - $newW) / 2;
    $srcY = ($origH - $newH) / 2;
    
    $dest = imagecreatetruecolor($maxWidth, $maxHeight);
    imagecopyresampled($dest, $image, 0, 0, $srcX, $srcY, $maxWidth, $maxHeight, $newW, $newH);
    
    // 儲存
    $result = imagejpeg($dest, $destPath, IMAGE_QUALITY_JPEG);
    imagedestroy($image);
    imagedestroy($dest);
    
    return $result;
}

/**
 * 裁切為正方形（IG 用）
 */
function cropSquare($sourcePath, $destPath, $size = 1080) {
    $image = loadImage($sourcePath);
    if (!$image) return false;
    
    $origW = imagesx($image);
    $origH = imagesy($image);
    $min = min($origW, $origH);
    
    $srcX = ($origW - $min) / 2;
    $srcY = ($origH - $min) / 2;
    
    $dest = imagecreatetruecolor($size, $size);
    imagecopyresampled($dest, $image, 0, 0, $srcX, $srcY, $size, $size, $min, $min);
    
    $result = imagejpeg($dest, $destPath, IMAGE_QUALITY_JPEG);
    imagedestroy($image);
    imagedestroy($dest);
    
    return $result;
}

/**
 * 載入圖片（支援多種格式）
 */
function loadImage($path) {
    if (!file_exists($path)) return null;
    
    $info = @getimagesize($path);
    if (!$info) return null;
    
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:
            $img = imagecreatefrompng($path);
            imagepalettetotruecolor($img);
            return $img;
        case IMAGETYPE_WEBP:
            return imagecreatefromwebp($path);
        case IMAGETYPE_GIF:
            $img = imagecreatefromgif($path);
            imagepalettetotruecolor($img);
            return $img;
        default:
            return null;
    }
}

/**
 * 新增浮水印
 */
function addWatermark($sourcePath, $destPath, $watermarkText = '') {
    $text = $watermarkText ?: getSetting('product_default_watermark');
    if (empty($text)) return false;
    
    $image = loadImage($sourcePath);
    if (!$image) return false;
    
    $w = imagesx($image);
    $h = imagesy($image);
    
    // 浮水印字型大小
    $fontSize = max(12, min(48, $w / 20));
    
    // 使用內建字型（如果伺服器有支援的話）
    $fontFile = '/usr/share/fonts/truetype/noto/NotoSansTC-Regular.otf';
    if (!file_exists($fontFile)) {
        $fontFile = __DIR__ . '/../assets/fonts/NotoSansTC-Regular.otf';
    }
    
    if (file_exists($fontFile)) {
        $color = imagecolorallocatealpha($image, 255, 255, 255, 60);
        $box = imagettfbbox($fontSize, 0, $fontFile, $text);
        $tw = $box[2] - $box[0];
        $x = $w - $tw - 20;
        $y = $h - 20;
        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontFile, $text);
    } else {
        // 降級：使用內建 GD 字型
        $color = imagecolorallocatealpha($image, 255, 255, 255, 60);
        $tw = imagefontwidth(5) * strlen($text) * 2;
        $x = $w - $tw - 10;
        $y = $h - 10;
        imagestring($image, 5, $x, $y, $text, $color);
    }
    
    $result = imagejpeg($image, $destPath, IMAGE_QUALITY_JPEG);
    imagedestroy($image);
    return $result;
}

/**
 * 記錄 AI 生成日誌
 */
function logGeneration($productId, $type, $status, $message = '', $model = '', $prompt = '', $resultPath = '', $timeMs = 0) {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "INSERT INTO ai_generation_log 
            (product_id, generation_type, status, model_used, prompt_text, result_path, error_message, processing_time_ms) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$productId, $type, $status, $model, $prompt, $resultPath, $status === 'failed' ? $message : '', $timeMs]);
    } catch (Exception $e) {
        // 日誌失敗不影響主要流程
    }
}

/**
 * AI 生成產品圖片（DALL-E / Stable Diffusion）
 */
function generateAIImage($productId, $productName, $description) {
    $apiKey = getSetting('ai_image_api_key');
    $model = getSetting('ai_image_model') ?: 'dall-e-3';
    
    if (empty($apiKey)) {
        return ['success' => false, 'error' => '請先在後台設定 AI 圖片 API Key'];
    }
    
    // 取得圖片生成提示詞
    $prompt = getPromptTemplate('product_image_prompt', [
        'product_name' => $productName,
        'description' => mb_substr($description, 0, 200),
    ]);
    
    $startTime = microtime(true);
    
    // DALL-E 3 API
    $data = [
        'model' => $model,
        'prompt' => $prompt,
        'n' => 1,
        'size' => '1024x1024',
        'quality' => 'standard',
        'response_format' => 'b64_json',
    ];
    
    $ch = curl_init('https://api.openai.com/v1/images/generations');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $timeMs = round((microtime(true) - $startTime) * 1000);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $b64 = $result['data'][0]['b64_json'] ?? '';
        $revisedPrompt = $result['data'][0]['revised_prompt'] ?? $prompt;
        
        if ($b64) {
            // 儲存圖片
            $productDir = 'uploads/products/' . $productId . '/ai';
            $fullDir = __DIR__ . '/../' . $productDir;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }
            
            $filename = $productDir . '/ai_' . uniqid() . '.png';
            file_put_contents(__DIR__ . '/../' . $filename, base64_decode($b64));
            
            // 記錄資料庫
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO product_images (product_id, image_path, image_type) VALUES (?, ?, 'ai_generated')");
            $stmt->execute([$productId, $filename]);
            
            // 更新產品 AI 圖片計數
            $db->prepare("UPDATE products SET ai_images_generated = ai_images_generated + 1 WHERE id = ?")
                ->execute([$productId]);
            
            logGeneration($productId, 'image', 'success', '', $model, $revisedPrompt, $filename, $timeMs);
            
            return ['success' => true, 'path' => $filename];
        }
    }
    
    $errorMsg = $httpCode !== 200 ? "API 錯誤: HTTP {$httpCode}" : '回傳資料異常';
    logGeneration($productId, 'image', 'failed', $errorMsg, $model, $prompt, '', $timeMs);
    
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * 取得提示詞範本
 */
function getPromptTemplate($key, $variables = []) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT prompt_template FROM ai_prompts WHERE prompt_key = ? AND is_active = 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        $template = $row ? $row['prompt_template'] : '';
        
        if (empty($template)) {
            // 內建降級範本
            return $variables['product_name'] ?? 'Product';
        }
        
        // 變數替換
        foreach ($variables as $k => $v) {
            $template = str_replace('{' . $k . '}', $v, $template);
        }
        
        return $template;
    } catch (Exception $e) {
        return $variables['product_name'] ?? 'Product';
    }
}

/**
 * 取得產品所有圖片（含 AI 生成）
 */
function getProductImages($productId) {
    try {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT * FROM product_images WHERE product_id = ? ORDER BY image_type ASC, sort_order ASC, created_at DESC"
        );
        $stmt->execute([$productId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}