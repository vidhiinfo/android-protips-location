# OBC Office — National President's Office Management System

A web application (Laravel 13, PHP 8.3+) for the National President's office. It covers the organisation database of
office bearers and party workers (national → state → district → mandal), with photos, plus the President's daily
office work. It also provides a REST API, ready for the Android and iOS apps (phase 2).

```
obc-office/
└── web/          Laravel 13 application (web portal + REST API for mobile)
```

## Features

| Area | What it does |
|---|---|
| **Dashboard** | Bearers, workers, pending approvals and data-quality counts. Shows a state-wise table (district-wise for state admins) with posts filled against sanctioned seats, missing photos and last update. Also shows today's schedule, open and overdue petitions, birthdays and your own tasks. |
| **Bearers & Workers** | Full profiles with a photo (camera upload on phones, auto-resized, stored privately), designation, level, area, tenure, contact details, social handles and recorded DPDP consent. Includes filters, a profile-completeness score and duplicate-mobile detection. |
| **Approval workflow** | Records added by state or district admins start as *Proposed* until the national office approves them (configurable). |
| **Photo Directory** | Printable, PDF-ready photo directory for any state, district or level, ordered by post seniority. |
| **Import / Export** | CSV import with the same validation as the form (Excel → *Save as CSV UTF-8*), using a downloadable template, and a per-row error report. CSV export of any filtered list. Every export is audited. |
| **Programmes & Reports** | The national office announces a campaign; states and districts submit event reports with attendance and photos. Includes a state-coverage tracker. |
| **Circulars** | Directives to all states or one state, with attachments and read receipts. |
| **Biodata Register** | Records every biodata the office receives: date, received from (state, district or person), received via (by hand, post, email, WhatsApp…), category, the person's details and photo, background, remarks and the scanned or PDF biodata files (several per entry, viewable in the browser). Includes search and filters, *Also received earlier* duplicate detection, a status, a printable biodata sheet and CSV export. |
| **Schedule & Tours** | The President's diary: meetings, tours, events and calls, with local contacts and briefing notes. |
| **Visitors** | Front-desk visitor register with status (waiting, met, rescheduled). |
| **Petitions** | Auto tracking number (`PET-2026-00001`), category, forwarding, due dates, a follow-up timeline, a printable acknowledgement slip and a WhatsApp status message. |
| **Tasks** | Assign tasks to staff or state presidents. Assignees update their status from the web or the app. |
| **VIP Contacts** | MPs, MLAs, officials and journalists, with birthday, anniversary and last-met date. |
| **Letters Register** | Inward and outward correspondence register. |
| **Greetings** | Today's and upcoming birthdays and anniversaries, each with a one-click pre-filled WhatsApp message. |
| **Users** | Super Admin, National Staff, State Admin and District Admin accounts, each tied to a state or district. |
| **Audit Log** | Who viewed, created, changed, exported or deleted what, and from which IP address. |

## Everything is configurable (no developer needed)

**Settings** (Super Admin):

- **General & branding:** organisation name, short name, President's name, logo, theme colour (web and app), footer,
  birthday and anniversary message templates (`{name}`, `{designation}`, `{signature}`), petition number prefix and
  default due days, date formats, rows per page and photo size.
- **Member rules:** which fields are mandatory, consent required or not, whether state entries need national approval,
  and duplicate-mobile warning on or off.
- **Dropdown lists:** every dropdown in the app (levels, member types and statuses, professions, education, petition
  categories and statuses, priorities, schedule types, visitor and task statuses, contact categories, letter statuses).
  You can rename, recolour, reorder, deactivate or add options. System values are locked against deletion, but you can
  still rename them.
- **Custom fields** for both *Bearers & Workers* and the *Biodata Register*: add fields such as Voter ID, Assembly
  constituency or Blood group. Types are text, long text, number, date, dropdown and yes/no. You can mark a field
  required and show it in the list. Custom fields appear on the form, profile, print, CSV and the API.
- **Biodata register:** mandatory fields (e.g. require the scanned file, mobile or date of birth), reference-number
  prefix (`BIO-2026-00001`), maximum file size, and the categories, "received via" options and statuses (Dropdown lists).
- **Roles & permissions:** a matrix of each role × module with `None` / `View` / `Manage`, plus switches to turn whole
  modules on or off.

**States, Districts & Posts** (Masters):

- States and UTs. Districts can be pasted one per line.
- Designations per level, with seniority (for ordering) and sanctioned seats (for vacancy tracking).

## Quick start (local)

Requirements: PHP 8.3+ with `pdo_sqlite` (or `pdo_mysql`), `gd`, `mbstring` and `intl`; Composer; Node 20+.

```bash
cd obc-office/web
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
ADMIN_PASSWORD='ChooseAStrongOne1' SEED_DEMO=true php artisan db:seed
php artisan storage:link
php artisan serve
```

Open http://127.0.0.1:8000 and sign in as `admin`. With `SEED_DEMO=true` you also get the demo users `staff`,
`up_admin`, `lucknow_admin` and `rj_admin` (password `Demo@12345`). Use them to see how each role and jurisdiction
works. **Don't seed demo data in production.**

Run the tests with `php artisan test`.

## Production deployment

- Use MySQL 8 or PostgreSQL. Set `DB_CONNECTION` and related variables in `.env`, then run
  `php artisan migrate --force && php artisan db:seed --force`.
- Host in India (e.g. AWS Mumbai, Azure Central India or a MeitY-empanelled cloud) for data localisation.
- Set `APP_ENV=production`, `APP_DEBUG=false`, HTTPS only, `SESSION_SECURE_COOKIE=true`.
- Run `php artisan config:cache route:cache view:cache` on every deploy.
- Back up the database and `storage/app/private` (member photos and attachments) daily.
- Photos and attachments are on the private disk and are only served to signed-in users with jurisdiction. Switch the
  `local` disk to S3 in `config/filesystems.php` when scaling.

## Security & data protection

- Role-based permissions plus **hard jurisdiction scoping**: a state admin can never read or write another state's data,
  either on the web or through the API.
- Recorded consent with timestamp (DPDP Act 2023), soft-deleted member records and a full audit log.
- Login throttling, disabled accounts are locked out immediately, and a password reset revokes mobile tokens.
- Recommended next steps: OTP / 2FA for national users, and an IP allow-list for the admin panel.

## REST API (for the Android / iOS apps)

Base URL: `/api/v1`. Get a token, then send `Authorization: Bearer <token>` and `Accept: application/json`.
Permissions and jurisdiction are identical to the web app.

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/login` `{username, password, device_name}` | Returns `token` and `user`, including the effective `permissions` per module and `branding` (name, colour, logo), so the app builds its menu and theme dynamically |
| GET | `/me` · POST `/logout` | Profile and permissions / revoke token |
| GET | `/meta` | States, districts, designations, all dropdown lists, custom fields and required fields (cache these for offline forms) |
| GET | `/dashboard` | Totals, state/district breakdown, today's office summary, my tasks |
| GET | `/members?q=&state_id=&district_id=&level=&type=&missing=photo&updated_since=` | Paginated, scoped list (`updated_since` for incremental offline sync) |
| GET | `/members/{id}` · `/members/{id}/photo` | Profile / photo |
| POST | `/members` (multipart, optional `photo`) | Add a member from the phone camera |
| POST | `/members/{id}` (multipart) | Partial update (send only changed fields) |
| GET | `/biodata?q=&state_id=&category=&from=&to=` · `/biodata/{id}` | Biodata register (with file links) |
| POST | `/biodata` (multipart `photo`, `documents[]`) · `/biodata/{id}/documents` | Record a biodata by photographing its pages, or attach more files |
| GET | `/schedule?from=&to=` | President's schedule |
| GET | `/greetings?days=7` | Birthdays and anniversaries with ready-made WhatsApp links |
| GET | `/circulars` · POST `/circulars/{id}/read` | Circulars with read status / mark as read |
| GET | `/tasks?mine=1` · PATCH `/tasks/{id}` `{status}` | Tasks / update status |
| GET | `/petitions` · `/petitions/{id}` · POST `/petitions/{id}/updates` | Petitions and follow-ups |
| GET | `/programs` · POST `/programs/{id}/reports` (multipart `photos[]`) | Programme reports from the field |

## Roadmap: phase 2 (mobile apps)

The API above is complete for the first app release. The plan is one **Flutter** codebase producing both the Android
and iOS apps:

1. **President's app:** dashboard, today's schedule, greetings with one tap to WhatsApp, approvals, member search with
   photo, petitions and task assignment.
2. **State / district app:** add and update bearers using the camera, submit programme reports with geo-tagged photos,
   receive circulars through push notifications, and an offline mode (cached `/meta` plus queued uploads).

Planned additions: push notifications (FCM), WhatsApp Business API for bulk greetings, OTP login, Hindi UI, and an
India map on the dashboard.
