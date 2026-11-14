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
| **Reports** | Assembly-wise and booth-wise voter summaries, village/ward summary, age and gender distribution, election results summary and party performance. Each report exports to Excel or PDF and prints. |
| **Admin** | Users with ADMIN, OPERATOR or VIEWER roles, an optional assigned assembly, enable/disable and password reset. Also an audit log (logins, failed logins and every change) and a system page that shows the database character set. |

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
Change the `voter_app` password in the script first. To upgrade a version 1 database, run `db/upgrade-v1-to-v2.sql`.

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

## Roles
* **ADMIN:** everything, including users, the audit log and editing masters.
* **OPERATOR:** add, edit, delete and import voters; enter booth results.
* **VIEWER:** search, view, dashboard, reports, results, export and print.

## Adding another language
1. Copy `src/main/resources/messages.properties` to `messages_xx.properties` (UTF-8) and translate the values.
2. Add the language to `config/Languages.java` (code, native name, whether it is right-to-left).
3. If it uses a new script, add its Noto TTF files to `static/fonts`, map the script in `export/PdfFonts.java`
   and add it to `static/css/fonts.css`.

Native speakers should review the translations before go-live, especially election terms.

## Tests
`mvn test` runs 15 integration tests. They cover assembly login and session scoping, language switching and RTL,
local-language search, native digits, the household popup, roles, Hindi/Urdu/Bengali Excel, PDF and print, import in another language,
masters, user admin, audit, result entry, every report and the dashboard data.

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
