# BizComply — Business Compliance Management Platform

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
