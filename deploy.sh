#!/bin/bash
set -e

echo "🚀 Starting Deployment v1.0.0..."

# Enter maintenance mode
php artisan down || true

# Update codebase (comment out if not using git)
# git pull origin main

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Run Database Migrations
php artisan migrate --force

# Install Node dependencies and build assets
# Note: On smaller servers, run this locally and upload the 'public/build' directory instead.
# npm install
# npm run build

# Clear and Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart Queue Workers (if using supervisor/systemd)
php artisan queue:restart

# REMINDER: Ensure the cron job is set up on the server:
# * * * * * cd /home/cloudpanel-user/htdocs/your-domain.com && php artisan schedule:run >> /dev/null 2>&1
#
# NEW: Ensure a queue worker is running to process background broadcasts/notifications:
# php artisan queue:work --queue=default,notifications --tries=1
# (Recommended: Use Supervisor to manage the queue worker process)

# Exit maintenance mode
php artisan up

echo "✅ Deployment Successful!"

