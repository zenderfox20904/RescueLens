# RescueLens local setup without Docker (Windows PowerShell).
# Usage: .\scripts\setup.ps1 [-Run]
# Optional environment: DB_DATABASE DB_USERNAME DB_PASSWORD DB_HOST DB_PORT META_MODEL_API_KEY SKIP_INSTALL=1
param([switch]$Run)
$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent $PSScriptRoot
function Get-Opt($name, $default) { $v = [Environment]::GetEnvironmentVariable($name); if ([string]::IsNullOrEmpty($v)) { $default } else { $v } }
$DbName = Get-Opt 'DB_DATABASE' 'rescuelens'; $DbUser = Get-Opt 'DB_USERNAME' 'root'; $DbPass = Get-Opt 'DB_PASSWORD' ''
$DbHost = Get-Opt 'DB_HOST' '127.0.0.1'; $DbPort = Get-Opt 'DB_PORT' '3306'; $ApiPort = Get-Opt 'API_PORT' '8000'; $WebPort = Get-Opt 'WEB_PORT' '5173'
foreach ($c in 'php','composer','npm','mysql') { if (-not (Get-Command $c -ErrorAction SilentlyContinue)) { throw "Missing $c. See docs/SETUP.md" } }

function Set-EnvValue($file, $key, $value) {
  $lines = Get-Content $file
  if ($lines -match "^$key=") { $lines = $lines -replace "^$key=.*$", "$key=$value" } else { $lines += "$key=$value" }
  Set-Content -Path $file -Value $lines -Encoding UTF8
}

Write-Host '1/5 Backend environment'
Set-Location "$Root\backend"
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
Set-EnvValue .env DB_DATABASE $DbName; Set-EnvValue .env DB_USERNAME $DbUser; Set-EnvValue .env DB_PASSWORD $DbPass
Set-EnvValue .env DB_HOST $DbHost; Set-EnvValue .env DB_PORT $DbPort; Set-EnvValue .env APP_URL "http://localhost:$ApiPort"
Set-EnvValue .env CORS_ALLOWED_ORIGINS "http://localhost:$WebPort,http://127.0.0.1:$WebPort"
if ($env:META_MODEL_API_KEY) { Set-EnvValue .env META_MODEL_API_KEY $env:META_MODEL_API_KEY }
if ($env:SKIP_INSTALL -ne '1') { composer install --no-interaction }
if (-not (Select-String -Path .env -Pattern '^APP_KEY=base64:' -Quiet)) { php artisan key:generate --force }

Write-Host "2/5 Database $DbName"
$env:MYSQL_PWD = $DbPass
mysql -h $DbHost -P $DbPort -u $DbUser -e "CREATE DATABASE IF NOT EXISTS ``$DbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan migrate --force

Write-Host '3/5 Demo data'
$users = (mysql -N -h $DbHost -P $DbPort -u $DbUser $DbName -e 'SELECT COUNT(*) FROM users').Trim()
if ($users -eq '0') {
  php artisan db:seed --force
  php artisan db:seed --class=JudgeDemoSeeder --force
  if (Select-String -Path .env -Pattern '^(META_MODEL_API_KEY|LLAMA_API_KEY)=.+' -Quiet) { php artisan rescuelens:analyze-seeded }
} else { Write-Host 'Users already exist, skipping seeding' }
Remove-Item Env:MYSQL_PWD
php artisan storage:link 2>$null

Write-Host '4/5 Web'
Set-Location "$Root\frontend"
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
Set-EnvValue .env VITE_API_URL "http://127.0.0.1:$ApiPort/api/v1"; Set-EnvValue .env VITE_SHARE_BASE "http://127.0.0.1:$ApiPort"
if ($env:SKIP_INSTALL -ne '1') { npm install --no-audit --no-fund }

Write-Host '5/5 Done'
Write-Host "Web: http://localhost:$WebPort   API: http://127.0.0.1:$ApiPort/api/v1"
Write-Host 'Demo logins: docs/DEMO_ACCESS.md (password RescueLensDemo2026!)'
if ($Run) {
  Start-Process php -ArgumentList 'artisan','serve','--host=0.0.0.0',"--port=$ApiPort" -WorkingDirectory "$Root\backend"
  npm run dev -- --port $WebPort --strictPort
}
