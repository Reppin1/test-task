#!/usr/bin/env bash
set -e

cd /var/www/html

if [ ! -f .env ]; then
    echo "[entrypoint] .env не найден — копирую .env.example"
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    echo "[entrypoint] vendor/ пуст — composer install"
    composer install --no-interaction --prefer-dist --no-progress
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[entrypoint] генерирую APP_KEY"
    php artisan key:generate --force
fi

# Ждём MySQL: artisan migrate без живой БД просто упадёт.
if [ -n "${DB_HOST}" ]; then
    echo "[entrypoint] жду ${DB_HOST}:${DB_PORT:-3306}"
    until php -r 'exit(@fsockopen(getenv("DB_HOST"), (int) (getenv("DB_PORT") ?: 3306)) ? 0 : 1);'; do
        sleep 2
    done
fi

if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "[entrypoint] php artisan migrate --force"
    php artisan migrate --force
fi

if [ "${RUN_SEED}" = "true" ]; then
    # Сидеры идемпотентны (updateOrCreate) — безопасно гонять при каждом старте.
    echo "[entrypoint] php artisan db:seed --force"
    php artisan db:seed --force
fi

php artisan optimize:clear >/dev/null 2>&1 || true

echo "[entrypoint] запускаю: $*"
exec "$@"
