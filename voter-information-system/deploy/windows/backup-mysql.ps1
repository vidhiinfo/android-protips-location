<#
.SYNOPSIS
  Daily MySQL backup (utf8mb4-safe), keeps the last 30 days. Schedule it with Task Scheduler - see docs/WINDOWS-HOSTING.md.
  The password is read from a MySQL option file so it never appears on the command line.
#>
param(
    [string]$BackupDir = "C:\VoterSystem\backups",
    [string]$Database = "voter_db",
    [string]$OptionFile = "C:\VoterSystem\config\mysql-backup.cnf",   # [client] user=... password=...
    [string]$MySqlBin = "C:\Program Files\MySQL\MySQL Server 8.0\bin",
    [int]$KeepDays = 30
)
$ErrorActionPreference = "Stop"
New-Item -ItemType Directory -Force -Path $BackupDir | Out-Null
$stamp = Get-Date -Format "yyyy-MM-dd_HHmm"
$file = Join-Path $BackupDir "$Database-$stamp.sql"
& "$MySqlBin\mysqldump.exe" "--defaults-extra-file=$OptionFile" --default-character-set=utf8mb4 --single-transaction --routines --triggers --hex-blob $Database --result-file="$file"
if ($LASTEXITCODE -ne 0) { throw "mysqldump failed with exit code $LASTEXITCODE" }
Compress-Archive -Path $file -DestinationPath "$file.zip" -Force
Remove-Item $file
Get-ChildItem $BackupDir -Filter "$Database-*.sql.zip" | Where-Object { $_.LastWriteTime -lt (Get-Date).AddDays(-$KeepDays) } | Remove-Item
Write-Host "Backup written: $file.zip"
