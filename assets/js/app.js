/**
 * AI Salesbot - Common JavaScript
 */

// Copy to clipboard
function copyToClipboard(text, btn) {
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '✅ 已複製';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        const original = btn.innerHTML;
        btn.innerHTML = '✅ 已複製';
        setTimeout(() => { btn.innerHTML = original; }, 2000);
    });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    const icons = {
        success: '✅',
        error: '❌',
        warning: '⚠️',
        info: 'ℹ️'
    };
    
    toast.innerHTML = `<span>${icons[type] || 'ℹ️'}</span><span>${message}</span>`;
    toast.style.borderColor = `var(--${type})`;
    toast.classList.add('show');
    
    setTimeout(() => { toast.classList.remove('show'); }, 3000);
}

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const menuBtn = document.querySelector('.mobile-menu-btn');
    const navLinks = document.querySelector('.nav-links');
    
    if (menuBtn && navLinks) {
        menuBtn.addEventListener('click', function() {
            navLinks.classList.toggle('show');
            menuBtn.textContent = navLinks.classList.contains('show') ? '✕' : '☰';
        });
    }
});

// AI Copy Generation
async function generateAICopy() {
    const name = document.getElementById('name')?.value;
    const desc = document.getElementById('description')?.value;
    const price = document.getElementById('price')?.value;
    const btn = document.querySelector('.ai-generate-btn');
    const preview = document.querySelector('.ai-preview');
    const resultDiv = document.getElementById('ai-result');
    const spinner = document.querySelector('.ai-spinner');
    
    if (!name || !desc) {
        showToast('請填寫產品名稱和描述', 'warning');
        return;
    }
    
    if (btn) {
        btn.disabled = true;
        if (spinner) spinner.style.display = 'inline-block';
        btn.innerHTML = '<span class="ai-spinner"></span> AI 生成中...';
    }
    
    try {
        const formData = new FormData();
        formData.append('name', name);
        formData.append('description', desc);
        formData.append('price', price || '');
        
        const response = await fetch('../api/generate-copy.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && preview && resultDiv) {
            // Parse the markdown-style result
            let html = data.copy
                .replace(/===標語===/g, '<div class="copy-section"><strong>📢 標語</strong></div>')
                .replace(/===特色===/g, '<div class="copy-section" style="margin-top:12px"><strong>✨ 產品特色</strong></div>')
                .replace(/===文案===/g, '<div class="copy-section" style="margin-top:12px"><strong>📝 完整文案</strong></div>')
                .replace(/===關鍵字===/g, '<div class="copy-section" style="margin-top:12px"><strong>🔑 SEO關鍵字</strong></div>')
                .replace(/\n/g, '<br>')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            
            resultDiv.innerHTML = html;
            preview.classList.add('show');
            showToast('✅ AI 文案產生成功！');
            
            // Store for form submission
            document.getElementById('ai_copy_hidden').value = data.copy;
        } else {
            showToast('AI 生成失敗，請稍後再試', 'error');
        }
    } catch (e) {
        showToast('網路錯誤，請稍後再試', 'error');
    }
    
    if (btn) {
        btn.disabled = false;
        if (spinner) spinner.style.display = 'none';
        btn.innerHTML = '🤖 AI 產生文案';
    }
}

// Confirm dialog helper
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Format currency
function formatPrice(price) {
    return 'NT$ ' + Number(price).toLocaleString('zh-TW');
}