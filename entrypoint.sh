#!/bin/bash
set -e

cd /var/www

# Ensure permissions for storage & cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# Clear common caches (safe in dev; in prod you may skip or run optimize)
php artisan config:clear || true
php artisan cache:clear || true
php artisan view:clear || true
php artisan route:clear || true

# Rebuild config cache only if APP_ENV=production
if [ "$APP_ENV" = "production" ]; then
  php artisan config:cache || true
fi

# Create storage symlink if needed (ignore failure)
php artisan storage:link || true

# Finally hand off to the main container command (server, scheduler, supervisord, etc.)
exec "$@"
