# Deploying to https://electionsoft.in/obc

The portal runs in the **`/obc` sub-folder** of electionsoft.in, so it can share the domain with other applications.
The Android and iOS apps are built to talk to `https://electionsoft.in/obc`.

> This setup has been tested locally under an `/obc` path: web login, every page, cookies scoped to `/obc`, and the
> mobile API. It has not yet been tested on the live server or on MySQL; the automated tests run on SQLite.

## 1. Server requirements

- PHP **8.3+** with these extensions: `pdo_mysql`, `mbstring`, `xml`, `curl`, `gd`, `intl`, `zip`, `fileinfo`, `bcmath`.
- MySQL 8 (or MariaDB 10.6+) and Composer 2.
- Node 20+, needed only to build the CSS/JS. You can also build on your laptop and upload `public/build`.
- An HTTPS certificate for electionsoft.in (e.g. Let's Encrypt). **The mobile app only connects over HTTPS.**
- Upload limits big enough for biodata scans. In `php.ini` set `upload_max_filesize = 20M`, `post_max_size = 120M`
  and `max_file_uploads = 20`. On Nginx also set `client_max_body_size 120m;`.

## 2. Put the code on the server

Keep the application **outside** the web root; only its `public/` folder is exposed at `/obc`.

```bash
cd /var/www
git clone -b claude/brave-heisenberg-3c01ek https://github.com/vidhiinfo/android-protips-location.git obc-src
cd obc-src/obc-office/web
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env
php artisan key:generate
```

## 3. Production `.env`

Edit `obc-office/web/.env`. These are the values that matter:

```dotenv
APP_NAME="OBC Office"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://electionsoft.in/obc
APP_TIMEZONE=Asia/Kolkata

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=obc_office
DB_USERNAME=obc_office
DB_PASSWORD=choose-a-strong-password

# Keep the login cookie inside /obc so it never clashes with other apps on electionsoft.in
SESSION_DRIVER=database
SESSION_PATH=/obc
SESSION_COOKIE=obc_office_session
SESSION_SECURE_COOKIE=true

LOG_LEVEL=warning

# First administrator (created once by db:seed)
ADMIN_USERNAME=admin
ADMIN_PASSWORD=choose-a-strong-admin-password
SEED_DEMO=false
```

Create the database and user in MySQL:

```sql
CREATE DATABASE obc_office CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'obc_office'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT ALL PRIVILEGES ON obc_office.* TO 'obc_office'@'localhost';
```

Then create the tables, the first admin and the master data (states, posts, dropdown lists):

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
sudo chown -R www-data:www-data storage bootstrap/cache
```

Once you've signed in, remove `ADMIN_PASSWORD` from `.env` and change the password from **Users** in the portal.

### No terminal? Import the ready-made database instead

If you can't run `php artisan migrate` / `db:seed` (common on shared hosting), ask your developer for
**`obc_office.sql`**. It has all tables, the 36 states/UTs, posts, dropdown lists and one admin account, and was
tested on MySQL 8.0.

1. cPanel → **MySQL Database Wizard**: create an empty database and user with ALL PRIVILEGES.
2. cPanel → **phpMyAdmin** → click the new database on the left → **Import** → choose `obc_office.sql` → **Go**.
3. Put those database details in `.env` (step 3 above). You still need `APP_KEY`: run `php artisan key:generate`
   once, or ask your developer to generate one for you.
4. Sign in as `admin` with the password supplied with the file, then change it under **Users**.

Later updates that add tables can still be applied with `php artisan migrate --force`; the import includes
Laravel's migration history, so nothing is applied twice.

## 4. Web server: expose `public/` at `/obc`

Pick the option that matches the server.

### Option A: Nginx

Add this inside the existing `server { … }` block for electionsoft.in:

```nginx
location ^~ /obc {
    alias /var/www/obc-src/obc-office/web/public;
    try_files $uri $uri/ @obc;
    client_max_body_size 120m;

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
}
location @obc {
    rewrite ^/obc/(.*)$ /obc/index.php?/$1 last;
}
```

Then run `sudo nginx -t && sudo systemctl reload nginx`.

### Option B: Apache (VirtualHost access)

```apache
Alias /obc /var/www/obc-src/obc-office/web/public
<Directory /var/www/obc-src/obc-office/web/public>
    AllowOverride All
    Require all granted
</Directory>
```

Because this uses `Alias`, add `RewriteBase /obc` just below `RewriteEngine On` in
`obc-office/web/public/.htaccess`.

### Option C: cPanel / shared hosting (no server config access)

Upload the project outside `public_html` (e.g. `/home/USER/obc-src`), then create a symlink so `public_html/obc`
points to the app's `public` folder:

```bash
ln -s /home/USER/obc-src/obc-office/web/public /home/USER/public_html/obc
```

The included `.htaccess` works as-is in this layout. Select PHP 8.3 in cPanel's *MultiPHP Manager*.

## 5. Check it works

- Open https://electionsoft.in/obc and sign in as `admin`.
- Open https://electionsoft.in/obc/up. It should return `200` (the health check).
- Install the Android app, sign in with the same account, and check that the dashboard loads.

## 6. Backups (daily)

- The database: `mysqldump obc_office | gzip > /backups/obc-$(date +%F).sql.gz`
- Uploaded photos and documents: `obc-office/web/storage/app/private/` (member photos, biodata files, attachments)
  and `storage/app/public/branding/` (logo).

## 7. Updating later

```bash
cd /var/www/obc-src && git pull
cd obc-office/web
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Mobile app

- **Android:** `flutter build apk --release` in `obc-office/mobile` already points at `https://electionsoft.in/obc`.
  Share the APK directly, or upload an app bundle (`flutter build appbundle`) to the Play Store after setting up your
  own signing key.
- **iOS:** build on a Mac with Xcode (`flutter build ipa`) and upload to TestFlight or the App Store with your Apple
  Developer account.
- If the portal address ever changes, users can tap **Server settings** on the login screen. For new builds, pass
  `--dart-define=API_BASE_URL=https://new-address`.

## Troubleshooting: "https://electionsoft.in/obc is not working"

**Fastest way to find the problem:** upload `web/tools/obc-check.php` to `public_html/`, open
`https://electionsoft.in/obc-check.php`, fix the red items from the top down, then **delete the file**. It never
shows passwords.

| What you see | Usual cause | Fix |
|---|---|---|
| Site doesn't open at all / "can't be reached" | Domain DNS or hosting down, or no SSL | Check https://electionsoft.in itself opens; run AutoSSL in cPanel |
| **404 Not Found** | `public_html/obc` missing | `ln -s ~/obc-office/web/public ~/public_html/obc` |
| **403 Forbidden** | Host blocks symlinks (SymLinksIfOwnerMatch), or wrong permissions | Folders 755, files 644; ask the host to allow FollowSymLinks, or see "No symlinks allowed" below |
| **500 Internal Server Error** / blank page | PHP below 8.3, `.env` missing, no APP_KEY, storage not writable, DB details wrong | Run the check page; the last error is in `web/storage/logs/laravel.log` |
| Page shows but **no styling** | `public/build` not uploaded, or wrong APP_URL | Re-upload the zip; `APP_URL=https://electionsoft.in/obc`; `php artisan config:clear` |
| **419 Page Expired** at login | Opened over http, or SESSION settings | Always use **https**; `SESSION_PATH=/obc`, `SESSION_SECURE_COOKIE=true`; `php artisan config:clear` |
| Login says wrong password | `db:seed` not run, or a different password was used | `php artisan db:seed --force` (prints the admin password if ADMIN_PASSWORD was empty) |
| Mobile app: "Cannot reach the server" | Site not on https, or the web portal itself isn't working yet | Fix the web portal first; the app needs https://electionsoft.in/obc/api/v1 |

After changing `.env`, always run `php artisan config:clear` (and `php artisan config:cache` once it works).

### No symlinks allowed (some shared hosts)

1. Create the folder `public_html/obc`.
2. Copy everything from `obc-office/web/public/` into it, including the hidden `.htaccess` file.
3. Edit `public_html/obc/index.php` and replace `__DIR__.'/../` with `__DIR__.'/../../obc-office/web/` in the three
   lines that load `storage/framework/maintenance.php`, `vendor/autoload.php` and `bootstrap/app.php`.
4. Repeat step 2 after every update.
