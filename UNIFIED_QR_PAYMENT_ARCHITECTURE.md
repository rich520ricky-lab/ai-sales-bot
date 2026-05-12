# 統一 QR Code 支付系統 — 技術架構設計

## 專利核心概念

**一個 QR Code，收所有支付。**
消費者無論使用 LINE Pay、街口支付、台灣 Pay、全支付、悠遊付、icash Pay 或信用卡，
掃同一個 QR Code 就能完成付款。

## 技術架構

```
┌─────────────────────────────────────────────────────┐
│                  賣家平台 (AI Salesbot)               │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────┐ │
│  │ 產品管理      │  │ QR Code 產生   │  │ 訂單管理    │ │
│  └──────┬──────┘  └──────┬───────┘  └─────┬──────┘ │
│         │                │                 │         │
└─────────┼────────────────┼─────────────────┼─────────┘
          │                │                 │
          ▼                ▼                 ▼
┌─────────────────────────────────────────────────────┐
│                統一 QR Code 支付閘道                   │
│                                                      │
│  1. 消費者掃碼 → 開啟統一付款頁面                        │
│  2. 付款頁面顯示金額 + 所有可用支付方式                    │
│  3. 消費者選擇支付方式                                  │
│  4. 後端路由到對應支付 API                              │
│  5. 支付完成 → 回呼通知 → 訂單更新                       │
│                                                      │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌────────┐ │
│  │ LINE Pay │ │ 街口支付  │ │ 台灣 Pay  │ │ 信用卡  │ │
│  │   API    │ │   API    │ │   API    │ │   API  │ │
│  └──────────┘ └──────────┘ └──────────┘ └────────┘ │
└─────────────────────────────────────────────────────┘
```

## QR Code 格式設計

統一 QR Code 不採用各家原生格式，而是使用自訂格式：

```
https://pay.aiceox.com/q/{token}
```

其中 `{token}` 是一個加密的 UUID，對應後端的一筆訂單記錄。
後端再根據消費者選擇的支付方式，動態生成對應的支付請求。

### vs 各家原生 QR Code

| 支付方式 | 原生 QR Code 格式 | 統一 QR Code |
|---------|-------------------|-------------|
| LINE Pay | `LINE_PAY_` 前綴 | `https://pay.aiceox.com/q/xxx` |
| 街口 | `JKOPAY` 前綴 | 同上 |
| 台灣 Pay | TW QR Code 規格 | 同上 |
| 信用卡 | 感應/NFC | 同上 |

## 核心模組

### 1. 訂單生成 API (`api/pay.php`)

```
GET /api/pay.php?id={productId}&amount={price}
→ 顯示統一付款頁面
→ 產生訂單 token
→ 回傳 QR Code 內容
```

### 2. 統一付款頁面 (`api/unified-pay.php`)

```
GET /api/unified-pay.php?token={orderToken}
→ 顯示：
   - 產品資訊
   - 金額
   - 支付方式選擇 (LINE Pay / 街口 / 台灣 Pay / 信用卡)
→ POST 選擇後導向對應支付閘道
```

### 3. 支付路由 (`api/payment-router.php`)

```php
switch ($paymentMethod) {
    case 'line_pay':
        // LINE Pay API
        $reserveUrl = 'https://api-pay.line.me/v3/payments/request';
        break;
    case 'jkopay':
        // 街口支付 API
        $reserveUrl = 'https://api.jkopay.com/v3/payments';
        break;
    case 'twpay':
        // 台灣 Pay API (Taiwan Pay QR Code 規格)
        $reserveUrl = 'https://api.twnpay.com.tw/payment';
        break;
}
```

### 4. 支付回呼 (`api/payment-callback.php`)

各家支付的回呼通知統一收斂到此端點：

```php
// LINE Pay callback
POST /api/payment-callback.php?method=line_pay
// 街口回呼
POST /api/payment-callback.php?method=jkopay
// 台灣 Pay 回呼
POST /api/payment-callback.php?method=twpay
```

## 資料庫訂單流程

```
建立訂單 (status=pending)
    ↓
消費者掃碼 → 選擇支付方式
    ↓
導向對應支付閘道 (status=processing)
    ↓
消費者完成付款
    ↓
支付回呼 (status=paid)
    ↓
通知賣家 + 更新銷售統計
```

## 安全性設計

1. **訂單 Token**：64 bytes random + HMAC 簽章，防止偽造
2. **金額鎖定**：QR Code 產生時鎖定金額，付款頁面不可修改
3. **單次使用**：每個 QR Code token 只能使用一次
4. **CORS 限制**：付款頁面限制跨域請求
5. **HTTPS Only**：所有支付流程強制 HTTPS
6. **日誌稽核**：所有支付請求完整記錄

## 與各家支付的合作要點

### LINE Pay
- 申請成為 LINE Pay 特約商店
- 需要：公司登記、營業登記、銀行帳戶
- API：LINE Pay API v3 (Payment Request API)
- 手續費：約 2.5%-3.5%

### 街口支付
- 申請街口支付收款
- 需要：公司/個人商家資格
- API：街口支付 API
- 手續費：約 2%-3%

### 台灣 Pay
- 透過財金公司申請
- 需要：銀行帳戶
- TW QR Code 共通規格
- 手續費：較低 (0.8%-1.5%)

### 關鍵策略：專利授權模式

因為你有 **統一 QR Code 支付專利**，與其一家家串接 API，
更好的商業模式是：

1. **不做支付閘道** — 讓既有支付業者來授權你的專利
2. **專利授權** — LINE Pay、街口等業者若想支援統一 QR Code，需取得授權
3. **你的平台** — 作為第一家示範應用，展示專利可行性
4. **商業模式**：每筆交易抽成 0.1%-0.5% 作為專利授權費