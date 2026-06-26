#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# Render the Apache vhost from the template (only ${APP_DOMAIN} is replaced).
# ---------------------------------------------------------------------------
envsubst '${APP_DOMAIN}' \
  < /etc/apache2/vhosts.conf.template \
  > /etc/apache2/sites-enabled/rylees.conf

cd /var/www/api

if [ "${APP_ENV}" = "production" ]; then
  # Prod: app + frontend are baked in; just (re)build the optimised caches.
  # Migrations/seeding are deliberately a separate deploy step against the
  # external PostgreSQL, not run on container start.
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
else
  # Dev: bootstrap the bind-mounted app against the bundled PostgreSQL.
  [ -f .env ] || cp .env.example .env

  if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
  fi

  echo "Waiting for PostgreSQL at ${DB_HOST:-postgres}:${DB_PORT:-5432} ..."
  until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-rylees}" >/dev/null 2>&1; do
    sleep 1
  done

  php artisan migrate --force
  php artisan db:seed --force
fi

exec "$@"
