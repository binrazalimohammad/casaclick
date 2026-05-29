# Set Google OAuth variables on Railway (reads websitedev or casaclick .env.local).
# Prerequisite: railway login  (once)
# Usage: powershell -ExecutionPolicy Bypass -File scripts\set-railway-google-env.ps1

$ErrorActionPreference = 'Stop'

$defaultUri = 'https://web-production-6bdab.up.railway.app'
$envFiles = @(
    (Join-Path $PSScriptRoot '..\.env.local'),
    'C:\Users\Maligalig\websitedev\.env.local'
)

function Read-DotEnvValue([string]$path, [string]$key) {
    if (-not (Test-Path $path)) { return $null }
    foreach ($line in Get-Content $path) {
        if ($line -match "^\s*$key\s*=\s*(.+)\s*$") {
            return $Matches[1].Trim().Trim('"')
        }
    }
    return $null
}

$clientId = $null
$mobileId = $null
$secret = $null

foreach ($f in $envFiles) {
    if (-not $clientId) { $clientId = Read-DotEnvValue $f 'GOOGLE_OAUTH_CLIENT_ID' }
    if (-not $mobileId) { $mobileId = Read-DotEnvValue $f 'GOOGLE_MOBILE_WEB_CLIENT_ID' }
    if (-not $secret) { $secret = Read-DotEnvValue $f 'GOOGLE_OAUTH_CLIENT_SECRET' }
}

if (-not $clientId) {
    $clientId = '264534690497-r526rlqoptnnbkmk19bnro0sdnkji1hv.apps.googleusercontent.com'
}
if (-not $mobileId) {
    $mobileId = '264534690497-j0cl3eep1p4ensqk7es8moj6npsl92o7.apps.googleusercontent.com'
}

if (-not $secret) {
    Write-Error 'GOOGLE_OAUTH_CLIENT_SECRET not found in .env.local. Add it from Google Cloud Console.'
}

$railway = Get-Command railway -ErrorAction SilentlyContinue
if (-not $railway) {
    Write-Host 'Installing Railway CLI...'
    npm install -g @railway/cli | Out-Null
}

$prevEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
railway whoami 2>&1 | Out-Null
$loggedIn = ($LASTEXITCODE -eq 0)
$ErrorActionPreference = $prevEap

if (-not $loggedIn) {
    Write-Host ''
    Write-Host 'Not logged in to Railway. Run once:'
    Write-Host '  railway login'
    Write-Host ''
    Write-Host 'Then re-run:'
    Write-Host '  powershell -ExecutionPolicy Bypass -File scripts\set-railway-google-env.ps1'
    Write-Host ''
    Write-Host 'Or paste these in Railway - web service - Variables - RAW:'
    Write-Host "DEFAULT_URI=$defaultUri"
    Write-Host "GOOGLE_OAUTH_CLIENT_ID=$clientId"
    Write-Host "GOOGLE_MOBILE_WEB_CLIENT_ID=$mobileId"
    Write-Host "GOOGLE_OAUTH_CLIENT_SECRET=$secret"
    exit 1
}

Push-Location (Join-Path $PSScriptRoot '..')
try {
    Write-Host "Setting Google OAuth on Railway (DEFAULT_URI=$defaultUri)..."
    railway variables --set "DEFAULT_URI=$defaultUri" --skip-deploys
    railway variables --set "GOOGLE_OAUTH_CLIENT_ID=$clientId" --skip-deploys
    railway variables --set "GOOGLE_MOBILE_WEB_CLIENT_ID=$mobileId" --skip-deploys
    railway variables --set "GOOGLE_OAUTH_CLIENT_SECRET=$secret" --skip-deploys
    Write-Host 'Triggering redeploy...'
    railway up --detach 2>$null
    if ($LASTEXITCODE -ne 0) {
        Write-Host 'Variables saved. Redeploy from Railway dashboard if needed.'
    }
    Write-Host ''
    Write-Host 'Done. Add in Google Cloud (Web OAuth client) if missing:'
    Write-Host "  Redirect: $defaultUri/connect/google/check"
    Write-Host "  Origin:   $defaultUri"
}
finally {
    Pop-Location
}
