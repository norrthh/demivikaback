#!/bin/bash

# Wait for database
echo "Waiting for mysql_user..."
while ! nc -z mysql_user 3306; do
  sleep 1
done

# Install dependencies
composer install --no-interaction --optimize-autoloader --no-dev

# Create directories and set permissions (ВАЖНО!)
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper permissions BEFORE Laravel starts
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Laravel setup
if [ ! -f .env ]; then
    cp .env.example .env
fi

php artisan key:generate --no-interaction || true
php artisan config:clear || true
php artisan view:clear || true
php artisan migrate

# Start PHP-FPM
exec php-fpm
