# Elite Smiles CRM — Deployment Guide

## Step 1 — Create your .env file

Copy `.env.example` to `.env` in the same root folder (next to `landing.php`).

Fill in your real values:

```
DB_HOST=localhost
DB_PORT=3306
DB_NAME=nbkuymev_elitesmilesmktg
DB_USER=nbkuymev_esmadmin
DB_PASS=your_real_password_here

APP_KEY=   ← generate with: php -r "echo bin2hex(random_bytes(32));"

ELITE_PUSHOVER_APP_TOKEN=your_token
ELITE_PUSHOVER_USER_KEY=your_key
ELITE_QUICK_ACTION_SECRET=  ← another random string
```

**Never commit .env to git.**

---

## Step 2 — Upload files

Upload the entire contents of this zip into your server at:
```
hi.elitesmilesutah.com/crm/
```

Using cPanel File Manager or FTP. Overwrite everything except your existing `.env` if you already have one.

---

## Step 3 — Run the SQL installer

In cPanel → phpMyAdmin → select your database → Import tab → upload `install.sql`.

This will:
- Create `landing_pages` table (if not exists)
- Create `leads` table (if not exists)
- Create `users` table (if not exists)
- Seed all 200 landing page slugs
- Activate the 5 Draper veneers pages to start

---

## Step 4 — Test a landing page

Visit:
```
https://hi.elitesmilesutah.com/crm/landing.php?slug=veneers-draper-premium-trust-v1
```

You should see the full premium landing page with the quiz.

---

## Step 5 — Activate more pages

In phpMyAdmin, run:
```sql
UPDATE landing_pages SET is_active = 1 WHERE city = 'lehi';
-- or
UPDATE landing_pages SET is_active = 1 WHERE procedure_type = 'veneers';
-- or activate all at once:
UPDATE landing_pages SET is_active = 1;
```

Or use the Admin panel: `landing_pages.php` (admin role required).

---

## Step 6 — Add to .htaccess (optional clean URLs)

Add to your `/crm/.htaccess`:
```apache
RewriteEngine On
RewriteRule ^l/([a-z0-9-]+)/?$ landing.php?slug=$1 [QSA,L]
```

Then pages are accessible at:
```
https://hi.elitesmilesutah.com/crm/l/veneers-draper-premium-trust-v1
```

---

## Adding a new city (scalability)

1. Create `app/landing_pages/configs/cities/new-city.php`:
```php
<?php declare(strict_types=1);
return [
    'label' => 'New City',
    'state' => 'Utah',
    'seo_title_suffix' => 'New City, Utah',
];
```

2. Run the SQL INSERT for that city's slugs (or add them manually in phpMyAdmin).

3. Activate them with:
```sql
UPDATE landing_pages SET is_active = 1 WHERE city = 'new-city';
```

That's it — the router handles everything automatically.

---

## Security checklist

- [ ] .env file created with real secrets
- [ ] .env is NOT inside public web root, or is protected by .htaccess
- [ ] DB_PASS is changed from the old hardcoded value
- [ ] APP_KEY is a real random 64-char hex string
- [ ] ELITE_QUICK_ACTION_SECRET is a real random string
- [ ] Backup files removed (landing.php.bak, landing-live-backup.php, etc.)

---

## File structure overview

```
crm/
├── .env                          ← your secrets (never commit)
├── .env.example                  ← template
├── install.sql                   ← run once in phpMyAdmin
├── landing.php                   ← universal landing router
├── landing_pages.php             ← admin panel
├── dashboard.php                 ← CRM dashboard
├── login.php
├── app/
│   ├── config/config.php         ← reads from .env
│   ├── core/
│   │   ├── auth.php
│   │   ├── db.php                ← fixed db_execute + timezone
│   │   ├── env.php               ← .env loader
│   │   ├── helpers.php
│   │   └── mailer.php
│   ├── leads/
│   │   ├── lead_meta.php
│   │   └── lead_service.php
│   └── landing_pages/
│       ├── bootstrap.php         ← context builder
│       ├── configs/
│       │   ├── landing-map.php   ← all 200 page slugs
│       │   ├── procedures/       ← veneers, implants, all_on_x, etc.
│       │   ├── cities/           ← draper, lehi, south-jordan, etc.
│       │   └── angles/           ← premium_trust, financing, etc.
│       ├── content/shared/
│       │   ├── doctor-authority.php
│       │   └── reviews.php
│       └── question_sets/        ← quiz step definitions
└── assets/
    └── img/landings/             ← hero images, gallery, before/after
```
