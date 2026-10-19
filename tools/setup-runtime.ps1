<#
.SYNOPSIS
    Installs a portable PHP + MariaDB runtime and provisions the project
    databases. No administrator rights and no Windows services are required.

.DESCRIPTION
    Everything lands in ..\_runtime, which is gitignored, so the repository
    stays clean and the setup is reproducible on any Windows machine.

    Use this when you cannot install XAMPP/WAMP/LAMP (for example on a locked
    down or non-admin account). The PHP built-in web server replaces Apache
    for local development.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\setup-runtime.ps1
#>

[CmdletBinding()]
param(
    [string]$Root = '',
    [switch]$SkipDatabase
)

$ErrorActionPreference = 'Stop'

# $PSScriptRoot is not always populated inside a param default, so resolve the
# script directory here instead.
$ScriptDir = $PSScriptRoot
if (-not $ScriptDir) {
    $ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}
if (-not $Root) {
    $Root = Split-Path -Parent $ScriptDir
}

$runtime = Join-Path (Split-Path -Parent $Root) '_runtime'
$phpDir  = Join-Path $runtime 'php'
$mdDir   = Join-Path $runtime 'mariadb'
$myini   = Join-Path $mdDir 'my.ini'

$PHP_VERSION = '8.3.35'
$PHP_URL     = "https://windows.php.net/downloads/releases/php-$PHP_VERSION-nts-Win32-vs16-x64.zip"
$MARIADB_URL = 'https://archive.mariadb.org/mariadb-11.4.7/winx64-packages/mariadb-11.4.7-winx64.zip'

$MARIADB_HOME = Join-Path $mdDir 'mariadb-11.4.7-winx64'

function Write-Step($msg) { Write-Host "==> $msg" -ForegroundColor Cyan }
function Write-Ok($msg)   { Write-Host "    $msg" -ForegroundColor Green }

New-Item -ItemType Directory -Force -Path $runtime, $phpDir, $mdDir | Out-Null

# ---------------------------------------------------------------- PHP -----
if (Test-Path (Join-Path $phpDir 'php.exe')) {
    Write-Step 'PHP already present'
    & (Join-Path $phpDir 'php.exe') -v | Select-Object -First 1 | Write-Ok
} else {
    Write-Step "Downloading PHP $PHP_VERSION"
    $zip = Join-Path $runtime 'php.zip'
    Invoke-WebRequest -Uri $PHP_URL -OutFile $zip -UseBasicParsing -TimeoutSec 900
    Expand-Archive -Path $zip -DestinationPath $phpDir -Force
    Remove-Item $zip -Force
    Write-Ok 'PHP extracted'
}

# php.ini with the extensions the tasks need
$iniPath = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $iniPath)) {
    Write-Step 'Writing php.ini'
    $ini = @"
[PHP]
engine = On
memory_limit = 256M
max_execution_time = 30
error_reporting = E_ALL
display_errors = On
log_errors = On
expose_php = Off
allow_url_fopen = On
allow_url_include = Off
date.timezone = "Asia/Kolkata"
default_charset = "UTF-8"

session.save_path = "$($phpDir -replace '\\','/')/sessions"
session.use_strict_mode = 1
session.use_only_cookies = 1
session.cookie_httponly = 1
session.cookie_samesite = Lax

file_uploads = On
upload_max_filesize = 4M
post_max_size = 8M

extension_dir = "ext"
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=mysqli
extension=openssl
extension=pdo_mysql
extension=sodium
extension=zip
extension=intl
extension=exif
"@
    Set-Content -Path $iniPath -Value $ini -Encoding ASCII
    New-Item -ItemType Directory -Force -Path (Join-Path $phpDir 'sessions') | Out-Null
    Write-Ok 'php.ini written'
}

& (Join-Path $phpDir 'php.exe') -r "echo 'mysqli=',extension_loaded('mysqli')?'yes':'no',' pdo_mysql=',extension_loaded('pdo_mysql')?'yes':'no',PHP_EOL;" | Write-Ok

# ------------------------------------------------------------ MariaDB -----
if (Test-Path (Join-Path $MARIADB_HOME 'bin\mysqld.exe')) {
    Write-Step 'MariaDB already present'
} else {
    Write-Step 'Downloading MariaDB 11.4.7 (about 90 MB)'
    $zip = Join-Path $runtime 'mariadb.zip'
    Invoke-WebRequest -Uri $MARIADB_URL -OutFile $zip -UseBasicParsing -TimeoutSec 1800
    Expand-Archive -Path $zip -DestinationPath $mdDir -Force
    Remove-Item $zip -Force
    Write-Ok 'MariaDB extracted'
}

$dataDir = Join-Path $mdDir 'data'
if (-not (Test-Path $myini)) {
    Write-Step 'Writing my.ini'
    $basedir = $MARIADB_HOME -replace '\\', '/'
    $datadir = $dataDir -replace '\\', '/'
    $ini = @"
[client]
port = 3306
default-character-set = utf8mb4

[mysqld]
basedir = $basedir
datadir = $datadir
port = 3306
bind-address = 127.0.0.1
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci
skip-name-resolve
max_connections = 60
log-error = $datadir/mariadb-error.log
"@
    Set-Content -Path $myini -Value $ini -Encoding ASCII
    Write-Ok 'my.ini written'
}

if (-not (Test-Path (Join-Path $dataDir 'mysql'))) {
    Write-Step 'Initialising the MariaDB data directory'
    New-Item -ItemType Directory -Force -Path $dataDir | Out-Null
    & (Join-Path $MARIADB_HOME 'bin\mariadb-install-db.exe') -d $dataDir -p '' -P 3306 -s
    Write-Ok 'Data directory initialised'
}

# ------------------------------------------------- shared/config + creds ---
# Credentials are generated on first run and written to the gitignored
# shared/config.php. Nothing secret is ever stored in this repository.
$cfgPath = Join-Path $Root 'shared\config.php'

if (-not (Test-Path $cfgPath)) {
    Write-Step 'Creating shared/config.php from the example'
    $example = Join-Path $Root 'shared\config.example.php'
    if (-not (Test-Path $example)) { throw "Missing $example" }
    Copy-Item $example $cfgPath
    Write-Ok 'shared/config.php created (gitignored)'
}

$cfgText = Get-Content $cfgPath -Raw

function Write-Utf8NoBom {
    # PowerShell 5.1's `Set-Content -Encoding UTF8` writes a BOM, which makes
    # `declare(strict_types=1)` no longer the first statement and fatals PHP.
    param([string]$Path, [string]$Text)
    [System.IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}

function New-RandomPassword {
    # PowerShell 5.1 runs on .NET Framework, which has no
    # [RandomNumberGenerator]::Fill(), so use the older provider.
    $rng = New-Object System.Security.Cryptography.RNGCryptoServiceProvider
    $buf = New-Object byte[] 24
    $rng.GetBytes($buf)
    $rng.Dispose()
    return ([Convert]::ToBase64String($buf).TrimEnd('=').Replace('+', 'A').Replace('/', 'b'))
}

# Check the empty default first: the "non-empty" pattern would also match an
# empty quoted string, so testing it first would skip generation entirely.
if ($cfgText -match "'pass'\s*=>\s*getenv\('DB_PASS'\)\s*\?:\s*''") {
    # First run: generate a strong random password.
    $AppPass = New-RandomPassword
    $cfgText = $cfgText -replace "('pass'\s*=>\s*getenv\('DB_PASS'\)\s*\?:\s*)''", "`$1'$AppPass'"
    Write-Utf8NoBom -Path $cfgPath -Text $cfgText
    Write-Ok 'Random password generated and stored in shared/config.php'
} elseif ($cfgText -match "'pass'\s*=>\s*getenv\('DB_PASS'\)\s*\?:\s*'([^']+)'") {
    $AppPass = $Matches[1]
    Write-Ok 'Loaded existing database password from shared/config.php'
} else {
    throw "Could not determine the DB password from $cfgPath"
}

if ($cfgText -match "'name'\s*=>\s*getenv\('DB_NAME'\)\s*\?:\s*'([^']*)'")   { $AppDb   = $Matches[1] }
if ($cfgText -match "'user'\s*=>\s*getenv\('DB_USER'\)\s*\?:\s*'([^']*)'")   { $AppUser = $Matches[1] }
if ($cfgText -match "'host'\s*=>\s*getenv\('DB_HOST'\)\s*\?:\s*'([^']*)'")   { $DbHost  = $Matches[1] }

$CyberDb = 'apexplanet_cyber'

# The root account is local-only and its password is never written to disk.
$RootPass = New-RandomPassword

# ------------------------------------------------------- database setup ---
function Stop-Db {
    Get-Process mysqld -ErrorAction SilentlyContinue | ForEach-Object { try { $_.Kill() } catch {} }
    Start-Sleep -Seconds 4
}
function Start-Db([string[]]$extra = @()) {
    Start-Process -FilePath (Join-Path $MARIADB_HOME 'bin\mysqld.exe') `
        -ArgumentList (@("--defaults-file=$myini") + $extra) -WindowStyle Hidden
    Start-Sleep -Seconds 12
}

if ($SkipDatabase) {
    Write-Step 'Skipping database provisioning (-SkipDatabase)'
} else {
    Stop-Db
    Write-Step 'Starting MariaDB with --skip-grant-tables to provision accounts'
    Start-Db @('--skip-grant-tables')

    $safeApp = $AppPass.Replace("'", "''")
    $safeRoot = $RootPass.Replace("'", "''")
    $sql = @"
FLUSH PRIVILEGES;
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$safeRoot';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS $AppDb   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS $CyberDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$AppUser'@'127.0.0.1' IDENTIFIED BY '$safeApp';
ALTER USER '$AppUser'@'127.0.0.1' IDENTIFIED BY '$safeApp';
GRANT ALL PRIVILEGES ON $AppDb.*   TO '$AppUser'@'127.0.0.1';
GRANT ALL PRIVILEGES ON $CyberDb.* TO '$AppUser'@'127.0.0.1';
FLUSH PRIVILEGES;
"@
    $sqlFile = Join-Path $mdDir 'provision.sql'
    Set-Content -Path $sqlFile -Value $sql -Encoding ASCII
    Get-Content $sqlFile -Raw | & (Join-Path $MARIADB_HOME 'bin\mariadb.exe') --defaults-file=$myini -u root -h 127.0.0.1
    Remove-Item $sqlFile -Force

    Stop-Db
    Write-Step 'Restarting MariaDB normally'
    Start-Db

    & (Join-Path $MARIADB_HOME 'bin\mariadb.exe') --defaults-file=$myini -u $AppUser "-p$AppPass" -h 127.0.0.1 -e 'SHOW DATABASES;' |
        ForEach-Object { Write-Ok $_ }
}

Write-Host ''
Write-Host 'Setup complete.' -ForegroundColor Green
Write-Host ''
Write-Host '  Start a task:   npm run task:1'
Write-Host '  Run the tests:  npm run test:1'
Write-Host ''
