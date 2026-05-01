<?php
// Utility functions

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateQRCode($productId, $price) {
    // Placeholder: generate QR code URL containing payment info
    // Will integrate with LINE Pay / JKOPay / Taiwan Pay
    $paymentUrl = "https://pay.example.com/checkout/" . $productId . "?amount=" . $price;
    return "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($paymentUrl);
}

function generateAICopy($productName, $description) {
    // Placeholder: call AI API (OpenAI / Claude / local LLM)
    // For now returns a template
    $prompt = "Generate a compelling Chinese product description for: " . $productName;
    return "🔥 **" . $productName . "** 限時優惠！\n\n" . substr($description, 0, 100) . "...\n\n👇 立即掃碼購買！";
}

function uploadFile($file, $targetDir) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return null;
    }
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    return null;
}
