EARNSSTORE — MERGED E-COMMERCE XAMPP EDITION

INSTALL
1. Extract this folder into C:\xampp\htdocs\earnsstore.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import database/earn_ecommerce.sql. IMPORTANT: this SQL drops/recreates the app tables in earn_ecommerce. Back up an existing database first.
4. Visit http://localhost/earnsstore/setup_admin.php and create the first administrator using a strong password (12+ characters).
5. Delete setup_admin.php after creating the admin.
6. Store: http://localhost/earnsstore/
7. Sign in using Account and enter your email/password. The server returns the account role; admins are redirected to the dashboard automatically. There is no Admin tab on the storefront.

FEATURES
- Merged NewV2 account system, customer orders/history, reviews, inventory, analytics, order status management, search/categories/cart/checkout.
- Original EarnsStore products imported alongside NewV2 products. The original front image URL is used as the product image and back image/source URL are preserved in the description.
- Admin product form supports image upload from the computer using a file picker (JPG, PNG, GIF, WEBP; max 5 MB).
- Clicking an order in the admin dashboard opens a dedicated order details page.
- Kilimall-inspired marketplace layout; not affiliated with Kilimall.

LIMITATIONS
- Product images are remote URLs from the source catalogue, not image files downloaded into this package; external URLs may change or be unavailable.
- Demo checkout is cash on delivery only. No M-Pesa/card processor is connected. Delivery policy: KSh 350 below KSh 50,000, free at/above KSh 50,000.
- The standalone GitHub Pages HTML is a frontend demo using browser localStorage. GitHub Pages cannot execute PHP/MySQL; for real accounts, shared orders, file uploads, and secure admin permissions use this XAMPP PHP build or another backend host.
- Before public deployment, use HTTPS, a non-root DB user, backups, rate limiting, privacy controls, and review Kenya consumer/data-protection requirements.
