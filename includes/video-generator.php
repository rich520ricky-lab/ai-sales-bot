<?php
/**
 * AI Salesbot - Video Generator
 * 產品展示影片自動生成
 * 
 * 流程：照片輪播 + AI 語音旁白 + 背景音樂 + 字幕
 * 依賴：FFmpeg, OpenAI TTS, PHP GD
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/image-processor.php';

/**
 * 為產品產生展示影片
 */
function generateProductVideo($productId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return ['success' => false, 'error' => '產品不存在'];
        }
        
        // 更新狀態為處理中
        $db->prepare("UPDATE products SET video_status = 'processing' WHERE id = ?")->execute([$productId]);
        
        // 1. 準備圖片素材
        $images = getProductImages($productId);
        $imagePaths = [];
        
        // 優先使用處理過的圖片，其次 AI 生成，最後原始
        foreach ($images as $img) {
            $fullPath = __DIR__ . '/../' . $img['image_path'];
            if (file_exists($fullPath)) {
                $imagePaths[] = $fullPath;
            }
        }
        
        // 如果沒有多圖片，使用產品主圖
        if (empty($imagePaths) && $product['image_path']) {
            $fullPath = __DIR__ . '/../' . $product['image_path'];
            if (file_exists($fullPath)) {
                $imagePaths[] = $fullPath;
            }
        }
        
        // 若完全無圖片，建立一張文字圖
        if (empty($imagePaths)) {
            $textImage = createTextImage($product['name'], $product['price']);
            if ($textImage) {
                $imagePaths[] = $textImage;
            }
        }
        
        // 2. 生成影片腳本
        $script = generateVideoScript($product);
        
        // 3. 生成語音（TTS）
        $audioPath = generateVoiceover($script);
        
        // 4. 合併圖片 + 音訊 → 影片
        $outputPath = generateSlideshowVideo($imagePaths, $audioPath, $productId);
        
        if ($outputPath && file_exists($outputPath)) {
            $relativePath = str_replace(__DIR__ . '/../', '', $outputPath);
            
            $db->prepare("UPDATE products SET video_path = ?, video_status = 'done' WHERE id = ?")
                ->execute([$relativePath, $productId]);
            
            logGeneration($productId, 'video', 'success', '影片生成成功', 'ffmpeg+tts', $script, $relativePath);
            
            return [
                'success' => true,
                'video_path' => $relativePath,
                'script' => $script,
            ];
        } else {
            throw new Exception('影片合成失敗');
        }
        
    } catch (Exception $e) {
        $db = getDB();
        $db->prepare("UPDATE products SET video_status = 'failed' WHERE id = ?")->execute([$productId]);
        logGeneration($productId, 'video', 'failed', $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * AI 生成影片腳本
 */
function generateVideoScript($product) {
    $prompt = getPromptTemplate('product_video_script', [
        'product_name' => $product['name'],
        'price' => number_format($product['price'], 0),
        'description' => mb_substr($product['description'] ?? '', 0, 200),
    ]);
    
    $aiApiKey = getSetting('ai_api_key');
    $aiModel = getSetting('ai_model') ?: 'gpt-4o-mini';
    
    if (empty($aiApiKey)) {
        // 降級範本
        return "🔥 {$product['name']} 限時優惠中！\n"
            . "只要 NT\$" . number_format($product['price'], 0) . "！\n"
            . "品質保證，現貨供應\n"
            . "掃碼立即購買！";
    }
    
    $data = [
        'model' => $aiModel,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.7,
        'max_tokens' => 300,
    ];
    
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $aiApiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $script = $result['choices'][0]['message']['content'] ?? '';
        if (!empty($script)) {
            // 清理腳本，僅保留對話文字
            $script = strip_tags($script);
            $script = preg_replace('/===.*?===/', '', $script);
            $script = trim(preg_replace('/\n\s*\n/', "\n", $script));
            return $script;
        }
    }
    
    return "{$product['name']}，限時優惠 NT\$" . number_format($product['price'], 0) . "！掃碼立即購買！";
}

/**
 * TTS 語音生成
 */
function generateVoiceover($text) {
    $outputDir = __DIR__ . '/../uploads/audio';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputPath = $outputDir . '/tts_' . uniqid() . '.mp3';
    
    $apiKey = getSetting('ai_api_key');
    $voice = getSetting('ai_video_voice') ?: 'alloy';
    
    if (empty($apiKey)) {
        // 無 API Key 時回傳 null，後續只做無聲影片
        return null;
    }
    
    // 使用 OpenAI TTS
    $data = [
        'model' => 'tts-1',
        'input' => $text,
        'voice' => $voice,
    ];
    
    // 支援 Azure 中文語音
    $azureKey = getSetting('azure_tts_key');
    $azureRegion = getSetting('azure_tts_region');
    
    if ($azureKey && $azureRegion) {
        // Azure TTS（中文效果較好）
        $ttsText = '<speak version="1.0" xmlns="http://www.w3.org/2001/10/synthesis" xml:lang="zh-TW">'
            . '<voice name="' . $voice . '">' . htmlspecialchars($text) . '</voice></speak>';
        
        $ch = curl_init("https://{$azureRegion}.tts.speech.microsoft.com/cognitiveservices/v1");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Ocp-Apim-Subscription-Key: ' . $azureKey,
                'Content-Type: application/ssml+xml',
                'X-Microsoft-OutputFormat: audio-16khz-128kbitrate-mono-mp3',
                'User-Agent: AI-Salesbot',
            ],
            CURLOPT_POSTFIELDS => $ttsText,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
    } else {
        // OpenAI TTS（fallback）
        $ch = curl_init('https://api.openai.com/v1/audio/speech');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => 'tts-1',
                'input' => $text,
                'voice' => $voice,
                'response_format' => 'mp3',
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);
    }
    
    $audioData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && strlen($audioData) > 1000) {
        file_put_contents($outputPath, $audioData);
        return $outputPath;
    }
    
    return null;
}

/**
 * 圖片輪播合成影片（FFmpeg）
 */
function generateSlideshowVideo($imagePaths, $audioPath, $productId) {
    $outputDir = __DIR__ . '/../uploads/videos';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $outputPath = $outputDir . '/product_' . $productId . '_' . uniqid() . '.mp4';
    
    // 檢查 FFmpeg 是否可用
    exec('which ffmpeg 2>/dev/null', $ffmpegOutput, $ffmpegExit);
    if ($ffmpegExit !== 0) {
        // FFmpeg 不可用，嘗試用 PHP 生成簡單 GIF
        return generateSimpleGif($imagePaths, $outputPath);
    }
    
    $ffmpeg = trim($ffmpegOutput[0]);
    
    // 建立暫存目錄
    $tempDir = sys_get_temp_dir() . '/video_gen_' . uniqid();
    mkdir($tempDir, 0755, true);
    
    // 將圖片統一尺寸並命名為 frame_001.jpg, frame_002.jpg ...
    $frameDuration = 3; // 每張圖片顯示秒數
    $frames = [];
    foreach ($imagePaths as $i => $path) {
        $frameFile = "{$tempDir}/frame_" . str_pad($i + 1, 3, '0', STR_PAD_LEFT) . ".jpg";
        // 統一為 1080x1920 (直式，適合 IG/短影音)
        $cmd = escapeshellcmd($ffmpeg) . " -i " . escapeshellarg($path)
            . " -vf \"scale=1080:1920:force_original_aspect_ratio=2,crop=1080:1920\""
            . " -frames:v 1 -q:v 2 " . escapeshellarg($frameFile) . " 2>/dev/null";
        exec($cmd, $o, $code);
        if (file_exists($frameFile)) {
            $frames[] = $frameFile;
        }
    }
    
    if (empty($frames)) {
        rmdirRecursive($tempDir);
        return null;
    }
    
    // 建立 concat 檔案列表
    $listFile = "{$tempDir}/frames.txt";
    $listContent = '';
    foreach ($frames as $f) {
        $listContent .= "file '" . addslashes($f) . "'\n";
        $listContent .= "duration {$frameDuration}\n";
    }
    // 最後一張需要重複一次供 FFmpeg 正確讀取長度
    $listContent .= "file '" . addslashes(end($frames)) . "'\n";
    file_put_contents($listFile, $listContent);
    
    // FFmpeg 參數
    $videoDuration = count($frames) * $frameDuration;
    
    if ($audioPath && file_exists($audioPath)) {
        // 有語音：合併音訊 + 字幕
        $cmd = escapeshellcmd($ffmpeg)
            . " -f concat -safe 0 -i " . escapeshellarg($listFile)
            . " -i " . escapeshellarg($audioPath)
            . " -c:v libx264 -preset fast -crf 23"
            . " -c:a aac -b:a 128k -shortest"
            . " -pix_fmt yuv420p"
            . " -movflags +faststart"
            . " " . escapeshellarg($outputPath)
            . " 2>/dev/null";
    } else {
        // 無語音：純音樂或無聲
        $cmd = escapeshellcmd($ffmpeg)
            . " -f concat -safe 0 -i " . escapeshellarg($listFile)
            . " -c:v libx264 -preset fast -crf 23"
            . " -pix_fmt yuv420p"
            . " -movflags +faststart"
            . " -an"
            . " -t " . intval($videoDuration)
            . " " . escapeshellarg($outputPath)
            . " 2>/dev/null";
    }
    
    exec($cmd, $output, $exitCode);
    
    // 清理暫存
    rmdirRecursive($tempDir);
    
    return ($exitCode === 0 && file_exists($outputPath) && filesize($outputPath) > 10000)
        ? $outputPath
        : null;
}

/**
 * 降級方案：生成簡單 GIF
 */
function generateSimpleGif($imagePaths, $outputPath) {
    if (empty($imagePaths)) return null;
    
    // 用 PHP GD 產生動畫 GIF
    $gifPath = preg_replace('/\.mp4$/', '.gif', $outputPath);
    $frames = [];
    
    foreach ($imagePaths as $path) {
        $img = loadImage($path);
        if ($img) {
            // 縮小到 480x480
            $w = imagesx($img);
            $h = imagesy($img);
            $size = min($w, $h, 480);
            $thumb = imagecreatetruecolor($size, $size);
            imagecopyresampled($thumb, $img, 0, 0, ($w - $size) / 2, ($h - $size) / 2, $size, $size, $size, $size);
            
            // 轉為調色板 GIF
            $gifFrame = imagecreatetruecolor($size, $size);
            imagecopy($gifFrame, $thumb, 0, 0, 0, 0, $size, $size);
            imagetruecolortopalette($gifFrame, true, 256);
            
            $frames[] = $gifFrame;
            imagedestroy($img);
            imagedestroy($thumb);
        }
    }
    
    if (empty($frames)) return null;
    
    // 輸出 GIF（簡易實作，無 delay 控制）
    $result = imagegif($frames[0], $gifPath);
    foreach ($frames as $f) imagedestroy($f);
    
    return $result ? $gifPath : null;
}

/**
 * 無圖片時：生成文字圖
 */
function createTextImage($productName, $price) {
    $dir = __DIR__ . '/../uploads/text_images';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $path = $dir . '/text_' . uniqid() . '.png';
    $img = imagecreatetruecolor(1080, 1080);
    
    // 漸層背景
    $bg1 = imagecolorallocate($img, 99, 102, 241);
    $bg2 = imagecolorallocate($img, 168, 85, 247);
    $textColor = imagecolorallocate($img, 255, 255, 255);
    
    // 簡單漸層
    for ($y = 0; $y < 1080; $y++) {
        $r = $bg1 >> 16 & 0xFF + ($bg2 >> 16 & 0xFF - $bg1 >> 16 & 0xFF) * $y / 1080;
        imagesetpixel($img, 0, $y, $bg1);
    }
    
    // 使用內建字型
    $fontSize = 48;
    $fontFile = '/usr/share/fonts/truetype/noto/NotoSansTC-Regular.otf';
    
    if (file_exists($fontFile)) {
        imagettftext($img, 64, 0, 100, 400, $textColor, $fontFile, $productName);
        imagettftext($img, 48, 0, 100, 550, $textColor, $fontFile, 'NT$ ' . number_format($price, 0));
        imagettftext($img, 32, 0, 100, 700, $textColor, $fontFile, '掃碼立即購買');
    } else {
        imagestring($img, 5, 100, 400, $productName, $textColor);
        imagestring($img, 5, 100, 500, 'NT$ ' . number_format($price, 0), $textColor);
    }
    
    imagepng($img, $path);
    imagedestroy($img);
    
    return $path;
}

/**
 * 遞迴刪除目錄
 */
function rmdirRecursive($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? rmdirRecursive($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * 取得產品影片資訊
 */
function getProductVideoInfo($productId) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT video_path, video_status FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        return null;
    }
}