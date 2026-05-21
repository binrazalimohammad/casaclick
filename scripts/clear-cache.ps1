# Fix "Access is denied" on var/cache (Windows file locks from PHP server / IDE)
# Usage: .\scripts\clear-cache.ps1

$ErrorActionPreference = "Continue"
Set-Location (Split-Path $PSScriptRoot -Parent)

Write-Host "Stopping PHP processes that may lock var/cache..." -ForegroundColor Cyan
Get-Process php -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2

Write-Host "Removing var/cache/dev ..." -ForegroundColor Cyan
Remove-Item -Recurse -Force "var\cache\dev" -ErrorAction SilentlyContinue

Write-Host "Rebuilding Symfony cache ..." -ForegroundColor Cyan
php bin/console cache:clear

Write-Host "Done. Start the server again: php -S 0.0.0.0:8000 -t public" -ForegroundColor Green
