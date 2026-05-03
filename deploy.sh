#!/bin/bash
# =============================================================================
# Update deploy script — for subsequent updates after the initial bootstrap.
# Pulls latest code, backs up the DB, runs migrations, rebuilds caches, and
# restarts the queue worker. For the FIRST install on a fresh server, run
# ./bootstrap.sh instead.
#
# Flags:
#   --build-frontend   Also run `php artisan ziggy:generate && npm run build`
#                      Use when routes or frontend assets have changed.
# =============================================================================
set -e

BUILD_FRONTEND=false
for arg in "$@"; do
    [ "$arg" = "--build-frontend" ] && BUILD_FRONTEND=true
done

echo "🚀 Starting deployment..."

# Enter maintenance mode
php artisan down || true

# Auto-fetch latest code from origin (= trivartatech/schools1)
git pull origin main

# Pre-migration MySQL backup (so we can roll back if a migration breaks something)
if [ -f .env ]; then
    # Read DB credentials WITHOUT exporting them — see bootstrap.sh for why
    # `source .env` corrupts subsequent `php artisan` commands via Dotenv's
    # safeLoad(). Extract individual values via awk instead.
    env_get() {
        awk -F= -v k="$1" '
            $1 == k {
                sub(/^[^=]*=/, "")
                sub(/^"/, "")
                sub(/"$/, "")
                print
                exit
            }
        ' .env
    }
    DB_HOST=$(env_get DB_HOST)
    DB_PORT=$(env_get DB_PORT)
    DB_DATABASE=$(env_get DB_DATABASE)
    DB_USERNAME=$(env_get DB_USERNAME)
    DB_PASSWORD=$(env_get DB_PASSWORD)

    BACKUP_DIR=storage/backups
    mkdir -p "$BACKUP_DIR"
    BACKUP_FILE="$BACKUP_DIR/$(date +%Y%m%d-%H%M%S)-${DB_DATABASE}.sql.gz"
    if mysqldump -h "$DB_HOST" -P "${DB_PORT:-3306}" -u "$DB_USERNAME" -p"$DB_PASSWORD" \
        --single-transaction --quick --lock-tables=false "$DB_DATABASE" 2>/dev/null | gzip > "$BACKUP_FILE"; then
        echo "💾 Backup written: $BACKUP_FILE"
    else
        echo "⚠️  mysqldump failed — proceeding without backup. Check DB credentials in .env."
        rm -f "$BACKUP_FILE"
    fi
fi

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Run database migrations
php artisan migrate --force

# Frontend build — only when --build-frontend is passed
if $BUILD_FRONTEND; then
    echo "🏗  Regenerating Ziggy routes + rebuilding frontend assets…"
    php artisan ziggy:generate
    npm install --prefer-offline
    npm run build
    echo "✅ Frontend built."
else
    echo "ℹ️  Skipping frontend build (pass --build-frontend to rebuild assets)."
fi

# Clear and cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers (if using supervisor/systemd)
php artisan queue:restart

# REMINDER: Ensure the cron job is set up on the server:
# * * * * * cd /home/cloudpanel-user/htdocs/your-domain.com && php artisan schedule:run >> /dev/null 2>&1
#
# REMINDER: Ensure a queue worker is running to process background broadcasts/notifications:
# php artisan queue:work --queue=default,notifications --tries=1
# (Recommended: Use Supervisor to manage the queue worker process)

# Exit maintenance mode
php artisan up

echo "✅ Deployment Successful!"
