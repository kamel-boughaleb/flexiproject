#!/bin/bash
set -e

install_dependencies() {
    echo "Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader

    local npm_lock_hash
    npm_lock_hash=$(sha256sum package-lock.json | cut -d' ' -f1)
    if [ "$(cat node_modules/.package-lock.sha256 2>/dev/null)" != "$npm_lock_hash" ]; then
        echo "Installing npm dependencies..."
        npm ci
        echo "$npm_lock_hash" > node_modules/.package-lock.sha256
    fi

    echo "Building frontend assets..."
    npm run build
}

# The prod image ships vendor/ and public/build; the dev image mounts the
# working tree over /app and has to produce them itself.
if [ "${INSTALL_DEPENDENCIES_ON_START:-0}" = 1 ]; then
    install_dependencies
fi

if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate
fi

echo "Waiting for database..."
until php artisan db:show >/dev/null 2>&1; do
    echo "Database is unavailable - sleeping"
    sleep 2
done
echo "Database is up"

# Has to be decided before migrating: the first migrate creates the migrations
# table, after which the database no longer looks fresh.
db_was_empty=0
if ! php artisan migrate:status >/dev/null 2>&1; then
    db_was_empty=1
fi

php artisan migrate --force

# DemoSeeder creates accounts with the password "password".
if [ "$db_was_empty" = 1 ] && [ "$APP_ENV" = local ]; then
    echo "Fresh database - seeding demo data..."
    php artisan db:seed --force || echo "WARNING: db:seed failed" >&2
fi

echo "Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting services (nginx + php-fpm + scheduler via supervisord)..."
exec "$@"
