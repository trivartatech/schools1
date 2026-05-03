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

# Read .env values WITHOUT exporting them.
#
# We deliberately avoid `source .env`: that exports every variable into the
# shell environment, including any empty ones (e.g. APP_KEY="" on a fresh
# install). Dotenv's safeLoad() then refuses to override those non-empty-named
# but empty-valued vars from .env when subsequent `php artisan` commands boot,
# which causes `config:cache` to silently bake an empty APP_KEY into
# bootstrap/cache/config.php → 500s with "No application encryption key" in
# production. Pulling individual values via awk keeps them as plain shell
# variables (not env), so child PHP processes still read .env afresh.
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
DB_ROOT_USERNAME=$(env_get DB_ROOT_USERNAME)
DB_ROOT_PASSWORD=$(env_get DB_ROOT_PASSWORD)

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

# 2. Fix any root-owned files before running any artisan/composer commands
sudo -n chown -R "$(whoami):$(whoami)" . 2>/dev/null || true

# 3. PHP deps
echo "📦 composer install…"
composer install --no-dev --optimize-autoloader --no-interaction

# 3. CRITICAL: clear any stale config cache BEFORE key:generate.
#    key:generate's regex anchors on config('app.key') — if a stale cache
#    has the wrong (or empty) value, the regex won't match cleanly and the
#    new key gets prepended to the old line instead of replacing it. Also
#    ensures the seeder reads .env via config('school.*') fresh.
php artisan config:clear

# 4. APP_KEY (only if missing)
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "🔑 Generating APP_KEY…"
    php artisan key:generate --force
fi

# 5. Storage symlink
php artisan storage:link || true

# 6. Migrations
echo "🗄  Running migrations…"
php artisan migrate --force

# Give MySQL a moment to settle its prepared-statement cache after the DDL flood.
# Without this, some servers (CloudPanel/ProxySQL) throw error 1615 on the first
# seeder query. config:clear forces Laravel to open a fresh DB connection.
php artisan config:clear

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

# 11. Cron job — add to the site user's crontab (no sudo needed)
echo "⏰ Setting up cron job…"
CRON_CMD="* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
if crontab -l 2>/dev/null | grep -qF "artisan schedule:run"; then
    echo "  Cron already configured — skipping."
else
    (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
    echo "  ✅ Cron job added."
fi

# 12. Queue worker — systemd user service (no sudo needed), Supervisor fallback
echo "⚙️  Setting up queue worker…"
APP_DIR="$(pwd)"
APP_DOMAIN=$(env_get APP_URL | sed 's|https\?://||; s|/$||')
WORKER_NAME="schools-$(echo "$APP_DOMAIN" | tr '.' '-')-worker"
WORKER_SETUP=false

setup_systemd_worker() {
    # XDG_RUNTIME_DIR is required for systemctl --user in non-interactive SSH sessions
    export XDG_RUNTIME_DIR="/run/user/$(id -u)"
    local svc_dir="$HOME/.config/systemd/user"
    local svc_file="$svc_dir/${WORKER_NAME}.service"
    mkdir -p "$svc_dir"
    sed "s|__APP_PATH__|$APP_DIR|g; s|__WORKER_NAME__|$APP_DOMAIN|g" \
        worker.service.example > "$svc_file"
    systemctl --user daemon-reload          2>/dev/null &&
    systemctl --user enable  "$WORKER_NAME" 2>/dev/null &&
    systemctl --user start   "$WORKER_NAME" 2>/dev/null
}

if [ -f worker.service.example ]; then
    if setup_systemd_worker; then
        echo "  ✅ Worker started — systemd user service: $WORKER_NAME"
        WORKER_SETUP=true
        # Enable linger so the service survives server reboots (needs root once)
        if sudo -n loginctl enable-linger "$USER" 2>/dev/null; then
            echo "  ✅ Linger enabled — worker survives reboots automatically."
        else
            echo "  ⚠️  Run once as root to make the worker survive reboots:"
            echo "       sudo loginctl enable-linger $USER"
        fi
    fi
fi

# Supervisor fallback — used when systemd --user is unavailable
if ! $WORKER_SETUP && command -v supervisorctl &>/dev/null && [ -f supervisor.conf.example ]; then
    SUP_CONF="/etc/supervisor/conf.d/${WORKER_NAME}.conf"
    if sudo -n bash -c "
        sed 's|DOMAIN|${APP_DOMAIN}|g; s|USER|${USER}|g' \
            '${APP_DIR}/supervisor.conf.example' > '${SUP_CONF}' &&
        supervisorctl reread &&
        supervisorctl update &&
        supervisorctl start '${WORKER_NAME}'
    " 2>/dev/null; then
        echo "  ✅ Worker started — Supervisor: $WORKER_NAME"
        WORKER_SETUP=true
    fi
fi

# Neither worked — print clear manual instructions
if ! $WORKER_SETUP; then
    echo "  ⚠️  Worker could not be started automatically."
    echo "  Run ONE of the following after bootstrap:"
    echo ""
    echo "  Option A — systemd (recommended, no sudo):"
    echo "    export XDG_RUNTIME_DIR=/run/user/\$(id -u)"
    echo "    mkdir -p ~/.config/systemd/user"
    echo "    sed \"s|__APP_PATH__|$APP_DIR|g; s|__WORKER_NAME__|$APP_DOMAIN|g\" \\"
    echo "        worker.service.example > ~/.config/systemd/user/${WORKER_NAME}.service"
    echo "    systemctl --user daemon-reload"
    echo "    systemctl --user enable $WORKER_NAME"
    echo "    systemctl --user start  $WORKER_NAME"
    echo "    sudo loginctl enable-linger $USER"
    echo ""
    echo "  Option B — Supervisor (needs sudo):"
    echo "    sudo cp supervisor.conf.example /etc/supervisor/conf.d/${WORKER_NAME}.conf"
    echo "    sudo nano /etc/supervisor/conf.d/${WORKER_NAME}.conf"
    echo "    # Replace:  DOMAIN → $APP_DOMAIN   USER → $USER"
    echo "    sudo supervisorctl reread && sudo supervisorctl update"
    echo "    sudo supervisorctl start $WORKER_NAME"
fi

# 13. Mark bootstrap complete
date -u +"%Y-%m-%dT%H:%M:%SZ" > .bootstrap-done

# Pull display values from .env now (after seeds wrote any defaults back).
APP_URL=$(env_get APP_URL)
SUPER_ADMIN_EMAIL=$(env_get SUPER_ADMIN_EMAIL)
ADMIN_EMAIL=$(env_get ADMIN_EMAIL)
PRINCIPAL_EMAIL=$(env_get PRINCIPAL_EMAIL)
DEFAULT_PASSWORD=$(env_get DEFAULT_PASSWORD)

echo ""
echo "✅ Bootstrap complete!"
echo "   Login at:      ${APP_URL}/login"
echo "   Super admin:   ${SUPER_ADMIN_EMAIL}"
echo "   School admin:  ${ADMIN_EMAIL}"
echo "   Principal:     ${PRINCIPAL_EMAIL}"
echo "   Temp password: ${DEFAULT_PASSWORD}  ← change on first login!"
echo ""
if ! $WORKER_SETUP; then
    echo "⚠️  Queue worker needs manual setup — see instructions above."
fi
