#requires -version 5.1
# mobile2 post-apply runbook — runs end-to-end on the build/runtime host.
# Run from the project root:
#   PowerShell -ExecutionPolicy Bypass -File storage\app\mobile2-patches\post-apply.ps1

$ErrorActionPreference = 'Stop'

function Step($n, $label) { Write-Host "`n=== Step $n — $label ===" -ForegroundColor Cyan }
function OK($msg)  { Write-Host "  OK: $msg" -ForegroundColor Green }
function Note($msg){ Write-Host "  $msg" -ForegroundColor Yellow }

$projectRoot = (Get-Location).Path
Note "Project root: $projectRoot"

if (-not (Test-Path "$projectRoot\artisan")) {
    Write-Host "artisan not found — run this from the Laravel project root." -ForegroundColor Red
    exit 1
}

# ── Step 1 — composer install ───────────────────────────────────────────────
Step 1 "composer install"
if (-not (Test-Path "$projectRoot\vendor\autoload.php")) {
    composer install --no-interaction --prefer-dist --optimize-autoloader
} else {
    Note "vendor/ already present — skipping (run 'composer update' manually if needed)"
}
OK "composer ready"

# ── Step 2 — npm install ────────────────────────────────────────────────────
Step 2 "npm install"
npm install --no-audit --no-fund
OK "node_modules ready"

# ── Step 3 — migrations ─────────────────────────────────────────────────────
Step 3 "php artisan migrate"
php artisan migrate --force
OK "migrations applied"

# ── Step 4 — Vue build ──────────────────────────────────────────────────────
Step 4 "npm run build"
npm run build
OK "Vue assets built"

# ── Step 5 — cache clear + cache warm ───────────────────────────────────────
Step 5 "cache clear & warm"
php artisan optimize:clear
php artisan route:cache
php artisan config:cache
OK "caches refreshed"

# ── Step 6 — verification hints ─────────────────────────────────────────────
Step 6 "smoke test hints"
Note "Generate a bearer token (replace EMAIL_HERE):"
Note "  php artisan tinker --execute=`"`$u = App\\Models\\User::where('email','EMAIL_HERE')->first(); echo 'BEARER='.$u->createToken('debug')->plainTextToken.PHP_EOL;`""
Note ""
Note "Then verify these endpoints (replace <T> + <host>):"
Note "  GET  /api/mobile/exams                          (teacher)"
Note "  GET  /api/mobile/transport/driver/vehicles      (driver)"
Note "  GET  /api/mobile/front-office/gate/stats        (gate keeper)"
Note "  GET  /api/mobile/children                       (parent)"
Note "  POST /api/mobile/device/register {expo_push_token,device_platform}"
Note ""
Note "UI: User Management, Communication Templates, Students list, Due Report — see POST_APPLY_RUNBOOK.md."

Write-Host "`nAll done." -ForegroundColor Green
