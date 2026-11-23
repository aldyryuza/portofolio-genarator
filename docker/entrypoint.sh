#!/bin/sh
set -e

if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY is empty. Generate one and put it in .env:" >&2
    echo "  docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show" >&2
    exit 1
fi

# Caches are built at start-up (not at image build time) because they depend on
# the runtime environment variables.
php artisan optimize --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
