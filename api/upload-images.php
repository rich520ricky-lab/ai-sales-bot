<?php
/**
 * AI Salesbot - Multi-image Upload API
 * 支援拖曳多圖片上傳、即時預覽、AI 處理
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/image-processor.php';

header('Content-Type: application/json');
$user = requireLogin();

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = '僅支援 POST';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'upload':
        // 上傳多張圖片
        $productId = intval($_POST['product_id'] ?? 0);
        $files = $_FILES['images'] ?? null;
        
        if (!$files || !isset($files['tmp_name'])) {
            $response['error'] = '請選擇圖片';
            break;
        }
        
        $uploaded = [];
        $errors = [];
        
        // 支援單檔或多檔
        $fileCount = is_array($files['tmp_name']) ? count($files['tmp_name']) : 1;
        
        for ($i = 0; $i < $fileCount; $i++) {
            $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
            $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            
            if ($error !== UPLOAD_ERR_OK || empty($tmp)) continue;
            
            // 驗證檔案類型
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $errors[] = "{$name}: 不支援的檔案格式";
                continue;
            }
            
            // 驗證檔案大小（最大 20MB）
            $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
            if ($size > 20 * 1024 * 1024) {
                $errors[] = "{$name}: 檔案過大（最大 20MB）";
                continue;
            }
            
            // 處理圖片
            $result = processProductImage($tmp, $productId, $name);
            
            if ($result['success']) {
                // 記錄到 product_images 表
                try {
                    $db = getDB();
                    
                    // 先處理圖片（去背/縮放）
                    $paths = $result['paths'];
                    
                    // 儲存原始圖
                    $stmt = $db->prepare(
                        "INSERT INTO product_images (product_id, image_path, image_type, sort_order) VALUES (?, ?, 'original', ?)"
                    );
                    $stmt->execute([$productId, $paths['original'], $i]);
                    
                    // 如果有去背圖，也儲存
                    if (isset($paths['instagram'])) {
                        $stmt = $db->prepare(
                            "INSERT INTO product_images (product_id, image_path, image_type, sort_order) VALUES (?, ?, 'processed', ?)"
                        );
                        $stmt->execute([$productId, $paths['instagram'], $i + 100]);
                    }
                    
                    $uploaded[] = [
                        'name' => $name,
                        'original' => $paths['original'],
                        'thumbnail' => $paths['thumbnail'] ?? $paths['small'] ?? $paths['original'],
                        'instagram' => $paths['instagram'] ?? null,
                    ];
                } catch (Exception $e) {
                    $errors[] = "{$name}: 資料庫儲存失敗";
                }
            } else {
                $errors[] = "{$name}: {$result['error']}";
            }
        }
        
        $response = [
            'success' => !empty($uploaded),
            'uploaded' => $uploaded,
            'errors' => $errors,
            'total' => count($uploaded),
        ];
        break;

    case 'generate_ai':
        // AI 產生產品圖片
        $productId = intval($_POST['product_id'] ?? 0);
        
        if (!$productId) {
            $response['error'] = '缺少產品 ID';
            break;
        }
        
        // 取得產品資訊
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND user_id = ?");
            $stmt->execute([$productId, $user['id']]);
            $product = $stmt->fetch();
            
            if (!$product) {
                $response['error'] = '產品不存在';
                break;
            }
            
            $result = generateAIImage(
                $productId,
                $product['name'],
                $product['description'] ?? ''
            );
            
            $response = $result;
            
        } catch (Exception $e) {
            $response['error'] = '系統錯誤：' . $e->getMessage();
        }
        break;

    case 'generate_video':
        // AI 產生產品影片
        $productId = intval($_POST['product_id'] ?? 0);
        
        if (!$productId) {
            $response['error'] = '缺少產品 ID';
            break;
        }
        
        // 檢查影片功能是否啟用
        if (!getSetting('ai_video_enabled')) {
            $response['error'] = '影片生成功能未啟用，請先在後台設定';
            break;
        }
        
        require_once __DIR__ . '/../includes/video-generator.php';
        
        $result = generateProductVideo($productId);
        $response = $result;
        break;

    case 'get_images':
        // 取得產品所有圖片
        $productId = intval($_GET['product_id'] ?? 0);
        
        if (!$productId) {
            $response['error'] = '缺少產品 ID';
            break;
        }
        
        $images = getProductImages($productId);
        $response = [
            'success' => true,
            'images' => $images,
            'total' => count($images),
        ];
        break;

    case 'delete_image':
        // 刪除圖片
        $imageId = intval($_POST['image_id'] ?? 0);
        
        if (!$imageId) {
            $response['error'] = '缺少圖片 ID';
            break;
        }
        
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT image_path FROM product_images WHERE id = ?");
            $stmt->execute([$imageId]);
            $img = $stmt->fetch();
            
            if ($img) {
                // 刪除實體檔案
                $fullPath = __DIR__ . '/../' . $img['image_path'];
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
                
                $stmt = $db->prepare("DELETE FROM product_images WHERE id = ?");
                $stmt->execute([$imageId]);
                
                $response = ['success' => true];
            } else {
                $response['error'] = '圖片不存在';
            }
        } catch (Exception $e) {
            $response['error'] = '刪除失敗';
        }
        break;

    case 'reorder':
        // 重新排序圖片
        $order = $_POST['order'] ?? '';
        $ids = explode(',', $order);
        
        try {
            $db = getDB();
            foreach ($ids as $i => $id) {
                $id = intval($id);
                if ($id > 0) {
                    $stmt = $db->prepare("UPDATE product_images SET sort_order = ? WHERE id = ?");
                    $stmt->execute([$i, $id]);
                }
            }
            $response = ['success' => true];
        } catch (Exception $e) {
            $response['error'] = '排序更新失敗';
        }
        break;

    default:
        $response['error'] = '未知動作';
}

echo json_encode($response);