#!/bin/sh
set -e

# Caches are built at start-up (not at image build time) because they depend on
# the runtime environment variables.
php artisan optimize --no-interaction

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

exec "$@"
