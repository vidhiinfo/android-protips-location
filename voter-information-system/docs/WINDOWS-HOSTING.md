# Hosting on Windows Server

This guide installs the Voter Information System on **Windows Server 2019 / 2022 / 2025**. The end result:

```
Users ──HTTPS 443──> IIS (reverse proxy, SSL certificate) ──http 127.0.0.1:8080──> Voter Information System (Windows service)
                                                                                         │
                                                                                         └──> MySQL 8 (localhost:3306, utf8mb4)
```

* The application runs as a **Windows service**. It starts at boot and restarts automatically if it stops.
* **IIS** serves HTTPS on port 443 and forwards requests to the application. The application itself only listens on `127.0.0.1`.
* **MySQL** stores all data in `utf8mb4`, so every Indian language is stored correctly.

Time needed: about 1 hour. Everything below is done on the server, signed in as an administrator.

---

## 0. What you need

| Item | Notes |
|---|---|
| Windows Server 2019 or newer, 64-bit | 4 GB RAM minimum (8 GB recommended), 20 GB free disk |
| A domain name, e.g. `voters.example.org` | Its DNS A-record must point to the server's public IP (needed for the HTTPS certificate) |
| The project zip | `voter-information-system/` with the full source code and `dist/voter-information-system-1.0.0.jar` (ready to run) |

Unzip it to `C:\Install\` so that you have `C:\Install\voter-information-system\`.

---

## 1. Install Java 21

1. Download **Eclipse Temurin JDK 21 (LTS), Windows x64, `.msi`** from https://adoptium.net
2. Run the installer. On the *Custom Setup* page, set these to *"Will be installed on local hard drive"*:
   **Add to PATH**, **Set JAVA_HOME variable**.
3. Open a **new** PowerShell and check:
   ```powershell
   java -version        # must show version "21..."
   ```

---

## 2. Install MySQL 8 and create the database

1. Download **MySQL Installer for Windows** from https://dev.mysql.com/downloads/installer/ and choose **Server only**.
2. During configuration:
   * Config type: *Server Computer*, port **3306**
   * Authentication: *Use Strong Password Encryption*
   * Set a strong **root** password and write it down.
   * Windows Service name: **MySQL80** (the default), started at system startup.
3. Make MySQL use full Unicode by default. Open `C:\ProgramData\MySQL\MySQL Server 8.0\my.ini` in Notepad (run Notepad as administrator).
   Under `[mysqld]`, make sure these lines are present:
   ```ini
   [mysqld]
   character-set-server=utf8mb4
   collation-server=utf8mb4_unicode_ci
   bind-address=127.0.0.1
   ```
   Then restart MySQL: `Restart-Service MySQL80`
4. Create the database, tables and application user:
   1. Open `voter-information-system\db\schema.sql` in Notepad.
   2. Replace `change_me` with a strong password for the **voter_app** user, then save.
   3. Run:
   ```powershell
   cd C:\Install\voter-information-system\db
   & "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 -e "source schema.sql"
   ```
   It asks for the root password. Check that it worked:
   ```powershell
   & "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p -e "SELECT default_character_set_name FROM information_schema.schemata WHERE schema_name='voter_db'; SHOW TABLES FROM voter_db;"
   ```
   The first result must be `utf8mb4`, and the table list must include `voters`, `roles`, `app_users`, `message_outbox` and the others.

> **Upgrading an existing installation?** Back up the database first, then follow *Upgrading* in the README
> (`upgrade-v1-to-v2.sql` and `upgrade-v2-to-v3.sql`).

---

## 3. Install the application as a Windows service

1. Open **PowerShell as administrator** and run:
   ```powershell
   cd C:\Install\voter-information-system\deploy\windows
   powershell -ExecutionPolicy Bypass -File .\install.ps1
   ```
   The script:
   * checks Java 21
   * creates `C:\VoterSystem\` with `config\`, `logs\` and `backups\` folders
   * copies the jar
   * creates `C:\VoterSystem\config\application.properties`, which only Administrators and SYSTEM can read
   * downloads **WinSW** (the service wrapper; needs internet) and registers the service **"Voter Information System"**. It starts automatically, depends on MySQL80 and restarts if it fails.

   *No internet on the server?* Download `WinSW-x64.exe` (v2.12.0) from https://github.com/winsw/winsw/releases on another PC,
   save it as `C:\VoterSystem\VoterInformationSystem.exe`, and run the script again.

2. **Edit the settings.** Open `C:\VoterSystem\config\application.properties` in Notepad (as administrator):
   * `spring.datasource.password=`: the **voter_app** password from step 2
   * `app.security.admin.password=`: the password for the first **admin** login (at least 8 characters)
   * optional: `app.branding.organisation=` (shown on the login banner), and the SMS / WhatsApp provider settings

3. **Start it:**
   ```powershell
   Start-Service VoterInformationSystem
   Get-Service VoterInformationSystem          # Status: Running
   ```
   After about 20 seconds, open **http://localhost:8080** in a browser *on the server*. You should see the login page.
   Sign in as `admin` with the password you set, then **Admin → Users** to create the other users and **Admin → Roles & Permissions**.

   If it doesn't start, look in `C:\VoterSystem\logs\voter-system.log` (application) and
   `C:\VoterSystem\logs\VoterInformationSystem.err.log` (service wrapper). See *Troubleshooting* below.

---

## 4. Publish it with IIS and HTTPS

### 4.1 Install IIS and the proxy modules
```powershell
Install-WindowsFeature -Name Web-Server, Web-WebSockets -IncludeManagementTools
```
Then download and install, in this order:
1. **URL Rewrite 2.1**: https://www.iis.net/downloads/microsoft/url-rewrite
2. **Application Request Routing (ARR) 3.0**: https://www.iis.net/downloads/microsoft/application-request-routing

Turn on the proxy in ARR:
1. Open **IIS Manager** and click the *server* node.
2. Open **Application Request Routing Cache**, then *Server Proxy Settings…* on the right.
3. Tick **Enable proxy** and untick *Reverse rewrite host in response headers*.
4. Click **Apply**.

Allow the forwarded headers:
1. Server node → **URL Rewrite** → *View Server Variables…* on the right.
2. **Add** `HTTP_X_FORWARDED_PROTO`, then **Add** `HTTP_X_FORWARDED_HOST`.

### 4.2 Create the site
1. Create the folder `C:\inetpub\voter` and copy `deploy\windows\web.config` into it.
2. IIS Manager → *Sites* → **Add Website…**
   * Site name: `VoterSystem`, physical path: `C:\inetpub\voter`
   * Binding: **http**, port **80**, host name `voters.example.org`
3. If the *Default Web Site* also uses port 80 with no host name, stop it or remove its binding.

### 4.3 Free HTTPS certificate (Let's Encrypt)
1. Download **win-acme** (`win-acme.v2.x.x.x64.trimmed.zip`) from https://www.win-acme.com and unzip it to `C:\Tools\win-acme`.
2. Run `C:\Tools\win-acme\wacs.exe` as administrator:
   1. Choose **N** (create certificate, default settings).
   2. Pick the `VoterSystem` site and the host `voters.example.org`.
   3. Accept the terms.

   win-acme adds the **https/443** binding and a scheduled task that renews the certificate automatically.
   (A certificate from your own CA or a commercial one also works: import it and add an https binding in IIS.)

### 4.4 Firewall
Open only HTTP and HTTPS to the outside. Port 8080 and MySQL's port 3306 stay closed:
```powershell
New-NetFirewallRule -DisplayName "HTTP 80"   -Direction Inbound -Protocol TCP -LocalPort 80  -Action Allow
New-NetFirewallRule -DisplayName "HTTPS 443" -Direction Inbound -Protocol TCP -LocalPort 443 -Action Allow
```
Now open **https://voters.example.org** from any PC or phone. `http://` redirects to `https://` automatically.

> **Intranet only, no domain or IIS?** Remove `server.address=127.0.0.1` and `server.servlet.session.cookie.secure=true`
> from the config, run `install.ps1 -OpenFirewall`, and use `http://SERVER-NAME:8080`. Only do this on a trusted internal network.

---

## 5. Daily backups

1. Create `C:\VoterSystem\config\mysql-backup.cnf` (Notepad, as administrator):
   ```ini
   [client]
   user=root
   password=YOUR_ROOT_PASSWORD
   ```
2. Test it:
   ```powershell
   powershell -ExecutionPolicy Bypass -File C:\VoterSystem\backup-mysql.ps1
   ```
   A file `voter_db-YYYY-MM-DD_HHMM.sql.zip` appears in `C:\VoterSystem\backups`. Backups older than 30 days are deleted.
3. Schedule it every night at 01:30:
   ```powershell
   $action  = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-ExecutionPolicy Bypass -File C:\VoterSystem\backup-mysql.ps1"
   $trigger = New-ScheduledTaskTrigger -Daily -At 1:30am
   Register-ScheduledTask -TaskName "VoterSystem MySQL backup" -Action $action -Trigger $trigger -User "SYSTEM" -RunLevel Highest
   ```
4. Copy the backups folder to another machine or to cloud storage regularly. A backup kept on the same disk does not protect against disk failure.

**Restore** (into an empty `voter_db`):
```powershell
Expand-Archive C:\VoterSystem\backups\voter_db-2026-11-14_0130.sql.zip -DestinationPath C:\Temp
& "C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe" -u root -p --default-character-set=utf8mb4 voter_db -e "source C:/Temp/voter_db-2026-11-14_0130.sql"
```

---

## 6. Updating to a new version

```powershell
# 1. back up first
powershell -ExecutionPolicy Bypass -File C:\VoterSystem\backup-mysql.ps1
# 2. run any upgrade-*.sql scripts mentioned in the release notes
# 3. install the new jar (keeps your config)
cd C:\Install\<new version>\voter-information-system\deploy\windows
powershell -ExecutionPolicy Bypass -File .\install.ps1
Start-Service VoterInformationSystem
```

### Building the jar yourself (optional)
The zip already contains a built jar in `dist\` (the installer uses it, or `target\` if you built one). To build from source on Windows (needs Java 21 and internet; Maven is downloaded automatically):
```powershell
cd C:\Install\voter-information-system
.\mvnw.cmd clean package            # runs the 30 tests, then builds target\voter-information-system-1.0.0.jar
.\mvnw.cmd clean package -DskipTests   # faster, without tests
```

---

## 7. Everyday commands

| Task | Command |
|---|---|
| Start / stop / restart | `Start-Service VoterInformationSystem` / `Stop-Service …` / `Restart-Service …` |
| Status | `Get-Service VoterInformationSystem` |
| Follow the log | `Get-Content C:\VoterSystem\logs\voter-system.log -Wait -Tail 50` |
| Uninstall the service | `C:\VoterSystem\VoterInformationSystem.exe uninstall` |
| Try without MySQL (demo data) | copy the jar and `deploy\windows\run-demo.bat` into one folder, double-click the .bat, open http://localhost:8080 (admin / admin@123) |

---

## 8. Security checklist

- [ ] Strong, different passwords for MySQL root, `voter_app` and the first `admin` user. Change the admin password after the first login (*user menu → Change Password*).
- [ ] `C:\VoterSystem\config` is readable only by Administrators and SYSTEM (`install.ps1` sets this).
- [ ] Only ports 80 and 443 are open. MySQL listens on `127.0.0.1` only.
- [ ] HTTPS works and the certificate renews automatically (win-acme scheduled task).
- [ ] Windows Update is on, and backups run nightly and are copied off the server.
- [ ] Users have only the roles they need (*Admin → Roles & Permissions*). Users who leave are disabled, not shared.
- [ ] Voter data is personal data. Keep access on a need-to-know basis and review the audit log (*Admin → Audit Log*) from time to time.

---

## 9. Troubleshooting

| Symptom | Fix |
|---|---|
| Service stops right after starting | Open `C:\VoterSystem\logs\voter-system.log`. Most often the database password is wrong or MySQL is not running (`Get-Service MySQL80`). |
| `Schema-validation: missing table` in the log | `schema.sql` was not run against `voter_db`, or an upgrade script is missing (step 2). |
| `Access denied for user 'voter_app'` | The password in `application.properties` doesn't match the one in `schema.sql`. Set it again in MySQL: `ALTER USER 'voter_app'@'localhost' IDENTIFIED BY 'new-password';` |
| Hindi / Urdu text shows as `????` | The database or table is not utf8mb4. Check the `[mysqld]` settings in step 2, then check *Admin → System Information*, which shows the character set. |
| IIS shows **502.3 Bad Gateway** | The application is not running or not on port 8080. Check that `http://localhost:8080` works on the server. |
| After login the browser jumps to `http://...:8080` | The server variables `HTTP_X_FORWARDED_PROTO` / `HOST` are not allowed (step 4.1), or *Reverse rewrite host* is still ticked. |
| Login works on http but not on https, or the reverse | `server.servlet.session.cookie.secure=true` requires HTTPS. Remove it only for an intranet-only http setup. |
| Port 8080 is already in use | Change `server.port` in the config and the port in `web.config`, then restart the service. |
| Excel upload fails for big files | The limit is 20 MB, set in `web.config` and in the application. |
