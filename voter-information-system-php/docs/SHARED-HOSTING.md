# Hosting the Voter Information System (PHP version)

This guide puts the PHP (Laravel 12) version on **ordinary shared hosting**: GoDaddy, HostGator, Hostinger, BigRock or any host with **cPanel**. It also covers a **Windows Server (IIS)** at the end.

You do **not** need SSH, Composer, Node.js or a terminal. Everything is done in cPanel's File Manager, phpMyAdmin and Cron Jobs. The zip already contains the `vendor` folder.

> **Time needed:** about 20–30 minutes the first time.

---

## 1. What your hosting plan needs

| Item | Needed | Where to check in cPanel |
|---|---|---|
| PHP | **8.2 or newer** (8.3 recommended) | *Software → Select PHP Version* or *MultiPHP Manager* |
| MySQL / MariaDB | MySQL 5.7+ / 8.x or MariaDB 10.3+ | *Databases → MySQL Databases* |
| PHP extensions | `pdo_mysql, mbstring, gd, zip, xml, dom, fileinfo, openssl, ctype, iconv, tokenizer` (`intl` optional) | *Select PHP Version → Extensions* |
| Disk space | about 200 MB for the application, plus the database | |
| Cron jobs | needed only for SMS / WhatsApp | *Advanced → Cron Jobs* |

GoDaddy (Linux cPanel), HostGator (any cPanel plan) and Hostinger meet all of these. Avoid the cheapest "Windows / Plesk" plans unless you follow section 10.

### Recommended PHP settings

In *Software → MultiPHP INI Editor* (or *Select PHP Version → Options*), set:

| Setting | Value | Why |
|---|---|---|
| `memory_limit` | `256M` | PDF exports of large booths |
| `max_execution_time` | `300` | Excel import / export of a whole assembly |
| `upload_max_filesize` | `50M` | Excel voter lists |
| `post_max_size` | `50M` | same |

---

## 2. Create the database

1. cPanel → **MySQL Database Wizard**.
2. Database name: `voter_db`. cPanel adds your account prefix, so it becomes e.g. `myacct_voter_db`.
3. User: `voter_app`, with a strong password. It becomes e.g. `myacct_voter_app`.
4. Privileges: tick **ALL PRIVILEGES** → *Next step*.
5. Write down the **full** database name, user name and password.

## 3. Import the tables

1. cPanel → **phpMyAdmin**.
2. Click your database (e.g. `myacct_voter_db`) in the left column.
3. **Import** tab → *Choose file* → select `db/schema.sql` from the zip (unzip it on your PC first) → **Go**.
4. The database must use the character set **utf8mb4** with the collation **utf8mb4_unicode_ci** (choose it in phpMyAdmin → Operations if you are asked). Never choose `utf8` / `utf8mb3`.
5. You should see 16 tables: `voters`, `assemblies`, `booths`, `app_users` and the rest.

> The tables use **utf8mb4**, so Hindi, Urdu, Bengali, Tamil and other scripts are stored correctly. Do not change the collation.

## 4. Upload the application

Upload `voter-information-system-php.zip` with **File Manager → Upload**, then right-click it → **Extract**.

Choose **one** of the two layouts.

### Layout A: subdomain (recommended, most secure)

For example `voters.yourdomain.com`.

1. Extract the zip into your **home folder**, *not* inside `public_html`:
   `/home/myacct/voter-information-system-php/`
2. cPanel → **Domains** (or *Subdomains*) → *Create a new domain*: `voters.yourdomain.com`.
3. Set **Document Root** to `voter-information-system-php/public`.
   Untick "Share document root with yourdomain.com" if it is shown.
4. Only the `public` folder is visible on the web. The code, `.env` and logs cannot be reached.

### Layout B: main domain inside `public_html`

Use this only if you cannot create a subdomain.

1. Extract so that the files sit **directly** in `public_html`, so you see `public_html/app`, `public_html/public`, `public_html/.htaccess` and so on.
   If the zip created `public_html/voter-information-system-php/`, open that folder, select all, **Move** to `/public_html`.
2. The `.htaccess` in the root sends visitors into `public/` and **blocks** `.env`, the code, `storage` and `vendor`.
3. Check it: `https://yourdomain.com/.env` must show **403 Forbidden**.

> File Manager hides dot-files by default. Click **Settings → Show Hidden Files** to see `.htaccess` and `.env`.

## 5. Settings file `.env`

1. In the application folder, copy `.env.example` to **`.env`** (right-click → Copy, then rename).
2. Edit `.env` and change:

```ini
APP_URL=https://voters.yourdomain.com

DB_HOST=localhost
DB_DATABASE=myacct_voter_db
DB_USERNAME=myacct_voter_app
DB_PASSWORD=the-password-from-step-2

ADMIN_USER=admin
ADMIN_PASSWORD=Choose@StrongPassword1
```

3. Leave `APP_KEY=` empty. The application creates its own key on the first visit.
4. Keep `APP_DEBUG=false` on a live site. Set it to `true` only for a few minutes if you need to see an error.

## 6. Folder permissions

File Manager → right-click → **Change Permissions**:

| Folder | Permission |
|---|---|
| `storage` (and everything inside) | `755` (tick "recurse" if offered) |
| `bootstrap/cache` | `755` |
| `.env` | `644` (must be writable by PHP the first time, for the key) |

On most cPanel hosts PHP runs as your own user, so `755` / `644` is enough. Never use `777`.

## 7. First login

1. Open `https://voters.yourdomain.com`. The login page with the banner appears.
2. Log in with `ADMIN_USER` / `ADMIN_PASSWORD` from `.env`. Leave Assembly at **All Assemblies**.
3. Go to **user menu → Change Password** straight away.
4. Open **Admin → System Information**. It must show *Database character set: Unicode (utf8mb4)* and your PHP version.

Then set up your data:

1. **Masters → Assemblies**, **Booths**, **Parties**, **Elections**, **Candidates**.
2. **Voters → Bulk Import**: download the template, fill it in Excel, upload. A ward-wise sheet with columns such as *Ward, Booth, V.SNo., VoterID, Name, Gender, Relative's Name, Age, Houseno, Address, Mobile(Original), Mobile1, Membershipno, DOB, Marriage Anniv., Deleted, … Caste, Poling Station* can be uploaded as it is. Choose its assembly in "Assembly for rows without one". A whole assembly (1 lakh+ rows) in one file is fine. On hosts with a short time limit, upload booth-wise files.
3. **Admin → Users**: create users, give them roles and lock them to an assembly if needed.

## 8. HTTPS (free SSL)

cPanel → **SSL/TLS Status** → *Run AutoSSL* (or *Let's Encrypt*). Once the padlock works, make sure `APP_URL` in `.env` starts with `https://`. The application then makes every link https.

## 9. Cron job for SMS / WhatsApp (optional)

Messages are sent in the background by a command that runs every minute. It respects the sending speed, the 9 am – 9 pm window and the opt-out list.

cPanel → **Cron Jobs** → *Add New Cron Job*:

* Common setting: **Once Per Minute (\* \* \* \* \*)**
* Command (change `myacct` and the PHP version to yours):

```bash
/usr/local/bin/ea-php83 /home/myacct/voter-information-system-php/artisan messages:send >/dev/null 2>&1
```

| Host | PHP command to use |
|---|---|
| GoDaddy cPanel | `/usr/local/bin/ea-php83` (or `/usr/local/bin/php`) |
| HostGator | `/opt/cpanel/ea-php83/root/usr/bin/php` (or `/usr/local/bin/php`) |
| Hostinger (hPanel → Advanced → Cron Jobs) | `/usr/bin/php` |

For Layout B the path is `/home/myacct/public_html/artisan`.

Until you set a provider, messaging runs in **dry-run** mode: messages are recorded, never sent. To send for real, add your provider to `.env`:

* `SMS_PROVIDER=twilio` or `http` (MSG91, Textlocal, Fast2SMS and similar Indian gateways).
* `WHATSAPP_PROVIDER=twilio` or `meta`.

The provider keys go in `.env` too (see the comments in `.env.example`), never in the database. In India, SMS needs DLT-registered templates, and WhatsApp needs templates approved by Meta.

---

## 10. Windows Server (IIS) instead of cPanel

1. Install **PHP 8.3 (NTS, x64)** from windows.php.net into `C:\PHP`. Enable the extensions listed in section 1 in `php.ini`.
2. In IIS install **CGI** (Server Manager → Web Server → Application Development → CGI), the **URL Rewrite** module, and register PHP with *Handler Mappings → Add Module Mapping* (`*.php`, `FastCgiModule`, `C:\PHP\php-cgi.exe`).
3. Install **MySQL 8**, create the database and user, and import `db/schema.sql` (MySQL Workbench or the `mysql` command line).
4. Copy the folder to `C:\inetpub\voter-information-system-php` and create an IIS site whose **physical path is the `public` folder**. `public\web.config` is already included.
5. Give **IIS_IUSRS** *Modify* permission on `storage`, `bootstrap\cache` and `.env`.
6. SMS / WhatsApp: in **Task Scheduler**, create a task that runs every 1 minute:
   `C:\PHP\php.exe C:\inetpub\voter-information-system-php\artisan messages:send`

---

## 11. Updating to a newer version

1. Back up first (section 12).
2. Upload the new zip and extract it over the old folder. **Keep your `.env`** and the `storage` folder.
3. If the release notes mention database changes, import the supplied SQL file in phpMyAdmin.
4. Delete the files in `bootstrap/cache` (not the folder) so the new code is used.

### Database changes in this version

The voters table has 16 new columns: membership no., DOB, marriage anniversary, deleted, shift 1 / 2, place of origin, current place, linked with, social / political / organisational status, beneficiary 1–3 and caste.

* **New installation**: nothing extra to do. `db/schema.sql` already has the columns.
* **Database created with an older version**: phpMyAdmin → your database → **Import** → `db/upgrade-2026-11-voter-profile-fields.sql` (run it once). Existing voters are kept. The Java version keeps working with the upgraded table.

## 12. Backups

* **Database**: phpMyAdmin → your database → **Export** → *Quick* → SQL. Or use cPanel → **Backup** → *Download a MySQL Database Backup*.
* **Files**: only `.env` and the `storage/app` folder change; everything else is in the zip.
* Most hosts also keep daily backups. Check the host's *Backup* or *JetBackup* page.

## 13. How big can it get?

These were measured on MySQL with **1 lakh voters in one assembly** (3 lakh in total) and PHP limited to 128 MB:

| Action | Time | PHP memory |
|---|---|---|
| Voter list page (search, sort, filter) | 0.3 s | 22 MB |
| Dashboard with charts | 1.2 s | 34 MB |
| Excel export of the whole assembly (1 lakh rows) | 28 s | 38 MB |
| Excel import of 1 lakh rows | 42 s | 32 MB |
| Duplicate voter report for the assembly | 6 s | 66 MB |
| PDF of a 2,000-voter booth list in Hindi | 13 s | 82 MB |

* **PDF and Print** are limited to 5,000 voters per document, because PDF layout needs about 4.5 MB of memory per 1,000 rows. The app asks the user to narrow the list (for example by booth) or use Excel. Change it with `PDF_MAX_ROWS` in `.env` if your host gives more memory.
* Voter-list reports (first-time voters, phone-bank list, duplicates …) include up to 50,000 rows and show a notice beyond that.
* Use **one database for all assemblies**. Every user's work is limited to the assembly chosen at login, or the one assigned to them.

## 14. Troubleshooting

| Problem | Cause and fix |
|---|---|
| **500 Server Error** | Look in `storage/logs/laravel-YYYY-MM-DD.log`. Common causes: wrong DB name or password in `.env` (remember the cPanel prefix), PHP older than 8.2, a missing extension. |
| Page shows a **list of files** or the PHP code | The document root is not the `public` folder (Layout A), or `.htaccess` was not uploaded (Layout B: show hidden files). |
| Page without colours or icons (plain HTML) | `APP_URL` is wrong, or for Layout B the `.htaccess` is missing. |
| "No application encryption key" | `.env` is not writable. Set it to `644`, or paste a key: `APP_KEY=base64:` followed by 44 random base64 characters. |
| **419 Page Expired** at login | `APP_URL` is http but the site opens on https (or the reverse). Make them the same. Also check that `storage/framework/sessions` is writable. |
| Hindi or Urdu shows as `????` | The database or tables are not utf8mb4. Re-create the database and import `db/schema.sql` again. |
| Import or export stops halfway | Raise `max_execution_time` (section 1), or import booth-wise files. |
| SMS stay "Pending" | The cron job is not running. Check the PHP path in section 9. cPanel can e-mail you the cron output if you remove `>/dev/null 2>&1`. |
| Forgot the admin password | phpMyAdmin → **SQL** tab: `UPDATE app_users SET password_hash = '{noop}Temp@12345' WHERE username = 'admin';` → log in with `Temp@12345` → **Change Password** at once (it is then stored encrypted again). |

## 15. Using your existing users table

To log in with the users and roles you already have, set `USER_SOURCE=external` in `.env` and fill in `USERS_QUERY`, `ROLES_QUERY` and `USERS_ROLE_MAP`. Examples are in `.env.example`. Your passwords can be bcrypt, SHA-256, MD5 or plain text (`USERS_PASSWORD_ENCODING`). Your tables are only read, never changed.

## 16. Java and PHP versions together

Both versions use exactly the same tables and the same password format. You can run either one against the same database, or move from one to the other at any time without converting data.
