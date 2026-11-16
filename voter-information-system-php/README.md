# Voter Information System: PHP (Laravel 12) version

The PHP version of the Voter Information System. It has the same screens, the same features and the same MySQL tables as the Java (Spring Boot) version, and it runs on **ordinary shared hosting** (GoDaddy, HostGator, Hostinger … with cPanel) without SSH.

**To put it online, follow [docs/SHARED-HOSTING.md](docs/SHARED-HOSTING.md).**

## Features

* **Voters**: search across English and local-language names, father/husband name, EPIC, mobile, house no. and address. Filters by assembly, booth, gender, age, district, village/ward and mobile. Sortable columns, paging, add / edit / delete, voter slip.
* **Voter profile and outreach fields**: membership no., date of birth, marriage anniversary, caste, social / political / organisational status, 3 government-scheme beneficiary fields, place of origin, current place, linked with, shift 1 / 2, and a "deleted from roll" flag. You can filter by caste, political status, beneficiary, membership no. and deleted. Deleted voters are never messaged.
* **House No popup**: click a house number to see everyone at that house number and address.
* **Export and print**: Excel (any size, streamed) and PDF (Hindi, Bengali, Urdu, Tamil … correctly shaped), plus print view, for voter lists and every report.
* **Bulk import** from Excel. The template and column names work in all 9 languages. Ward-wise sheets are also read directly (`Ward, Booth, V.SNo., VoterID, Name, Gender, Relative's Name, Age, Houseno, Address, Mobile(Original), Mobile1, Membershipno, DOB, Marriage Anniv., Deleted, Shift1, Shift2, Place of Origin, Current Place, Linked With, Social Status, Political Status, Organisational Status, Beneficiary1-3, Caste, Poling Station`); pick the assembly on the import page. Dates may be dd-mm-yyyy, dd/mm/yyyy, yyyy-mm-dd or Excel dates. Age is calculated from DOB when empty.
* **9 languages**: English, हिन्दी, বাংলা, اردو (right-to-left), मराठी, ગુજરાતી, ਪੰਜਾਬੀ, தமிழ், తెలుగు, chosen on every page. Data is stored as utf8mb4 Unicode.
* **Login**: username, password and an Assembly drop-down. A user can be locked to one assembly, and all work is limited to the chosen assembly.
* **Role-based access**: Admin, Operator, Viewer, plus your own roles from 17 permissions. Users can also come from **your existing users / roles tables** (`USER_SOURCE=external`).
* **Campaign dashboard**: 8 charts (age/gender, booth sizes, lead/trail by booth, vote share, turnout, mobile reach, young voters, households).
* **15 reports**: assembly / booth / village summaries, age-gender, social category, caste-wise summary, birthdays and anniversaries in the next 30 days, first-time voters, voters 85+, large households, phone-bank list, results summary, party performance, swing booths, possible duplicate voters (same assembly or same village/ward).
* **Analytics** (menu *Analytics*): one home page with key figures and charts, plus 11 analyses, each with a chart, Excel / PDF / Print and Ward / Booth filters. Click a number to open those voters. The analyses: booth scorecard (A/B/C with a suggested action), voter sentiment, survey coverage, caste x booth (sensitive), scheme beneficiaries, worker coverage, household insights, place of origin, turnout vs voter profile, campaign reach, and team activity (sensitive).
* **Field survey on a phone** (Voters > Field survey): pick a booth, go house by house in roll order, record each member's mobile, caste / sub caste, political status, schemes, origin and membership no. "Same for the whole house" copies an answer to everyone; progress per booth is shown.
* **Bulk update**: tick voters in the list and set caste, sub caste, political status, social / organisational status or place of origin for all of them at once.
* **Workers**: booth presidents, agents, panna pramukhs and volunteers; assign voters by serial range, from the voter list, or with an automatic panna split (houses kept together); a leaderboard of survey progress. A worker's own login sees only their voters.
* **Poll day**: booth agents mark voters as voted by serial / EPIC no. or by tapping a serial grid on a phone; the war room shows live turnout per booth, votes per hour, and the supporters who have not voted yet (one click to message them).
* **Voter slips**: printable slips, 8 per A4 page, English + Hindi, for any voter list (no party name or symbol).
* **Grievances**: log voters' complaints, assign a worker, track Open > In progress > Resolved, with a booth-wise analysis.
* **Mobile app**: installable on Android and iPhone from the browser (page `/mobile-app`, works offline-aware with an app icon), plus a native Android / iOS app project in `../voter-mobile-app` (Capacitor) for the Play Store and App Store; Excel / PDF downloads open in the phone's share sheet.
* **Results**: booth-wise result entry, seat results with winner, margin and turnout.
* **SMS and WhatsApp**: templates, campaigns from any voter filter, opt-out list, sending window, rate limit and election silence period. Providers: Twilio, Meta WhatsApp Cloud API, or any HTTP SMS gateway. Dry-run mode until a provider is set.
* **Audit log** of logins and every change, with the user's IP address and browser / device; filter by IP.
* **Masters**: assemblies, booths, parties, elections, candidates.

## Requirements

* PHP **8.2+** with `pdo_mysql, mbstring, gd, zip, xml, dom, fileinfo, openssl, ctype, iconv, tokenizer`.
* MySQL 5.7+ / 8 or MariaDB 10.3+.
* That's all: no Node.js build, no queue worker, no Redis. SMS sending uses a cron job that runs every minute.

## Run it on your PC (demo data, no MySQL needed)

```bash
composer install                     # only if the vendor folder is missing
cp .env.demo .env                    # SQLite demo settings
touch database/demo.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder
php artisan serve                    # http://localhost:8000
```

Demo logins:

| User | Password | Role | Assembly |
|---|---|---|---|
| admin | admin@123 | Administrator | any |
| operator | operator@123 | Operator | any |
| viewer | viewer@123 | Viewer | any |
| jaipur | jaipur@123 | Operator | locked to 45 |
| agent | agent@1234 | Booth Agent (custom role) | locked to 52 |

The demo data is fictional: three assemblies with Hindi, Urdu and Bengali names, about 2,000 voters, and an election with booth results.

## Run it with MySQL

1. Create a utf8mb4 database and import `db/schema.sql` (phpMyAdmin → Import).
2. `cp .env.example .env`, then set `DB_*`, `APP_URL`, `ADMIN_USER` and `ADMIN_PASSWORD`.
3. Open the site. The first administrator is created from `ADMIN_USER` / `ADMIN_PASSWORD`.
4. Optional, for SMS / WhatsApp: add a cron job every minute running `php artisan messages:send`.

## Tests

```bash
php artisan test        # 56 tests on in-memory SQLite
# the same suite on MySQL:
DB_CONNECTION=mysql DB_DATABASE=voter_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```

## Where things are

| Path | What |
|---|---|
| `routes/web.php` | every URL, with the permission it needs |
| `app/Http/Controllers` | one controller per menu (Voters, Masters, Results, Reports, Messages, Admin) |
| `app/Services` | voter search and filters, reports, dashboard, results, Excel / PDF, messaging, login |
| `app/Models` | one model per table |
| `resources/views` | Blade templates (Bootstrap 5, RTL for Urdu) |
| `lang/messages/*.json` | all screen text, one file per language |
| `config/voters.php` | languages, login, user source, messaging settings (read from `.env`) |
| `db/schema.sql` | MySQL tables, empty (for your real data), the same as the Java version |
| `db/upgrade-2026-11-voter-profile-fields.sql` | adds the new voter columns to a database created before November 2026 |
| `db/voter_db_demo.sql` | full database **with demo data**, restore with phpMyAdmin → Import |
| `database/seeders/DemoSeeder.php` | demo data |
| `docs/SHARED-HOSTING.md` | hosting guide (cPanel and IIS) |

## Security notes

* Passwords are stored with bcrypt (`{bcrypt}$2y$…`, the same format as the Java version).
* Provider keys (Twilio, WhatsApp, SMS gateway) are read only from `.env`, never stored in the database.
* All SQL uses bound parameters. Forms have CSRF tokens. Logins are rate-limited (10 per minute) and every login is audited.
* The social-category report needs the separate *Sensitive reports* permission.
