#!/bin/bash
# =============================================================================
# Fresh-install for a generic school deployment from trivartatech/schools1.
# Run ONCE on a freshly-cloned server checkout. For subsequent updates use ./deploy.sh.
#
# Prerequisites: PHP 8.2+, Composer, Node 18+, npm, MySQL 8+, .env filled in.
#
# Two ways to provide config:
#   A) school-setup.xlsx (friendly UI) — bootstrap.sh auto-converts to .env
#   B) .env directly (technical users) — copy from .env.production.example
# =============================================================================
set -e

echo "🚀 School ERP — Fresh Deploy"

# Guard: don't re-run on already-bootstrapped clone
if [ -f .bootstrap-done ]; then
    echo "ℹ️  This clone has already been bootstrapped (see .bootstrap-done)."
    echo "   For updates, use: ./deploy.sh"
    echo "   To force re-bootstrap, delete .bootstrap-done first."
    exit 1
fi

# Auto-generate .env from school-setup.xlsx if user filled that instead of .env
if [ ! -f .env ] && [ -f school-setup.xlsx ]; then
    echo "📋 Found school-setup.xlsx → generating .env…"
    composer install --no-dev --optimize-autoloader --no-interaction
    php artisan school:configure-from-xlsx school-setup.xlsx
fi

if [ ! -f .env ]; then
    echo "❌ Neither .env nor school-setup.xlsx found."
    echo "   Option A: cp school-setup.example.xlsx school-setup.xlsx, fill it, rerun ./bootstrap.sh"
    echo "   Option B: cp .env.production.example .env, fill values, rerun ./bootstrap.sh"
    exit 1
fi

# Source .env (warn if it contains shell-unsafe chars)
if grep -qE '^[A-Z_]+=.*[#$!\\].*' .env 2>/dev/null; then
    echo "⚠️  .env may contain shell-unsafe chars (#, \$, !, \\)."
    echo "    If sourcing fails, escape them or pick simpler password values."
fi
set -a
# shellcheck disable=SC1091
source .env
set +a

# 1. Auto-create DB + user if root creds present
if [ -n "${DB_ROOT_USERNAME:-}" ] && [ -n "${DB_ROOT_PASSWORD:-}" ]; then
    echo "📦 Creating MySQL database '$DB_DATABASE' and user '$DB_USERNAME'…"
    mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_ROOT_USERNAME" -p"$DB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'localhost';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%';
FLUSH PRIVILEGES;
SQL
    echo "✅ Database ready."
else
    echo "ℹ️  DB_ROOT_* not set — assuming database '$DB_DATABASE' already exists."
fi

# 2. PHP deps
echo "📦 composer install…"
composer install --no-dev --optimize-autoloader --no-interaction

# 3. APP_KEY (only if missing)
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY…"
    php artisan key:generate --force
fi

# 4. Storage symlink
php artisan storage:link || true

# 5. CRITICAL: clear any stale config cache so the seeder reads .env via config('school.*')
php artisan config:clear

# 6. Migrations
echo "🗄  Running migrations…"
php artisan migrate --force

# 7. Seeds — REQUIRED order: roles → school → grading → templates
echo "🌱 Seeding…"
php artisan db:seed --class=RolePermissionSeeder --force
php artisan db:seed --class=GenericSchoolSeeder --force
php artisan db:seed --class=GradingSystemSeeder --force
php artisan db:seed --class=CommunicationTemplateSeeder --force

# 8. Frontend build (skip if pre-built and uploaded)
if [ ! -d public/build ]; then
    echo "🏗  Building frontend assets…"
    npm install
    npm run build
fi

# 9. Caches
echo "📚 Caching configs/routes/views/events…"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 10. Permissions (Linux/cPanel/CloudPanel)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 11. Mark bootstrap complete
date -u +"%Y-%m-%dT%H:%M:%SZ" > .bootstrap-done

echo ""
echo "✅ Deploy complete. Login at: ${APP_URL}/login"
echo "   Super admin:  ${SUPER_ADMIN_EMAIL}"
echo "   School admin: ${ADMIN_EMAIL}"
echo "   Principal:    ${PRINCIPAL_EMAIL}"
echo "   Temp password: ${DEFAULT_PASSWORD}  (change on first login!)"
echo ""
echo "⚠️  Set up cron:"
echo "    * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
echo "⚠️  Run queue worker via supervisor/systemd:"
echo "    php artisan queue:work --queue=default,notifications --tries=1"
