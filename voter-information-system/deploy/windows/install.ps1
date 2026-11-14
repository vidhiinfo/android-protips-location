<#
.SYNOPSIS
  Installs the Voter Information System as a Windows service (run in an elevated PowerShell).

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File .\install.ps1
  powershell -ExecutionPolicy Bypass -File .\install.ps1 -InstallDir D:\VoterSystem -OpenFirewall
#>
param(
    [string]$InstallDir = "C:\VoterSystem",
    [string]$Jar = "",                # path to voter-information-system-*.jar (default: ..\..\dist or ..\..\target)
    [string]$MySqlService = "MySQL80",
    [switch]$OpenFirewall             # only if users connect straight to port 8080 (not needed with IIS)
)
$ErrorActionPreference = "Stop"

if (-not ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw "Please run this script in PowerShell opened with 'Run as administrator'."
}
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$project = Resolve-Path (Join-Path $here "..\..")

# 1. Java 21
$java = Get-Command java -ErrorAction SilentlyContinue
if (-not $java) { throw "Java not found. Install Eclipse Temurin JDK 21 (x64 MSI, tick 'Add to PATH' and 'Set JAVA_HOME') and open a new PowerShell." }
$ver = (& java -version 2>&1 | Select-Object -First 1)
Write-Host "Java: $ver"
if ($ver -notmatch '"(2[1-9]|[3-9]\d)') { throw "Java 21 or newer is required." }

# 2. Find the jar
if (-not $Jar) {
    $Jar = Get-ChildItem -Path (Join-Path $project "dist"), (Join-Path $project "target") -Filter "voter-information-system-*.jar" -ErrorAction SilentlyContinue |
           Where-Object { $_.Name -notlike "*original*" } | Select-Object -First 1 -ExpandProperty FullName
}
if (-not $Jar -or -not (Test-Path $Jar)) { throw "Jar not found. Build it with 'mvnw.cmd clean package' in the project folder, or pass -Jar <path>." }
Write-Host "Jar:  $Jar"

# 3. Folders and files
New-Item -ItemType Directory -Force -Path $InstallDir, "$InstallDir\config", "$InstallDir\logs", "$InstallDir\backups" | Out-Null
$svc = Get-Service -Name VoterInformationSystem -ErrorAction SilentlyContinue
if ($svc -and $svc.Status -eq "Running") { Stop-Service VoterInformationSystem; Start-Sleep -Seconds 3 }
Copy-Item $Jar "$InstallDir\voter-information-system.jar" -Force
if (-not (Test-Path "$InstallDir\config\application.properties")) {
    Copy-Item (Join-Path $here "config\application.properties") "$InstallDir\config\application.properties"
    Write-Host "Created $InstallDir\config\application.properties - EDIT THE PASSWORDS IN IT before starting." -ForegroundColor Yellow
}
(Get-Content (Join-Path $here "VoterInformationSystem.xml")) -replace '<depend>MySQL80</depend>', "<depend>$MySqlService</depend>" |
    Set-Content "$InstallDir\VoterInformationSystem.xml" -Encoding UTF8
Copy-Item (Join-Path $here "backup-mysql.ps1") "$InstallDir\backup-mysql.ps1" -Force

# 4. WinSW service wrapper
$exe = "$InstallDir\VoterInformationSystem.exe"
if (-not (Test-Path $exe)) {
    $url = "https://github.com/winsw/winsw/releases/download/v2.12.0/WinSW-x64.exe"
    Write-Host "Downloading WinSW from $url"
    try { [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest $url -OutFile $exe -UseBasicParsing }
    catch { throw "Could not download WinSW. Download WinSW-x64.exe manually, save it as $exe and run this script again." }
}

# 5. Lock down the config folder (contains passwords): Administrators + SYSTEM only
icacls "$InstallDir\config" /inheritance:r /grant:r "Administrators:(OI)(CI)F" "SYSTEM:(OI)(CI)F" | Out-Null

# 6. Install / update the service
if (-not $svc) { & $exe install; Write-Host "Service installed." } else { Write-Host "Service already installed - updated files." }

if ($OpenFirewall) {
    New-NetFirewallRule -DisplayName "Voter Information System 8080" -Direction Inbound -Protocol TCP -LocalPort 8080 -Action Allow -ErrorAction SilentlyContinue | Out-Null
    Write-Host "Firewall: port 8080 opened. Also remove 'server.address=127.0.0.1' from the config." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Next:" -ForegroundColor Green
Write-Host "  1. Edit $InstallDir\config\application.properties (database and admin passwords)"
Write-Host "  2. Start-Service VoterInformationSystem"
Write-Host "  3. Open http://localhost:8080 on the server   (logs: $InstallDir\logs)"
