param(
  [string]$ProjectRoot = (Resolve-Path ".").Path,
  [string]$DumpDir = "storage\\backups",
  [string]$MysqlDumpPath = "C:\\xampp\\mysql\\bin\\mysqldump.exe"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-EnvValue {
  param([string]$Path, [string]$Key)
  if (!(Test-Path $Path)) { return $null }
  $line = Select-String -Path $Path -Pattern "^\s*$Key=" -SimpleMatch | Select-Object -First 1
  if (!$line) { return $null }
  $value = $line.Line.Substring($line.Line.IndexOf("=") + 1)
  return $value.Trim().Trim('"')
}

$envPath = Join-Path $ProjectRoot ".env"
$dbName = Get-EnvValue -Path $envPath -Key "DB_DATABASE"
$dbUser = Get-EnvValue -Path $envPath -Key "DB_USERNAME"
$dbPass = Get-EnvValue -Path $envPath -Key "DB_PASSWORD"
$dbHost = Get-EnvValue -Path $envPath -Key "DB_HOST"
$dbPort = Get-EnvValue -Path $envPath -Key "DB_PORT"

if ([string]::IsNullOrWhiteSpace($dbName)) { throw "DB_DATABASE tidak ditemukan di .env" }
if ([string]::IsNullOrWhiteSpace($dbUser)) { $dbUser = "root" }
if ([string]::IsNullOrWhiteSpace($dbHost)) { $dbHost = "127.0.0.1" }
if ([string]::IsNullOrWhiteSpace($dbPort)) { $dbPort = "3306" }

$dumpDirFull = Join-Path $ProjectRoot $DumpDir
New-Item -ItemType Directory -Force -Path $dumpDirFull | Out-Null

if (!(Test-Path $MysqlDumpPath)) {
  $MysqlDumpPath = "C:\\xampp\\mysql\\bin\\mysqldump"
}
if (!(Test-Path $MysqlDumpPath)) {
  throw "mysqldump tidak ditemukan. Set -MysqlDumpPath ke lokasi mysqldump.exe"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$outFile = Join-Path $dumpDirFull "$dbName-backup-$timestamp.sql"

$args = @("-h", $dbHost, "-P", $dbPort, "-u", $dbUser)
if (![string]::IsNullOrWhiteSpace($dbPass)) {
  $args += @("-p$dbPass")
}
$args += @($dbName)

& $MysqlDumpPath @args > $outFile

Write-Output "Backup selesai: $outFile"
