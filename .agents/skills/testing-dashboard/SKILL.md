---
name: testing-sale-aiceox-dashboard
description: Test the sale.aiceox.com seller dashboard and related features. Use when verifying dashboard UI, data isolation between sellers, or sales analytics changes.
---

# Testing sale.aiceox.com

## Environment

- **Production URL**: https://sale.aiceox.com
- **Server**: 129.146.1.87 (Oracle Cloud Ubuntu 22.04)
- **App Path**: /home/ubuntu/www/sale.aiceox.com/
- **Database**: ai_salesbot (MySQL)

## Demo Accounts

Demo seller accounts use password `password` (bcrypt hash `$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi`):

| Email | Store Name | user_id | Products |
|-------|-----------|---------|----------|
| seller1@demo.com | 台灣好物小舖 | 10 | 有機紅藜麥, 冷壓橄欖油, 天然蜂蜜禮盒 |
| seller2@demo.com | 美食天地 | 11 | 麻辣鍋底調理包, 手作鳳梨酥禮盒 |
| seller3@demo.com | 科技生活館 | 12 | Various electronics |
| seller4@demo.com | 時尚衣著 | 13 | Fashion items |
| seller5@demo.com | 居家好物 | 14 | Home goods |

Note: The admin account (admin@sale.aiceox.com) uses a different password hash and the password is unknown. Use demo seller accounts for testing.

## Key Testing Patterns

### Data Isolation Testing
The dashboard at `/dashboard.php` filters data by `user_id` or `seller_id`. To prove isolation works:
1. Log in as seller1@demo.com, note the stats (products, views, orders, revenue)
2. Log out, log in as seller2@demo.com
3. Verify all numbers are different and match database records

### Database Verification
SSH to server and query expected values before testing:
```bash
ssh -i ~/.ssh/aiceox_fixed_key ubuntu@129.146.1.87 \
  "mysql -u ai_salesbot -p'Ricky520!' ai_salesbot -e 'SELECT ...'"
```

### Deployment
Files are deployed via scp:
```bash
scp -i ~/.ssh/aiceox_fixed_key <file> ubuntu@129.146.1.87:/home/ubuntu/www/sale.aiceox.com/<path>
```
After uploading, fix ownership:
```bash
ssh -i ~/.ssh/aiceox_fixed_key ubuntu@129.146.1.87 "sudo chown www:www /home/ubuntu/www/sale.aiceox.com/<file>"
```

## Devin Secrets Needed

- **AICEOX_SERVER_SSH_KEY**: RSA private key for SSH access to 129.146.1.87

## Known Issues

- The SSH key stored as AICEOX_SERVER_SSH_KEY may be stored as a single-line string. If you get "error in libcrypto" when using it, you need to reconstruct the PEM format with proper line breaks (split every 64 chars between BEGIN/END markers).
- The index.php page uses its own inline header (not the shared `includes/header.php`), so navigation links added to header.php won't appear on the homepage — they appear on other pages like /products/list.php and /dashboard.php.
