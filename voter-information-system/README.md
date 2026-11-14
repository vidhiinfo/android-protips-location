# Voter Information System

Java 21 · Spring Boot 3.5 · Spring Security · Spring Data JPA · MySQL 8 · Thymeleaf + Bootstrap 5 · Apache POI (Excel) · OpenPDF (PDF)

## Features
- **Voter records**: EPIC/Voter ID no., name, father/husband name (with S/O, D/O, W/O), gender, age,
  assembly no. and name, booth/part no., serial no., polling station, house no., address, village/ward,
  district, pincode, mobile, alternate mobile, email, category, remarks.
- **Search**: quick search across name, father/husband name, EPIC, mobile, house no., address and booth name.
  Words are combined, so `ramesh 98765` finds Ramesh whose number contains 98765.
- **Filters**: assembly, booth, gender, age range, district, village/ward, house no., with or without mobile, plus per-field search.
- **Sorting**: click any column heading to sort; click again to reverse.
- **Pagination**: 10 to 500 rows per page.
- **Export**: Excel (.xlsx, with frozen header and auto-filter) and PDF (A4 landscape, header repeated on every page).
  Exports and printing use the **current filters and sort order**.
- **Print**: a print-ready voter list (A4 landscape), plus a print slip for a single voter.
- **Bulk import** from Excel. Voters are matched on EPIC no.: existing ones are updated and new ones added. A template is included.
- **Security**: login required. Two roles: `ADMIN` can add, edit, delete and import; `VIEWER` can search, view, export and print.
  CSRF protection, BCrypt passwords, parameterised queries, a whitelist of sortable columns, a strict Content Security Policy,
  and a 30-minute session timeout.
- **Validation**: unique EPIC no., 10-digit Indian mobile number, 6-digit pincode, age 18 or over.

## 1. Set up MySQL
```bash
mysql -u root -p < db/schema.sql      # creates voter_db, the voters table and the user voter_app
```
Edit the password in `db/schema.sql` before you run it.

## 2. Configure
Set environment variables (or edit `src/main/resources/application.properties`):

| Variable | Default |
|---|---|
| `DB_URL` | `jdbc:mysql://localhost:3306/voter_db?...` |
| `DB_USERNAME` / `DB_PASSWORD` | `voter_app` / `change_me` |
| `ADMIN_USER` / `ADMIN_PASSWORD` | `admin` / `admin@123` |
| `VIEWER_USER` / `VIEWER_PASSWORD` | `viewer` / `viewer@123` |
| `DDL_AUTO` | `update` (set to `validate` in production after running schema.sql) |

**Change the default passwords.** You can also store them already hashed, for example `ADMIN_PASSWORD={bcrypt}$2a$10$...`.

## 3. Run
```bash
mvn spring-boot:run                       # uses MySQL
# or
mvn clean package && java -jar target/voter-information-system-1.0.0.jar
```
Open http://localhost:8080 and log in.

**Try it without MySQL** (in-memory database with 300 sample voters):
```bash
mvn spring-boot:run -Dspring-boot.run.profiles=demo
```

## Tests
`mvn test` runs integration tests for login and roles, search, filter and sort, Excel/PDF export, printing, validation and import.

## Project layout
```
src/main/java/com/vidhi/voters
├── config/   SecurityConfig (login, roles, CSP), DemoDataSeeder
├── model/    Voter entity, Gender, RelationType
├── repo/     VoterRepository (JPA + Specifications)
├── service/  VoterFilter (search/sort/page params), VoterSpecifications, VoterService
├── export/   ExcelExporter, PdfExporter, ExcelImporter, Columns (shared column list)
└── web/      VoterController, AuthController
src/main/resources/templates  login, voters/list, form, view, print, import
db/schema.sql                 MySQL DDL and the application DB user
```

## Notes
- For production, run it behind HTTPS (nginx or Apache reverse proxy) and take regular `mysqldump` backups.
- The PDF uses the built-in Helvetica font, which cannot show Hindi or other Indian scripts.
  To export names in Devanagari, register a Unicode font such as Noto Sans Devanagari in `PdfExporter`.
  Excel export and the print view already handle Hindi.
- To add more users, replace the in-memory users in `SecurityConfig` with a `users` table and a JDBC `UserDetailsService`.
