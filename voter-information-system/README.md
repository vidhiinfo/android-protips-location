# Voter Information System

A multilingual voter, election-result and campaign-statistics application.

**Stack:** Java 21 · Spring Boot 3.5 · Spring Security · Spring Data JPA · MySQL 8 (utf8mb4) · Thymeleaf + Bootstrap 5 ·
Chart.js · Apache POI (Excel) · OpenPDF (PDF with Indic and Urdu text shaping)

## Features

| Menu | What it does |
|---|---|
| **Login** | Username, password and an **Assembly** drop-down. The chosen assembly becomes the working assembly for the session, so voters, exports, reports, results and the dashboard are limited to it. Users can be locked to one assembly. |
| **Dashboard** | Campaign statistics with charts: voters by age group and gender, voters per booth, **lead or trail per booth** against the strongest rival, party vote share, booth turnout, mobile reach, young (18-25) voters per booth, and houses by number of voters. Summary tiles show voters, booths, young and senior voters, mobile reach, households, the focus party's vote share and the number of booths where it leads. Every chart has a table view. |
| **Voters** | Voter list with quick and advanced search (English **or** local-language text), filters, sorting on any column, pagination, Excel/PDF export and printing. **House No** has its own column before Address; clicking it opens a popup with **every voter at that house no. and address**. Add, edit and delete voters, print a voter slip, and bulk-import from Excel. |
| **Masters** | Assemblies, Booths (polling stations), Parties, Elections and Candidates. A local-language name can be stored for each. |
| **Results** | Booth-wise vote entry (save and move to the next booth), a results overview for all seats, and a per-seat page with winner, margin, vote share, turnout and booth-wise votes. Excel/PDF/print. |
| **Reports** | 13 reports in four sections, each with **Excel and PDF export** and printing. **Voter analysis:** assembly-wise and booth-wise summaries, village/ward summary, age and gender distribution, social category (GEN/OBC/SC/ST, sensitive). **Outreach lists:** first-time voters (18-21), voters aged 85+ (home voting), large households (5+ voters), phone-bank list. **Election analysis:** results summary, party performance, booth priority (swing, weak and strong booths). **Data quality:** possible **duplicate voters** (same name + father/husband name within an assembly or a village/ward, ignoring case, spacing and punctuation). |
| **Messages** | **SMS and WhatsApp** to the voters selected in the voter list. Uses approved templates in any language with placeholders such as {name}, {nameLocal}, {boothNo}, {boothName} and {serialNo}. Shows a preview and the SMS part count for Unicode text before sending. Messages go out in the background, and you can watch campaign progress and see the status of each message. Also: an opt-out list, a test send, and a provider settings page. |
| **Admin** | **Users, roles and permissions in their own tables**: create roles, tick their permissions in a matrix, and give users one or more roles plus an optional assigned assembly. Users can also come from **your existing user/role tables** (see below). Also an audit log (logins, failed logins and every change) and a system page. |

### Multilingual
* **UI languages:** English, Hindi (हिन्दी), Bengali (বাংলা), Urdu (اردو), Marathi (मराठी), Gujarati (ગુજરાતી), Punjabi (ਪੰਜਾਬੀ),
  Tamil (தமிழ்) and Telugu (తెలుగు). Pick one from the drop-down in the navbar or on the login page. The choice is remembered in a cookie, and
  on a first visit the browser's language is used if it is supported.
* **Urdu** switches the whole layout to **right-to-left** (Bootstrap RTL). Excel sheets and PDF tables are mirrored too.
* **What is translated:** every label, menu, validation message, report heading, Excel/PDF column header and chart.
* **Database:** utf8mb4 throughout. Any field accepts any script. Name, father/husband name and address also have
  **local-language columns** (`*_local`) next to the English ones, as on Indian electoral rolls. Search covers both.
* **Normalisation:** text is stored as Unicode NFC, so the same Hindi or Urdu word typed on different keyboards still matches.
  Native digits (९८७६…, ৯৮৭৬…, ۹۸۷۶…) are accepted in mobile, pincode and vote fields.
* **PDF:** uses embedded Google Noto fonts (OFL licence, in `static/fonts`). Conjuncts, matras and joined Urdu letters
  render correctly. The same fonts are used in the browser.
* **Excel import:** recognises column headers and gender/relation values in **any** of the 9 languages, so a file exported in
  Tamil can be re-imported unchanged.

## 1. Set up MySQL
```bash
mysql -u root -p < db/schema.sql          # database, all tables (utf8mb4) and the user voter_app
```
Change the `voter_app` password in the script first. Upgrading: a version 1 database needs `db/upgrade-v1-to-v2.sql`.
For version 2 → 3 (roles tables and messaging): run `schema.sql`, start the app once, then run `db/upgrade-v2-to-v3.sql`.

## 2. Configure (environment variables or `application.properties`)
| Variable | Default |
|---|---|
| `DB_URL` | `jdbc:mysql://localhost:3306/voter_db?...&characterEncoding=UTF-8&connectionCollation=utf8mb4_unicode_ci` |
| `DB_USERNAME` / `DB_PASSWORD` | `voter_app` / `change_me` |
| `ADMIN_USER` / `ADMIN_PASSWORD` | `admin` / `admin@123`: the first administrator, created on first start |
| `DDL_AUTO` | `update`. Use `validate` in production after running `schema.sql` |

**Change the default passwords.** Create further users under **Admin > Users**.

## 3. Run
```bash
mvn spring-boot:run                                       # with MySQL
mvn clean package && java -jar target/voter-information-system-1.0.0.jar
```
Open http://localhost:8080.

**Try it without MySQL:** the demo profile uses an in-memory database with about 2,000 sample voters in Hindi, Urdu and Bengali
regions, households, masters, an election with booth results, and one user per role.
```bash
mvn spring-boot:run -Dspring-boot.run.profiles=demo
```
| Demo user | Password | Role |
|---|---|---|
| admin | admin@123 | Administrator |
| operator | operator@123 | Operator |
| viewer | viewer@123 | Viewer |
| jaipur | jaipur@123 | Operator, assembly 45 only |
| agent | agent@1234 | Booth Agent (custom role), assembly 52 only |

## Users, roles and permissions
Tables: `app_users` → `user_roles` → `roles` → `role_permissions`. A user's permissions are the union of the permissions of all of its roles.
Every menu, button, page and URL checks a permission:

| Group | Permissions |
|---|---|
| General | `DASHBOARD_VIEW` |
| Voters | `VOTER_VIEW`, `VOTER_EDIT`, `VOTER_DELETE`, `VOTER_IMPORT`, `VOTER_EXPORT` |
| Masters | `MASTER_VIEW`, `MASTER_EDIT` |
| Results | `RESULT_VIEW`, `RESULT_ENTRY` |
| Reports | `REPORT_VIEW`, `REPORT_SENSITIVE` (social category report) |
| Messages | `MESSAGE_SEND`, `MESSAGE_ADMIN` (templates, opt-outs, settings) |
| Administration | `USER_ADMIN` (users and roles), `AUDIT_VIEW`, `SYSTEM_VIEW` |

The system roles **ADMIN** (all permissions), **OPERATOR** and **VIEWER** are created on first start. Add your own roles under
**Admin > Roles & Permissions**. The demo has a *Booth Agent* role (view voters and enter results only).
Safeguards: you cannot remove your own user-admin rights, the ADMIN role always keeps `USER_ADMIN`, and system roles cannot be deleted.

### Using your existing user and role tables
Set `USER_SOURCE=external` and give two SQL queries. They can use any table and column names.
```properties
app.security.user-source=external
# username, password, enabled [, assembly_no] [, full_name]
app.security.external.users-query=select login_id, pwd_hash, is_active as enabled, ac_no as assembly_no, display_name as full_name from staff where login_id = ?
# one role name per row
app.security.external.roles-query=select g.group_name from staff_groups sg join staff s on s.staff_id = sg.staff_id join access_groups g on g.group_id = sg.group_id where s.login_id = ?
# hashes without a {id} prefix: bcrypt | pbkdf2 | scrypt | argon2 | SHA-256 | MD5 | noop
app.security.external.password-encoding=bcrypt
# your role names -> application role codes (or create roles with the same codes under Admin > Roles)
app.security.external.role-map.SUPERVISOR=ADMIN
app.security.external.role-map.FIELD_STAFF=VIEWER
# optional, if the user tables are in another database
app.security.external.url=jdbc:mysql://host:3306/hr_db
```
Users are then managed in your system. This application decides which **permissions** each role name gives
(Admin > Roles & Permissions). `ExternalUsersTest` shows a complete example with differently named tables.

## SMS and WhatsApp
**Dry-run by default:** messages are recorded and marked as sent, but nothing leaves the server until you configure a provider.
Set the credentials with environment variables. They are never stored in the database.

| Provider | Settings |
|---|---|
| Twilio (SMS and WhatsApp) | `SMS_PROVIDER=twilio` and/or `WHATSAPP_PROVIDER=twilio`, `TWILIO_ACCOUNT_SID`, `TWILIO_AUTH_TOKEN`, `TWILIO_SMS_FROM` (number or Messaging Service SID), `TWILIO_WHATSAPP_FROM` |
| WhatsApp Cloud API (Meta) | `WHATSAPP_PROVIDER=meta`, `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN` |
| Indian DLT SMS gateway (MSG91, Textlocal, Fast2SMS ...) | `SMS_PROVIDER=http`, `SMS_HTTP_URL`, `SMS_HTTP_METHOD`, `SMS_HTTP_BODY`, `SMS_HTTP_AUTH_HEADER`, `SMS_SENDER_ID`, `SMS_DLT_ENTITY_ID` |

For the HTTP gateway, the URL and body can use `{mobile}` (10 digits), `{e164}`, `{message}`, `{templateId}`, `{senderId}` and `{entityId}`. Example (GET):
`SMS_HTTP_URL=https://gateway.example.com/send?apikey=XXXX&sender={senderId}&numbers={mobile}&message={message}&dlt_template_id={templateId}`

Built-in rules:
* **Templates only:** SMS in India must match a DLT-registered template, and WhatsApp needs a Meta-approved template. Store the provider template id on the template and list the placeholders in variable order.
* **Recipients:** each number gets one message per campaign, so a family sharing a phone gets it once. The opt-out list is honoured, and any user who can send messages can add a number to it from the voter page.
* **Sending window:** messages go out only between 09:00 and 21:00 (TRAI rule for promotional SMS), rate-limited, with retries on temporary errors.
* **Silence period:** sending is blocked from 48 hours before the current election's poll day until polling ends.
* **Restarts:** a message that was being sent when the server stopped is marked failed, not resent, so nobody receives a duplicate.
* **Audit:** every campaign, cancellation, test message and opt-out is recorded in the audit log.

Make sure every number was collected with consent for contact, and follow the Model Code of Conduct.

## Login page and branding
The login page is a split screen: a banner with title, tagline and highlights (translated), plus the sign-in form.
To use your own banner, put an image under `src/main/resources/static/images/` and set
`BRAND_BANNER=/images/your-banner.png`. `BRAND_ORGANISATION` (e.g. "Jan Vikas Party - Jaipur North") and `BRAND_LOGO` are optional.

## Adding another language
1. Copy `src/main/resources/messages.properties` to `messages_xx.properties` (UTF-8) and translate the values.
2. Add the language to `config/Languages.java` (code, native name, whether it is right-to-left).
3. If it uses a new script, add its Noto TTF files to `static/fonts`, map the script in `export/PdfFonts.java`
   and add it to `static/css/fonts.css`.

Native speakers should review the translations before go-live, especially election terms.

## Tests
`mvn test` runs 30 tests:
* **Login and access:** assembly login and session scoping, permissions per role, custom roles, editing role permissions, users read from external tables.
* **Languages:** language switching and RTL, local-language search, native digits, Hindi/Urdu/Bengali Excel, PDF and print, import in another language.
* **Voters and data:** the household popup, masters, the audit log, result entry.
* **Reports and dashboard:** every report with an Excel and PDF export check, duplicate and booth-priority reports, the dashboard data.
* **Messaging:** preview, queue and dry-run send, opt-outs, the silence period, template checks.
* **Providers:** the exact HTTP requests to Twilio, Meta and an HTTP gateway (against a local stub server), and SMS part counting.

## Project layout
```
src/main/java/com/vidhi/voters
├── config/     Security (login + assembly), languages, locale switching, WorkContext (session assembly), demo data
├── admin/      Users (DB, BCrypt), assembly-aware login, audit log, admin and account screens
├── model/ repo/ service/ web/   Voters: entity, search specifications, household lookup, controller
├── master/     Assemblies, booths, parties, elections, candidates
├── result/     Booth results, seat and election calculations, result screens
├── report/     Reports and the generic Excel/PDF table exporter
├── dashboard/  Campaign dashboard data (JSON for the charts)
└── export/     Voter Excel/PDF export, multi-script PDF fonts, multilingual Excel import
src/main/resources
├── messages*.properties   UI texts in 9 languages
├── templates/             Thymeleaf pages
└── static/                css, js (app.js, dashboard.js), fonts (Noto)
db/schema.sql, db/upgrade-v1-to-v2.sql
```

## Production notes
* Run behind HTTPS (nginx or Apache reverse proxy) and take regular `mysqldump --default-character-set=utf8mb4` backups.
* All third-party assets (Bootstrap, icons, Chart.js, fonts) are served by the app itself. No CDN is needed, and the strict
  Content-Security-Policy is kept.
