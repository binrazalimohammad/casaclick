# Start CasaClick locally (Windows PowerShell)
# Usage: .\scripts\start-dev.ps1

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "Starting MySQL (Docker)..." -ForegroundColor Cyan
docker start websitedev-mysql-1 2>$null
if ($LASTEXITCODE -ne 0) {
    Write-Host "Creating containers via docker compose..." -ForegroundColor Yellow
    docker compose up -d mysql
}

$ready = $false
foreach ($i in 1..30) {
    try {
        php bin/console doctrine:query:sql "SELECT 1" 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    } catch { }
    Start-Sleep -Seconds 2
}

if (-not $ready) {
    Write-Host "WARNING: MySQL is not reachable on port 3308." -ForegroundColor Red
    Write-Host "  - Open Docker Desktop and wait until it is running" -ForegroundColor Yellow
    Write-Host "  - Then run: docker start websitedev-mysql-1" -ForegroundColor Yellow
    Write-Host "  Public pages may work; login/dashboard need the database." -ForegroundColor Yellow
} else {
    Write-Host "Database OK." -ForegroundColor Green
}

if (-not (Test-Path "public\build\entrypoints.json")) {
    Write-Host "Building front-end assets (first time)..." -ForegroundColor Cyan
    npm run build
}

php bin/console cache:clear --no-warmup 2>$null | Out-Null

Write-Host ""
Write-Host "Open: http://127.0.0.1:8000" -ForegroundColor Green
Write-Host "Press Ctrl+C to stop the server." -ForegroundColor DarkGray
php -S 127.0.0.1:8000 -t public
