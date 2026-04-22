#!/bin/bash
# =============================================================================
# Fresh-deploy script for Sree Gurukula International School
# sreegurukulainternationalschool.com
#
# Run this ONCE on a freshly-cloned server checkout. For subsequent updates
# use the regular deploy.sh.
#
# Prerequisites:
#   1. PHP 8.2+, Composer, Node, npm, MySQL 8+
#   2. .env created from .env.sree-production.example with DB_* filled in
#   3. If DB_ROOT_PASSWORD is set, the DB will be created automatically;
#      otherwise create the database manually before running.
# =============================================================================
set -e

echo "🚀 Sree Gurukula International School — Fresh Deploy"

if [ ! -f .env ]; then
    echo "❌ .env missing. Copy .env.sree-production.example → .env and fill MySQL creds first."
    exit 1
fi

# Load .env (handles quoted values with spaces)
set -a
source .env
set +a

# 1. Create DB if root creds provided
if [ -n "$DB_ROOT_USERNAME" ] && [ -n "$DB_ROOT_PASSWORD" ]; then
    echo "📦 Creating MySQL database '$DB_DATABASE' (if missing)…"
    mysql -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_ROOT_USERNAME" -p"$DB_ROOT_PASSWORD" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_DATABASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USERNAME'@'%' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON \`$DB_DATABASE\`.* TO '$DB_USERNAME'@'%';
FLUSH PRIVILEGES;
SQL
    echo "✅ Database ready."
else
    echo "ℹ️  DB_ROOT_* not set — assuming database '$DB_DATABASE' already exists."
fi

# 2. PHP dependencies
echo "📦 composer install…"
composer install --no-dev --optimize-autoloader --no-interaction

# 3. App key (only if missing)
if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate --force
fi

# 4. Storage symlink
php artisan storage:link || true

# 5. Migrate + minimal seed
echo "🗄  Running migrations…"
php artisan migrate --force

echo "🌱 Seeding roles/permissions…"
php artisan db:seed --class=RolePermissionSeeder --force

echo "🌱 Seeding school (Sree Gurukula)…"
php artisan db:seed --class=SreeGurukulaSchoolSeeder --force

echo "🌱 Seeding grading system + communication templates…"
php artisan db:seed --class=GradingSystemSeeder --force || true
php artisan db:seed --class=CommunicationTemplateSeeder --force || true

# 6. Frontend build (skip if already built locally and uploaded)
if [ ! -d public/build ]; then
    echo "🏗  Building frontend assets…"
    npm install
    npm run build
fi

# 7. Caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Permissions (Linux/cPanel/CloudPanel)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo ""
echo "✅ Sree Gurukula deploy complete."
echo ""
echo "Login at: https://sreegurukulainternationalschool.com/login"
echo "  Super admin: superadmin@sreegurukulainternationalschool.com"
echo "  School admin: admin@sreegurukulainternationalschool.com"
echo "  Principal:    principal@sreegurukulainternationalschool.com"
echo "  Temp password: ChangeMe@2026  (change on first login!)"
echo ""
echo "⚠️  Reminder: set up cron"
echo "   * * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
echo "⚠️  Reminder: run a queue worker (supervisor/systemd)"
echo "   php artisan queue:work --queue=default,notifications --tries=1"
