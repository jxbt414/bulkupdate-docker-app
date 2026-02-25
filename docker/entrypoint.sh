#!/bin/sh

# Fix storage and cache permissions (bind mount overrides Dockerfile permissions)
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate .env and app key if missing
if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

if grep -q "^APP_KEY=$" /var/www/html/.env; then
    php artisan key:generate
fi

exec "$@"
