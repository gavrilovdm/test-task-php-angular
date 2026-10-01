#!/bin/sh
set -e

# Run DB migrations once on application start (app container only).
if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php bin/console migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"
