<#
.SYNOPSIS
    Single entry point for every ApexPlanet Full Stack task.

.DESCRIPTION
    Resolves the PHP binary (system PATH, then the portable runtime created by
    tools\setup-runtime.ps1), then starts or tests the requested task.

.EXAMPLE
    .\tools\task.ps1 task:1
    .\tools\task.ps1 test:1
    .\tools\task.ps1 db:start
    .\tools\task.ps1 help
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, Position = 0)]
    [string]$Command
)

$ErrorActionPreference = 'Stop'

$Root    = Split-Path -Parent $PSScriptRoot
$Runtime = Join-Path (Split-Path -Parent $Root) '_runtime'

# ----------------------------------------------------------- resolve PHP ---
function Resolve-Php {
    if ($env:APEX_PHP -and (Test-Path $env:APEX_PHP)) { return $env:APEX_PHP }
    $onPath = Get-Command php -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }
    $portable = Join-Path $Runtime 'php\php.exe'
    if (Test-Path $portable) { return $portable }
    throw "PHP not found. Run: powershell -File tools\setup-runtime.ps1"
}

# ------------------------------------------------------------- resolve DB ---
function Get-MariadbBin {
    $bin = Join-Path $Runtime 'mariadb\mariadb-11.4.7-winx64\bin'
    if (-not (Test-Path $bin)) {
        $found = Get-ChildItem (Join-Path $Runtime 'mariadb') -Directory -ErrorAction SilentlyContinue |
                 Where-Object { Test-Path (Join-Path $_.FullName 'bin\mysqld.exe') } |
                 Select-Object -First 1
        if ($found) { $bin = Join-Path $found.FullName 'bin' }
    }
    return $bin
}

# ------------------------------------------------------------------ usage ---
function Show-Help {
    Write-Host ''
    Write-Host 'ApexPlanet Full Stack Web Development - task runner' -ForegroundColor Cyan
    Write-Host ''
    Write-Host '  Serve a task (starts a web server, Ctrl+C to stop)'
    Write-Host '    .\tools\task.ps1 task:1     # portfolio + PHP + MySQL demos   :8081'
    Write-Host '    .\tools\task.ps1 task:2     # responsive auth UI               :8082'
    Write-Host '    .\tools\task.ps1 task:3     # user management CRUD             :8083'
    Write-Host '    .\tools\task.ps1 task:4     # storefront + admin panel         :8084'
    Write-Host '    .\tools\task.ps1 task:5     # capstone portal                   :8085'
    Write-Host ''
    Write-Host '  Run the automated tests for a task'
    Write-Host '    .\tools\task.ps1 test:1'
    Write-Host ''
    Write-Host '  Database control'
    Write-Host '    .\tools\task.ps1 db:start'
    Write-Host '    .\tools\task.ps1 db:stop'
    Write-Host '    .\tools\task.ps1 db:status'
    Write-Host ''
    Write-Host '  One-time setup (portable PHP + MariaDB)'
    Write-Host '    .\tools\task.ps1 setup'
    Write-Host ''
}

# ----------------------------------------------------------------- serve ---
if ($Command -match '^task:([1-5])$') {
    $n      = $Matches[1]
    $docroot = Join-Path $Root "task-$n\public"
    if (-not (Test-Path $docroot)) {
        Write-Host "task-$n does not exist yet: $docroot" -ForegroundColor Red
        exit 1
    }
    $port = 8080 + [int]$n
    $php  = Resolve-Php
    Write-Host "Task $n  ->  http://127.0.0.1:$port/" -ForegroundColor Green
    Write-Host "Document root: $docroot" -ForegroundColor DarkGray
    Write-Host "Press Ctrl+C to stop.`n" -ForegroundColor DarkGray
    & $php -S "127.0.0.1:$port" -t $docroot
    exit $LASTEXITCODE
}

# ------------------------------------------------------------------ test ---
if ($Command -match '^test:([1-5])$') {
    $n     = $Matches[1]
    $suite = Join-Path $Root "task-$n\tests\run-tests.php"
    if (-not (Test-Path $suite)) {
        Write-Host "No test suite for task-$n yet." -ForegroundColor Yellow
        exit 1
    }
    $php = Resolve-Php
    & $php $suite
    exit $LASTEXITCODE
}

# -------------------------------------------------------------------- db ---
$bin = Get-MariadbBin
$myini = Join-Path $Runtime 'mariadb\my.ini'

switch -Regex ($Command) {
    '^db:start$' {
        Start-Process -FilePath (Join-Path $bin 'mysqld.exe') `
            -ArgumentList "--defaults-file=$myini" -WindowStyle Hidden
        Start-Sleep -Seconds 10
        Write-Host 'MariaDB starting on 127.0.0.1:3306 ...' -ForegroundColor Green
    }
    '^db:stop$' {
        Get-Process mysqld -ErrorAction SilentlyContinue | ForEach-Object { try { $_.Kill() } catch {} }
        Write-Host 'MariaDB stopped.' -ForegroundColor Green
    }
    '^db:status$' {
        if (Get-Process mysqld -ErrorAction SilentlyContinue) {
            Write-Host 'MariaDB is running.' -ForegroundColor Green
            & (Join-Path $bin 'mariadb.exe') --defaults-file=$myini -u apexplanet '-pApexApp_2026!dev' `
                -h 127.0.0.1 -e 'SHOW DATABASES;' 2>&1 | ForEach-Object { "  $_" }
        } else {
            Write-Host 'MariaDB is not running. Start it with: .\tools\task.ps1 db:start' -ForegroundColor Yellow
        }
    }
    '^setup$' {
        & (Join-Path $PSScriptRoot 'setup-runtime.ps1')
    }
    '^(help|--help|-h)$' { Show-Help }
    default {
        Write-Host "Unknown command: $Command" -ForegroundColor Red
        Show-Help
        exit 1
    }
}
