/**
 * AI Salesbot — 拖曳上傳 & AI 素材生成
 */

class MediaUploader {
    constructor(options = {}) {
        this.zone = options.zone || document.querySelector('.upload-zone');
        this.input = options.input || this.zone?.querySelector('input[type="file"]');
        this.previewGrid = options.grid || document.querySelector('.upload-preview-grid');
        this.productId = options.productId || 0;
        this.maxFiles = options.maxFiles || 10;
        this.files = new Map(); // id -> {file, previewUrl, status}
        this.imageCount = 0;
        
        if (this.zone) {
            this.init();
        }
    }
    
    init() {
        // 拖曳事件
        this.zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.zone.classList.add('dragover');
        });
        
        this.zone.addEventListener('dragleave', () => {
            this.zone.classList.remove('dragover');
        });
        
        this.zone.addEventListener('drop', (e) => {
            e.preventDefault();
            this.zone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer?.files || []);
            if (files.length) this.handleFiles(files);
        });
        
        // 點擊選檔
        if (this.input) {
            this.input.addEventListener('change', (e) => {
                const files = Array.from(e.target.files || []);
                if (files.length) this.handleFiles(files);
                this.input.value = '';
            });
        }
    }
    
    handleFiles(files) {
        const imageFiles = files.filter(f => f.type.startsWith('image/'));
        const remaining = this.maxFiles - this.files.size;
        
        if (remaining <= 0) {
            showToast(`最多上傳 ${this.maxFiles} 張圖片`, 'warning');
            return;
        }
        
        const toUpload = imageFiles.slice(0, remaining);
        
        toUpload.forEach((file, i) => {
            const id = 'file_' + Date.now() + '_' + i;
            const previewUrl = URL.createObjectURL(file);
            
            this.files.set(id, { file, previewUrl, status: 'pending' });
            this.addPreviewItem(id, previewUrl);
        });
        
        // 更新區塊樣式
        if (this.files.size > 0) {
            this.zone.classList.add('has-files');
            this.zone.querySelector('.upload-text')?.classList.add('hidden');
        }
        
        // 上傳
        this.uploadAll();
    }
    
    addPreviewItem(id, previewUrl) {
        const div = document.createElement('div');
        div.className = 'upload-preview-item';
        div.id = 'preview_' + id;
        div.innerHTML = `
            <img src="${previewUrl}" alt="預覽">
            <button class="remove-btn" onclick="uploader.removeFile('${id}')">✕</button>
            <div class="upload-status" id="status_${id}">等待上傳...</div>
        `;
        this.previewGrid?.appendChild(div);
    }
    
    async uploadAll() {
        for (const [id, fileData] of this.files) {
            if (fileData.status !== 'pending') continue;
            
            this.updateStatus(id, 'loading', '上傳中...');
            
            try {
                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('product_id', this.productId);
                formData.append('images[]', fileData.file);
                
                const res = await fetch('../api/upload-images.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await res.json();
                
                if (data.success && data.uploaded?.length > 0) {
                    this.imageCount += data.uploaded.length;
                    this.updateStatus(id, 'done', '✅ 上傳完成');
                    
                    // 自動觸發 AI 去背
                    await this.triggerBackgroundRemoval(data.uploaded);
                } else {
                    this.updateStatus(id, 'error', '❌ 上傳失敗');
                    fileData.status = 'error';
                }
            } catch (e) {
                this.updateStatus(id, 'error', '❌ 網路錯誤');
                fileData.status = 'error';
            }
        }
    }
    
    async triggerBackgroundRemoval(uploaded) {
        // 去背已在上傳時自動處理（PHP 端），這裡只是更新 UI
        return true;
    }
    
    updateStatus(id, type, text) {
        const el = document.getElementById('status_' + id);
        if (el) {
            el.className = 'upload-status ' + type;
            el.textContent = text;
        }
        
        const fd = this.files.get(id);
        if (fd) fd.status = type;
    }
    
    removeFile(id) {
        const fd = this.files.get(id);
        if (fd?.previewUrl) {
            URL.revokeObjectURL(fd.previewUrl);
        }
        
        const el = document.getElementById('preview_' + id);
        el?.remove();
        
        this.files.delete(id);
        
        if (this.files.size === 0) {
            this.zone?.classList.remove('has-files');
        }
    }
}

/**
 * AI 素材生成器
 */
class AIGenerator {
    constructor(productId) {
        this.productId = productId;
        this.isGenerating = false;
    }
    
    /**
     * AI 生成產品圖片
     */
    async generateImage() {
        if (this.isGenerating) return;
        this.isGenerating = true;
        
        const btn = document.querySelector('[data-action="generate-image"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> 生成中...'; }
        
        this.showProgress('正在生成 AI 產品圖片...');
        
        try {
            const formData = new FormData();
            formData.append('action', 'generate_ai');
            formData.append('product_id', this.productId);
            
            const res = await fetch('../api/upload-images.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await res.json();
            
            if (data.success && data.path) {
                this.displayImageResult(data.path);
                showToast('✅ AI 圖片產生成功！', 'success');
            } else {
                showToast(data.error || 'AI 圖片生成失敗', 'error');
            }
        } catch (e) {
            showToast('網路錯誤', 'error');
        }
        
        this.hideProgress();
        this.isGenerating = false;
        if (btn) { btn.disabled = false; btn.innerHTML = '🎨 AI 生成圖片'; }
    }
    
    /**
     * AI 生成產品影片
     */
    async generateVideo() {
        if (this.isGenerating) return;
        this.isGenerating = true;
        
        const btn = document.querySelector('[data-action="generate-video"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="ai-spinner"></span> 生成影片中...'; }
        
        this.showProgress('正在生成產品展示影片...\n這大約需要 30-60 秒');
        
        try {
            const formData = new FormData();
            formData.append('action', 'generate_video');
            formData.append('product_id', this.productId);
            
            const res = await fetch('../api/upload-images.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await res.json();
            
            if (data.success && data.video_path) {
                this.displayVideoResult(data.video_path, data.script);
                showToast('✅ 影片產生成功！', 'success');
            } else {
                showToast(data.error || '影片生成失敗', 'error');
            }
        } catch (e) {
            showToast('網路錯誤', 'error');
        }
        
        this.hideProgress();
        this.isGenerating = false;
        if (btn) { btn.disabled = false; btn.innerHTML = '🎬 AI 生成影片'; }
    }
    
    /**
     * 顯示 AI 圖片結果
     */
    displayImageResult(path) {
        const area = document.getElementById('ai-image-results');
        if (!area) return;
        
        area.classList.add('show');
        
        const grid = area.querySelector('.result-grid') || area;
        const card = document.createElement('div');
        card.className = 'ai-result-card';
        card.innerHTML = `
            <img src="/${path}" alt="AI 生成圖片">
            <div class="result-card-footer">
                <span>AI 生成</span>
                <button class="btn btn-ghost btn-sm" onclick="window.open('/${path}')">🔍 檢視</button>
            </div>
        `;
        grid.appendChild(card);
    }
    
    /**
     * 顯示影片結果
     */
    displayVideoResult(path, script) {
        const area = document.getElementById('ai-video-results');
        if (!area) return;
        
        area.classList.add('show');
        area.innerHTML = `
            <div class="video-preview">
                <video controls autoplay muted loop>
                    <source src="/${path}" type="video/mp4">
                </video>
                <div class="video-actions">
                    <a href="/${path}" download class="btn btn-primary btn-sm">⬇️ 下載影片</a>
                </div>
            </div>
            ${script ? `<div class="copy-display" style="margin-top:12px"><strong>📝 影片腳本：</strong><br>${nl2br(escapeHtml(script))}</div>` : ''}
        `;
    }
    
    /**
     * 進度條
     */
    showProgress(text) {
        const area = document.getElementById('ai-progress');
        if (!area) return;
        
        area.classList.add('show');
        area.innerHTML = `
            <div class="progress-bar">
                <div class="progress-fill" style="width:60%"></div>
            </div>
            <p style="color:var(--text-muted);font-size:0.9rem;white-space:pre-line">${text}</p>
        `;
    }
    
    hideProgress() {
        const area = document.getElementById('ai-progress');
        if (area) area.classList.remove('show');
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // 檢查有無上傳區
    const uploadZone = document.querySelector('.upload-zone');
    const productId = parseInt(document.querySelector('[data-product-id]')?.dataset.productId || '0');
    
    if (uploadZone && productId > 0) {
        window.uploader = new MediaUploader({
            zone: uploadZone,
            productId: productId
        });
    }
    
    // AI 生成按鈕
    const genImageBtn = document.querySelector('[data-action="generate-image"]');
    const genVideoBtn = document.querySelector('[data-action="generate-video"]');
    const genCopyBtn = document.querySelector('[data-action="generate-copy"]');
    
    if (genImageBtn || genVideoBtn) {
        const generator = new AIGenerator(productId);
        
        genImageBtn?.addEventListener('click', () => generator.generateImage());
        genVideoBtn?.addEventListener('click', () => generator.generateVideo());
    }
    
    // 批量產生所有素材
    const batchBtn = document.querySelector('[data-action="generate-all"]');
    batchBtn?.addEventListener('click', async function() {
        this.disabled = true;
        this.innerHTML = '<span class="ai-spinner"></span> 全自動產生中...';
        
        showToast('🚀 開始全自動生成素材！', 'info');
        
        // 依序執行
        if (typeof generateAICopy === 'function') {
            await generateAICopy();
        }
        
        const generator = new AIGenerator(productId);
        await generator.generateImage();
        
        showToast('✅ 素材產生完成！', 'success');
        this.disabled = false;
        this.innerHTML = '🤖 一鍵全部產生';
    });
});

// Helper
function nl2br(str) {
    return (str + '').replace(/\n/g, '<br>');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}