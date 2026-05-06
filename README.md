# 🚖 Taxi Financial Tracker

A self-hosted web application for tracking daily taxi revenue, expenses, and partner payments.

## Stack

| Layer    | Technology                          |
|----------|-------------------------------------|
| Backend  | PHP 8+ (functional style)           |
| Database | MySQL / MariaDB                     |
| Frontend | Vue.js 3 (CDN) + Tailwind CSS (CDN) |

No Node.js, npm, or build step required.

---

## Quick Start

### 1. Configure the database

Edit **`config.php`** with your MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'taxi_tracker');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
```

Set the user-management toggle if desired:

```php
define('ALLOW_USER_MODIFICATIONS', true);   // false = lock the Users table
```

### 2. Upload to your server

Copy all files to `/var/www/html/taxi-tracker/` (or any web-accessible directory).

### 3. Run setup

Open `http://your-server/taxi-tracker/setup.php` in your browser.
It will create the database, all tables, and a default admin account:

| Field    | Value     |
|----------|-----------|
| Username | `admin`   |
| Password | `admin123`|

> ⚠️ **Change the password immediately after first login, then delete `setup.php`.**

### 4. Open the application

Navigate to `http://your-server/taxi-tracker/` and sign in.

---

## Features

| Feature | Admin | Partner |
|---------|-------|---------|
| Dashboard – Caisse balance | ✅ | ✅ |
| Incomes CRUD | ✅ | Read-only |
| Outcomes CRUD | ✅ | Read-only |
| Partner Transfers CRUD | ✅ | Own records (read-only) |
| User Management | ✅ (if enabled) | — |
| Audit Logs | ✅ | ✅ |

### Caisse Calculation

```
Caisse = Sum(Incomes) − Sum(Outcomes) − Sum(Transfers)
```

---

## File Structure

```
taxi-tracker/
├── index.html       # Vue.js 3 single-page frontend
├── api.php          # RESTful API (all CRUD operations)
├── config.php       # Database credentials & feature flags
├── db.php           # PDO connection helper
├── setup.php        # One-time database initialisation wizard
├── schema.sql       # Raw SQL CREATE TABLE statements
└── .htaccess        # Apache configuration (security)
```

---

## API Reference

All requests are sent to `api.php?action=<action>` using the appropriate HTTP method.

| Action       | GET | POST | PUT `?id=X` | DELETE `?id=X` |
|--------------|-----|------|-------------|----------------|
| `login`      | —   | ✅   | —           | —              |
| `logout`     | —   | ✅   | —           | —              |
| `me`         | ✅  | —    | —           | —              |
| `config`     | ✅  | —    | —           | —              |
| `dashboard`  | ✅  | —    | —           | —              |
| `incomes`    | ✅  | ✅ admin | ✅ admin | ✅ admin |
| `outcomes`   | ✅  | ✅ admin | ✅ admin | ✅ admin |
| `transfers`  | ✅  | ✅ admin | ✅ admin | ✅ admin |
| `users`      | ✅ admin | ✅ admin (if enabled) | ✅ admin (if enabled) | ✅ admin (if enabled) |
| `audit_logs` | ✅  | —    | —           | —              |

Every admin write/update/delete is automatically recorded in `audit_logs`.
