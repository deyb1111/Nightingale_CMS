# Nightingale Clinic Management System

A full PHP + MySQL implementation of the Nightingale CMS, faithful to the
**System Reference** (15-table schema, 6 stored procedures, 5 audit triggers,
two-factor authentication via RFC 6238 TOTP, three role-based dashboards).

The project ships with **5 years of realistic seed data** (May 2021 → May 2026)
so the dashboards have meaningful KPIs from the moment the SQL imports finish.

---

## 1.  At a glance

| Layer        | Technology                                                   |
|--------------|--------------------------------------------------------------|
| Web server   | Apache (XAMPP / Laragon / WAMP)                              |
| PHP          | 8.1+                                                         |
| Database     | MySQL 8 / MariaDB 10.6 (InnoDB)                              |
| TOTP         | `spomky-labs/otphp`                                          |
| QR codes     | `bacon/bacon-qr-code`                                        |
| Email        | `phpmailer/phpmailer` (or "log only" mode for dev)           |
| Env loading  | `vlucas/phpdotenv`                                           |

Folder layout (everything inside `nightingale_cms/`):

```
nightingale_cms/
├── public/                    ← Apache document root
│   ├── login.php
│   ├── admin-login.php
│   ├── totp-setup.php
│   ├── password-request.php / password-reset.php
│   ├── nurse.php / patient.php / admin.php
│   ├── api/
│   │   ├── _init.php
│   │   ├── auth/login.php / verify-totp.php / admin-login.php / …
│   │   ├── queue.php  consultation.php  vitals.php  dispense.php
│   │   ├── referral.php  medicines.php  restock.php
│   │   ├── ape.php  patients.php  reports.php  audit.php  settings.php
│   └── assets/css|js/
├── lib/                       ← server-only PHP (autoloaded)
│   ├── bootstrap.php
│   ├── db.php session.php csrf.php json_response.php
│   ├── totp.php qr.php mailer.php auth_helpers.php
├── sql/
│   ├── 01_schema.sql
│   ├── 02_clinic_settings.sql
│   ├── 03_storedprocs_triggers.sql
│   ├── 04_security_users.sql
│   ├── 05_seed_reference.sql
│   └── 06_seed_5_years.sql        ← generated
├── tools/
│   └── generate_seed_5y.php       ← regenerates 06_seed_5_years.sql
├── storage/                   ← writeable: mail.log, sessions, cache
├── composer.json / composer.lock
├── .env.example
└── README.md
```

---

## 2.  Setup — quick path (XAMPP on Windows)

The project assumes XAMPP, but anything with PHP 8.1+ and MySQL 8 works
(Laragon, WAMP, MAMP, plain LAMP, Docker, …).

### 2.1  Pre-requisites

* **XAMPP 8.1+** — <https://www.apachefriends.org/>
* **Composer** — <https://getcomposer.org/Composer-Setup.exe>
* **Git** (only if you cloned the project)
* **Authenticator app** for testing TOTP — Google Authenticator, Authy, 1Password, Microsoft Authenticator, etc.
* **(Optional) SMTP credentials** — Gmail App Password / Mailtrap / SMTP2GO for real password-reset and notification emails.  In development you can leave `MAIL_LOG_ONLY=true` and emails get appended to `storage/mail.log` — **no SMTP account required**.

### 2.2  Drop the project into Apache

```
C:\xampp\htdocs\nightingale_cms\          ← entire project root
```

Then either:

* point the browser at  `http://localhost/nightingale_cms/public/`, **or**
* edit `C:\xampp\apache\conf\httpd.conf` and set
  ```apache
  DocumentRoot "C:/xampp/htdocs/nightingale_cms/public"
  <Directory "C:/xampp/htdocs/nightingale_cms/public">
      AllowOverride All
      Require all granted
  </Directory>
  ```
  so `/` already serves the public folder.

### 2.3  Install PHP dependencies

From inside the project root:

```
composer install --no-dev
```

(Composer fetches `otphp`, `phpmailer`, `bacon-qr-code`, `phpdotenv`.)

### 2.4  Configure the environment

```
cp .env.example .env
```

Open `.env` and at minimum set:

```dotenv
APP_URL=http://localhost/nightingale_cms/public
APP_KEY=<paste 32 random hex bytes — see below>

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=nightingale_cms
DB_USER=cms_app_user
DB_PASSWORD=Cms@AppUser2026!

# Leave these alone for development:
MAIL_LOG_ONLY=true
ADMIN_NOTIFICATION_EMAIL=admin@example.com
TOTP_ISSUER=Nightingale CMS
```

Generate a strong `APP_KEY` (used to sign pending-TOTP tokens):

```
php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"
```

### 2.5  Create the database & users

Open `phpMyAdmin` (or `mysql -u root -p`) and run **the SQL files in order**:

```sql
SOURCE sql/01_schema.sql;                 -- 15 tables
SOURCE sql/02_clinic_settings.sql;        -- clinic_settings + password_reset_tokens
SOURCE sql/03_storedprocs_triggers.sql;   -- 6 stored procs + 5 audit triggers
SOURCE sql/04_security_users.sql;         -- creates cms_app_user, cms_readonly
SOURCE sql/05_seed_reference.sql;         -- 5 depts, 12 medicines, 3 nurses, 2 admins
SOURCE sql/06_seed_5_years.sql;           -- the big one: ~14k consultations, ~17k dispenses, ~1.8k referrals, 5 yrs of audit
SOURCE sql/07_demo_patient.sql;           -- fixed "patient/password" demo account
```

> **Where does `06_seed_5_years.sql` come from?**
> It's already generated and committed.  If you want to regenerate (e.g. with
> different randomisation seed or volume), run
> `php tools/generate_seed_5y.php` and it will overwrite that file.

### 2.6  Try it

* Visit **`http://localhost/nightingale_cms/public/login.php`**.
* Sign in with one of the demo accounts (Section 3).

---

## 3.  Demo accounts

> **Security:** every demo account uses the password `password`.  Change them
> all in production via the admin dashboard or:
> `UPDATE user_account SET password_hash = '<bcrypt>' WHERE username = '...';`

| Portal              | Username    | Password   | Role    | TOTP             |
|---------------------|-------------|------------|---------|------------------|
| `login.php`         | `nurse`     | `password` | nurse   | required (setup) |
| `login.php`         | `blim`      | `password` | nurse   | required (setup) |
| `login.php`         | `ccruz`     | `password` | nurse   | required (setup) |
| `login.php`         | `patient`   | `password` | patient | optional         |
| `admin-login.php`   | `admin`     | `password` | admin   | required (setup) |
| `admin-login.php`   | `hr.admin`  | `password` | admin   | required (setup) |

The first time a nurse / admin signs in, the system redirects to
**`totp-setup.php`**, where:

1. A QR code is shown (scanned by Google Authenticator, Authy, etc.).
2. The user enters the first 6-digit code to activate.
3. Eight one-time **backup codes** are displayed (and emailed via `MAIL_LOG_ONLY`).

After this, every subsequent sign-in asks for the 6-digit code (or a backup
code) before the session is created.

The **patient** account is not forced into TOTP by default; an admin can
toggle it on by setting `user_account.totp_enabled = 1`.

---

## 4.  Authentication architecture

```
                 ┌──────────────────────────────────────┐
                 │ login.php  ─→ /api/auth/login.php    │  password verify
                 └──────────────────────────────────────┘
                            │ (totp_required)
                            ▼
                 ┌──────────────────────────────────────┐
                 │ TOTP overlay  ─→ /api/auth/verify-totp │  6-digit code
                 └──────────────────────────────────────┘
                            │ (authenticated)
                            ▼
                 ┌──────────────────────────────────────┐
                 │  PHP $_SESSION  +  X-CSRF-Token      │
                 └──────────────────────────────────────┘
```

* Passwords: `password_hash` / `password_verify` (bcrypt cost 12).
* TOTP: 30-s window, ±1 step drift tolerance, 20-byte Base32 secret per RFC 6238.
* Admin portal lives at `admin-login.php` and is **never linked from `login.php`** (it is referenced by a near-invisible link near the bottom).
* Optional IP allowlist via `ADMIN_IP_ALLOWLIST` in `.env`.
* CSRF: every state-changing request must echo back `X-CSRF-Token`. The token comes from `/api/auth/session.php` and is stored in JS only (never in localStorage).
* Brute-force: `login_attempts` table is checked — 5 failed attempts in 15 min → HTTP 429.
* Sessions: HTTP-Only, `SameSite=Lax`, **8 h absolute lifetime** (configurable via `SESSION_LIFETIME_SECONDS`).
* Audit: every sensitive table has a database trigger writing into `audit_log`.  Triggers read `@current_user_id` / `@current_admin_id`, set automatically by `Session::start()`.

### 4.1  REST endpoints (response: JSON)

| Endpoint                            | Method | Auth      | Purpose                                                |
|-------------------------------------|--------|-----------|--------------------------------------------------------|
| `auth/login.php`                    | POST   | none      | Step 1 — username/password                             |
| `auth/verify-totp.php`              | POST   | none      | Step 2 — TOTP code (or backup code)                    |
| `auth/admin-login.php`              | POST   | none      | Admin password step                                    |
| `auth/admin-verify-totp.php`        | POST   | none      | Admin TOTP step                                        |
| `auth/totp-setup.php`               | POST   | pending   | Initialise + confirm TOTP enrollment, returns backup codes |
| `auth/logout.php`                   | POST   | session   | Destroy session                                        |
| `auth/session.php`                  | GET    | optional  | Returns user info + CSRF token                         |
| `auth/password-request.php`         | POST   | none      | Email-based reset request                              |
| `auth/password-reset.php`           | POST   | none      | Reset password with token                              |
| `queue.php`                         | GET/POST/PATCH | session | Daily queue                                       |
| `consultation.php`                  | GET/POST | session | Open / close consultations, fetch chart                 |
| `vitals.php`                        | POST   | nurse     | Record vitals                                          |
| `medicines.php`                     | GET    | session   | List + stock status                                    |
| `dispense.php`                      | POST   | nurse     | `sp_dispense_medicine` w/ low-stock alerts             |
| `restock.php`                       | POST   | admin     | `sp_restock_medicine`                                  |
| `referral.php`                      | POST   | nurse     | Create referral + email                                |
| `ape.php`                           | GET/POST | session | APE compliance                                        |
| `patients.php`                      | GET    | session   | Search / `mode=me` for patient view                    |
| `reports.php`                       | GET    | nurse/admin | KPIs, top illnesses, by-department, inventory, monthly trend |
| `audit.php`                         | GET    | admin     | System audit log                                        |
| `settings.php`                      | GET/POST | session/admin | Clinic settings                                  |

---

## 5.  Database design (matches Reference §03)

15 base tables in 3NF + InnoDB:

`department, employee, user_account, admin_account, nurse_profile, queue,
medicine, consultation, vital_signs, referral, medicine_dispensed,
inventory_log, ape_record, audit_log, totp_backup_codes`

Plus three operational tables added by `02_clinic_settings.sql`:

* `clinic_settings` (key/value)
* `password_reset_tokens` (hashed, 30-min TTL, single-use)
* `login_attempts`        (rate-limit + forensic trail)

And the reporting view `v_consultation_full`.

DB users (created by `04_security_users.sql`):

| User             | Password              | Privileges                                            |
|------------------|-----------------------|-------------------------------------------------------|
| `cms_app_user`   | `Cms@AppUser2026!`    | SELECT/INSERT/UPDATE on app tables, EXECUTE on procs  |
| `cms_readonly`   | `Cms@ReadOnly2026!`   | SELECT only — for reporting tools                     |

**Change these passwords before production deployment** by running
`ALTER USER 'cms_app_user'@'localhost' IDENTIFIED BY 'NEW_PASSWORD';`
and updating `.env` accordingly.

---

## 6.  Five years of seed data

`tools/generate_seed_5y.php` deterministically builds `sql/06_seed_5_years.sql`
(re-runnable with the same output thanks to `mt_srand(2026)`):

| Table                | Rows generated |
|----------------------|---------------|
| `employee`           | 250           |
| `user_account`       | 250 patients  |
| `queue`              | ~14,629       |
| `consultation`       | ~14,629       |
| `vital_signs`        | ~14,629       |
| `medicine_dispensed` | ~16,972       |
| `referral`           | ~1,848        |
| `inventory_log`      | ~17,326       |
| `ape_record`         | ~1,115        |
| `audit_log`          | ~70,000 (auto-populated by triggers) |

Realism tweaks:

* Working days only (PH holidays skipped).
* Seasonal load: Jul–Sep flu spike (×1.4), Dec–Jan colds (×1.2), Apr dengue (×1.3).
* Case mix: **65% illness, 18% injury, 12% follow-up, 5% emergency**.
* Stock levels never go negative — every dispense respects available stock and triggers a monthly restock cycle.
* Medicines, complaints, hospitals, doctors are all Philippines-relevant.

To regenerate (changes the seed inside the generator first, then):

```
php tools/generate_seed_5y.php
mysql -u root -p nightingale_cms < sql/06_seed_5_years.sql
```

---

## 7.  Email API setup

`lib/mailer.php` is the single sender.  Behaviour controlled by `.env`:

```
MAIL_LOG_ONLY=true          # default — append all emails to storage/mail.log
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=clinic@yourcompany.com
SMTP_PASSWORD=…app password…
SMTP_ENCRYPTION=tls
SMTP_FROM=no-reply@nightingale.clinic
SMTP_FROM_NAME=Nightingale Clinic
```

Common providers:

* **Gmail / Workspace** — turn on 2FA on the Google account, then create an
  *App password* at <https://myaccount.google.com/apppasswords>.
  Host `smtp.gmail.com`, port `587`, encryption `tls`.
* **Mailtrap (dev)** — sign up at <https://mailtrap.io>, copy SMTP credentials
  from your inbox.  Host `sandbox.smtp.mailtrap.io`, port `2525`, encryption `tls`.
* **SMTP2GO / SendGrid / Postmark** — use the SMTP details they provide.

The system sends emails for:

1. **TOTP enrollment** — backup codes mailed once, never again.
2. **Password reset** — single-use 30-minute link.
3. **Referral notifications** — to `ADMIN_NOTIFICATION_EMAIL` for hospital / specialist / emergency referrals.
4. **Low-stock alerts** — when `sp_dispense_medicine` brings stock at or below `min_stock`.

---

## 8.  Production hardening checklist

Before going live:

- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] `SESSION_COOKIE_SECURE=1` and serve over HTTPS only.
- [ ] Generate a fresh 32-byte `APP_KEY`.
- [ ] Change `cms_app_user` and `cms_readonly` passwords.
- [ ] Change every demo account password (`UPDATE user_account SET password_hash = '<bcrypt>' …`).
- [ ] Disable / delete the secondary `hr.admin` if you don't need it.
- [ ] Turn off `MAIL_LOG_ONLY`, configure real SMTP.
- [ ] Set `ADMIN_IP_ALLOWLIST` to your office IP(s).
- [ ] Move `storage/` and `vendor/` outside the document root if your hosting allows it.
- [ ] Place an Apache rewrite that denies access to anything that's not in `public/` (a sample `.htaccess` is in `public/`).
- [ ] Schedule a daily MySQL dump (audit_log alone grows ~14k rows/year).
- [ ] Schedule `DELETE FROM password_reset_tokens WHERE expires_at < NOW() - INTERVAL 7 DAY` weekly.
- [ ] Schedule `DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 90 DAY` weekly.

---

## 9.  Frequently-asked questions

### Why are admin and user accounts in different tables?
Per System Reference §03 #04, this is intentional: admins are clinic
administrators, *not* employees in the HR sense, and never appear in the
employee table.  Joining them would force a leaky `NULL employee_id` and
make audit queries error-prone.

### Where do I see what's been logged in `mail.log`?
`storage/mail.log` — appended by every "send" while `MAIL_LOG_ONLY=true`.
Open it in any text editor.

### How do I add a new nurse?
1. Insert into `employee` (HR data).
2. Insert into `user_account` with `role='nurse'` and the bcrypt of their initial password.
3. Insert into `nurse_profile` (PRC license, specialization).
   Or simply call `CALL sp_register_nurse(...)`.

### How do I bulk-load my own historical data?
Replace `sql/06_seed_5_years.sql` with your own SQL dump.  As long as the
schema in `01_schema.sql` is intact, the dashboards adapt automatically.

---

## 10.  License

MIT — do whatever you'd like, but the dashboards are built for the
IT103 Nightingale Clinic capstone and reference clinical workflows.
**Don't deploy in a real clinic without an information-security review.**
#   N i g h t i n g a l e _ C M S  
 