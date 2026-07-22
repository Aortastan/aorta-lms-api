#!/bin/sh
set -e

echo "Waiting for database..."
sleep 3

echo "Running optimize clear..."
php artisan optimize:clear || true

echo "Running optimize..."
php artisan optimize || true

echo "Starting PHP-FPM..."
exec php-fpm