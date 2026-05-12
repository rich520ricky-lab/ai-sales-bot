-- ============================================================
-- AI Salesbot v3 Migration
-- 新增 AI 商品素材自動生成功能
-- ============================================================

-- 1. 產品多圖片關聯表
CREATE TABLE IF NOT EXISTS product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_type ENUM('original','processed','ai_generated') DEFAULT 'original',
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id),
    INDEX idx_type (image_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. 產品影片相關欄位
ALTER TABLE products
    ADD COLUMN video_path VARCHAR(500) DEFAULT NULL AFTER qr_code_path,
    ADD COLUMN video_status ENUM('pending','processing','done','failed') DEFAULT NULL AFTER video_path,
    ADD COLUMN ai_images_generated INT DEFAULT 0 AFTER video_status;

-- 3. AI 生成日誌表
CREATE TABLE IF NOT EXISTS ai_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    generation_type ENUM('copy','image','video','background_removal') NOT NULL,
    status ENUM('pending','processing','success','failed') DEFAULT 'pending',
    model_used VARCHAR(100),
    prompt_text TEXT,
    result_path VARCHAR(500),
    error_message TEXT,
    processing_time_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_type (product_id, generation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. 設定檔新增 AI 相關設定項（如不存在則插入）
INSERT IGNORE INTO settings (`key`, `value`) VALUES
    ('ai_image_api_key', ''),
    ('ai_image_model', 'dall-e-3'),
    ('ai_video_enabled', '0'),
    ('ai_video_voice', 'zh-TW-WanLungNeural'),
    ('remove_bg_api_key', ''),
    ('product_default_watermark', ''),
    ('product_video_duration', '10');

-- 5. AI 提示詞範本表（讓管理者自訂生成風格）
CREATE TABLE IF NOT EXISTS ai_prompts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prompt_key VARCHAR(100) NOT NULL UNIQUE,
    prompt_template TEXT NOT NULL,
    description VARCHAR(255),
    is_active TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 預設提示詞
INSERT IGNORE INTO ai_prompts (prompt_key, prompt_template, description) VALUES
    ('product_slogan', '為以下的台灣產品撰寫一句吸引人的標語（15字以內，繁體中文，可含emoji）：\n產品：{product_name}\n描述：{description}', '產品標語生成'),
    ('product_features', '為以下產品列出3-5個特色重點（條列式，繁體中文，可含emoji）：\n產品：{product_name}\n描述：{description}', '產品特色生成'),
    ('product_full_copy', '你是專業的台灣電商文案寫手。請為以下產品撰寫完整的銷售文案（繁體中文，80-120字，適合社群媒體）：\n產品：{product_name}\n價格：NT${price}\n描述：{description}', '完整銷售文案'),
    ('product_seo_keywords', '為以下產品產生5個SEO關鍵字（繁體中文，逗號分隔）：\n產品：{product_name}\n描述：{description}', 'SEO關鍵字'),
    ('product_image_prompt', 'Generate a professional product photo of {product_name} on a clean white background. High quality, commercial photography, well-lit, 8K, minimalist product photography style.', 'AI圖片生成提示詞（英文送DALL-E）'),
    ('product_video_script', '為以下產品撰寫一個10秒的短影片腳本（繁體中文），包含開場、產品展示、結尾呼籲行動：\n產品：{product_name}\n價格：NT${price}\n描述：{description}', '產品影片腳本');

-- 6. 交易/訂單表（為統一 QR Code 支付做準備）
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(32) NOT NULL UNIQUE,
    product_id INT NOT NULL,
    buyer_name VARCHAR(100),
    buyer_phone VARCHAR(20),
    buyer_email VARCHAR(255),
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) COMMENT 'line_pay, jkopay, twpay, credit_card, unified_qr',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    qr_code_token VARCHAR(64) COMMENT '統一QR Code專用token',
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_order_no (order_no),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;