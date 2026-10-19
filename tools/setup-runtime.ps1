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
    [string]$Root = (Split-Path -Parent $PSScriptRoot),
    [switch]$SkipDatabase
)

$ErrorActionPreference = 'Stop'

$runtime = Join-Path (Split-Path -Parent $Root) '_runtime'
$phpDir  = Join-Path $runtime 'php'
$mdDir   = Join-Path $runtime 'mariadb'
$myini   = Join-Path $mdDir 'my.ini'

$PHP_VERSION = '8.3.35'
$PHP_URL     = "https://windows.php.net/downloads/releases/php-$PHP_VERSION-nts-Win32-vs16-x64.zip"
$MARIADB_URL = 'https://archive.mariadb.org/mariadb-11.4.7/winx64-packages/mariadb-11.4.7-winx64.zip'

$MARIADB_HOME = Join-Path $mdDir 'mariadb-11.4.7-winx64'

# Credentials used by the project. Development only.
$RootUser = 'root'
$RootPass = 'apex_dev_root_2026'
$AppUser  = 'apexplanet'
$AppPass  = 'ApexApp_2026!dev'
$AppDb    = 'apexplanet_fullstack'
$CyberDb  = 'apexplanet_cyber'

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

    $sql = @"
FLUSH PRIVILEGES;
ALTER USER 'root'@'127.0.0.1' IDENTIFIED BY '$RootPass';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
CREATE DATABASE IF NOT EXISTS $AppDb   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS $CyberDb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$AppUser'@'127.0.0.1' IDENTIFIED BY '$AppPass';
ALTER USER '$AppUser'@'127.0.0.1' IDENTIFIED BY '$AppPass';
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

# ------------------------------------------------------- shared/config ----
$cfg = Join-Path $Root 'shared\config.php'
if (-not (Test-Path $cfg)) {
    Write-Step 'Creating shared/config.php from the example'
    Copy-Item (Join-Path $Root 'shared\config.example.php') $cfg
    Write-Ok 'shared/config.php created (gitignored)'
}

Write-Host ''
Write-Host 'Setup complete.' -ForegroundColor Green
Write-Host ''
Write-Host '  Start a task:   php -S 127.0.0.1:8081 -t task-1\public'
Write-Host '  Run the tests:  php task-1\tests\run-tests.php'
Write-Host ''
