# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# base — Apache + mod_php 8.5 with the extensions Laravel + PostgreSQL need
# ---------------------------------------------------------------------------
FROM php:8.5-apache AS base

# PHP extensions via the install helper (handles system deps automatically).
COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql pgsql intl bcmath mbstring zip gd opcache pcntl

# envsubst (gettext-base) for templating the vhost; psql client for wait-for-db.
RUN apt-get update \
 && apt-get install -y --no-install-recommends gettext-base postgresql-client \
 && rm -rf /var/lib/apt/lists/*

# Apache modules + drop the stock default site.
RUN a2enmod rewrite headers \
 && a2dissite 000-default

# Composer binary.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APP_DOMAIN=rylees.test
WORKDIR /var/www/api

COPY docker/api/apache-vhosts.conf.template /etc/apache2/vhosts.conf.template
COPY docker/api/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]

# ---------------------------------------------------------------------------
# frontend-build — compile the two Vue SPAs into build/ (console, history, assets)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY src/frontend/package*.json ./
RUN npm ci
COPY src/frontend/ ./
RUN npm run build

# ---------------------------------------------------------------------------
# dev — source + built frontend are bind-mounted at runtime (see compose.dev)
# ---------------------------------------------------------------------------
FROM base AS dev
ENV APP_ENV=local

# ---------------------------------------------------------------------------
# prod — app + built frontend baked in, no secrets, optimised autoloader
# ---------------------------------------------------------------------------
FROM base AS prod
ENV APP_ENV=production

COPY docker/api/php.prod.ini /usr/local/etc/php/conf.d/zz-rylees.ini

COPY src/api/ /var/www/api/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

COPY --from=frontend-build /app/build/ /var/www/frontend/

RUN chown -R www-data:www-data /var/www/api/storage /var/www/api/bootstrap/cache
