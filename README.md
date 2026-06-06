# BizComply — Business Compliance Management Platform
## Installation Guide

---

## Prerequisites
- PHP 7.4+ or 8.x
- MySQL 5.7+ / MariaDB 10.3+
- Apache/Nginx with mod_rewrite (XAMPP or LAMP)

---

## XAMPP (Windows/Mac) — Quickstart

```bash
# 1. Clone/copy project into htdocs
cp -r business-compliance/ C:/xampp/htdocs/

# 2. Start Apache + MySQL in XAMPP Control Panel

# 3. Open phpMyAdmin: http://localhost/phpmyadmin
#    Create database: business_compliance
#    Import: sql/schema.sql

# 4. Edit config/db.php if your MySQL password differs:
#    define('DB_PASS', 'your_password');

# 5. Open browser: http://localhost/business-compliance
```

---

## LAMP (Ubuntu/Debian)

```bash
# Install stack
sudo apt update && sudo apt install -y apache2 mysql-server php php-mysqli php-mbstring libapache2-mod-php

# Create project directory
sudo cp -r business-compliance/ /var/www/html/
sudo chown -R www-data:www-data /var/www/html/business-compliance/
sudo chmod -R 755 /var/www/html/business-compliance/
sudo chmod -R 777 /var/www/html/business-compliance/assets/uploads/

# Import database
sudo mysql -u root -p -e "CREATE DATABASE business_compliance CHARACTER SET utf8mb4;"
sudo mysql -u root -p business_compliance < /var/www/html/business-compliance/sql/schema.sql

# Enable Apache mod_rewrite
sudo a2enmod rewrite && sudo systemctl restart apache2

# Open: http://localhost/business-compliance
```

---

## Default Login Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@compliance.local | password |
| Officer | officer@compliance.local | password |

> **Important:** Change passwords immediately after first login via Users > Edit.

---

## Folder Structure

```
business-compliance/
├── config/
│   └── db.php              # DB connection + constants
├── includes/
│   ├── auth.php            # Session, auth helpers
│   ├── header.php          # Sidebar + nav partial
│   └── footer.php          # JS includes partial
├── admin/
│   ├── businesses.php      # Business CRUD
│   ├── users.php           # User management (admin only)
│   └── categories.php      # Category management (admin only)
├── compliance/
│   └── records.php         # Compliance records CRUD
├── documents/
│   ├── index.php           # All documents listing
│   ├── upload.php          # Upload + view per record
│   └── view.php            # Secure file serving
├── reports/
│   └── index.php           # Printable reports
├── assets/
│   ├── css/style.css       # Custom styles
│   └── uploads/            # Uploaded documents (writable)
├── sql/
│   └── schema.sql          # Full DB schema + seed data
├── index.php               # Dashboard
├── login.php               # Login
└── logout.php              # Logout
```

---

## Key Features

- **Role-based access** — Admin sees Users + Categories menus; Officers see compliance + docs
- **Auto-status sync** — Statuses recalculate on every page load in records.php
- **Expiry logic** — Active → Pending Renewal (≤30 days) → Expired (past date)
- **Printable reports** — Print CSS hides sidebar/filters; clean report output
- **Secure file serving** — Files served via PHP (not public URL); auth check enforced

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Blank page | Check PHP errors: `display_errors = On` in php.ini |
| DB connect error | Verify credentials in `config/db.php` |
| Upload fails | `chmod 777 assets/uploads/` |
| Session issues | Ensure `session.save_path` is writable |

---

## Security Notes (for production upgrade)

- Move `assets/uploads/` outside web root and update `UPLOAD_DIR`
- Use `$_ENV` / `.env` for DB credentials instead of constants
- Enable HTTPS
- Add CSRF token to all POST forms
- Hash filenames (already done — `uniqid()`)
