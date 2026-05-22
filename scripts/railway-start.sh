#!/usr/bin/env sh
set -e

# Run when the container starts (DATABASE_URL is available from Railway MySQL).
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec php -S "0.0.0.0:${PORT}" -t public
