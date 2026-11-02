#!/bin/bash
set -e

# Values docker-compose injects. .env.example ships DB_HOST=127.0.0.1, which
# inside the container is the container itself, not the db service.
SEEDED_KEYS="APP_ENV APP_DEBUG APP_URL DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD"

set_env_key() {
    local file="$1" key="$2" value="$3" escaped

    # Escape what sed treats specially in a replacement: backslash, ampersand
    # (means "the whole match") and the | delimiter.
    escaped=$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g')

    if grep -qE "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$file"
    else
        printf '%s=%s\n' "$key" "$value" >> "$file"
    fi
}

seed_dotenv() {
    local key value

    if [ -f .env ]; then
        return 0
    fi

    echo "Seeding .env from .env.example..."
    cp .env.example .env

    for key in $SEEDED_KEYS; do
        value="${!key}"
        if [ -n "$value" ]; then
            set_env_key .env "$key" "$value"
        fi
    done
}

seed_dotenv

# Dependencies come first: every step below shells out to artisan, which cannot
# run at all without vendor/autoload.php.
echo "Installing composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

npm_lock_hash=$(sha256sum package-lock.json | cut -d' ' -f1)
if [ "$(cat node_modules/.package-lock.sha256 2>/dev/null)" != "$npm_lock_hash" ]; then
    echo "Installing npm dependencies..."
    npm ci
    echo "$npm_lock_hash" > node_modules/.package-lock.sha256
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

echo "Building frontend assets..."
npm run build

echo "Starting services (nginx + php-fpm + scheduler via supervisord)..."
exec "$@"
