# DVRA Membership Manager (PHP)

Standalone **PHP** scaffold (not part of any Python repo). Intended for deployment on **shared hosting** alongside other PHP—no long-lived application server required.

Uses **Slim 4**. **`/`**: Tabulator grid with filters/sort (Python-aligned). **`/members/new`**, **`/members/{id}/view`** (single-form edit), **`POST …/delete`**, validations (duplicate call sign, duplicate name-without-call, unique key number), unsaved-change guard + delete confirm mirroring Python. **`/members/{id}/payments`**: add payment dialog; inline row edits (`POST /payments/{id}/edit`); delete (`POST /payments/{id}/delete`); **`members.paid_through`** recomputed as **`MAX(payments.paid_through)`** after each change (NULL when no payments). Still not ported: spreadsheet import, JSON REST API, CSV/XLSX/PDF routes, reports, admin/reference screens (export links on `/` still 404).

Shared UI assets (`public/static/style.css`, `w2zq-site-icon-gold.png`) are **copies**; when you change branding in one stack, update the other manually if you want them to match.

## Requirements

- PHP **8.2+** with **pdo_sqlite** (default for local dev). Add **pdo_mysql** when you port the schema to MySQL.
- [Composer](https://getcomposer.org/)

## Install

```bash
composer install
mkdir -p var
```

SQLite defaults to **`var/dvra_membership.sqlite`**. Override:

```bash
export DATABASE_DSN="sqlite:$(pwd)/var/custom.sqlite"
```

Optional Slim **`BASE_PATH`** when the app is mounted under a subdirectory (e.g. `export BASE_PATH="/members-app/public"`).

Bootstrap admin when **`admin_users`** is empty: **`admin`** / **`admin123`**, overridable with **`DVRA_ADMIN_USERNAME`** and **`DVRA_ADMIN_PASSWORD`**.

Verbose Slim errors: **`export DVRA_DISPLAY_PHP_ERRORS=1`**.

## Run locally

```bash
php -S 127.0.0.1:8089 -t public public/router.php
```

- `http://127.0.0.1:8089/health` → `OK`
- `http://127.0.0.1:8089/login` → sign in

## Apache

Point **DocumentRoot** at **`public/`** and allow **`public/.htaccess`** rewrites.

## Schema

See **`database/schema.sqlite.sql`** (SQLite). For MySQL/MariaDB on shared hosting, translate types and the partial unique index on **`members.call_sign`**.
