ARG BASE_IMAGE=dunglas/frankenphp:1-php8.5-alpine@sha256:2e95565f69e5295e1a0a5ceb5dc81b6039c9e552e819da47f8c313f466926a83
FROM ${BASE_IMAGE} AS base

RUN php -r 'exit(version_compare(PHP_VERSION, "8.5.12", ">=") ? 0 : 1);'

RUN apk upgrade --no-cache curl libcurl \
    && apk add --no-cache git \
    && install-php-extensions pdo_pgsql pcntl sockets

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY docker/php/10-bakery.ini docker/php/20-bakery-opcache.ini /usr/local/etc/php/conf.d/

WORKDIR /var/www/app

COPY --chown=www-data:www-data . .

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown www-data:www-data /var/www/app \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

EXPOSE 8000
HEALTHCHECK --interval=10s --timeout=5s --start-period=30s --retries=3 CMD wget -qO- http://127.0.0.1:8000/up || exit 1
CMD ["php", "artisan", "octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8000"]

FROM base AS dev
RUN composer install --prefer-dist --no-progress --optimize-autoloader
USER root
RUN git config --system --add safe.directory /var/www/app
USER www-data
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM base AS prod
RUN composer install --no-dev --prefer-dist --no-progress \
    --optimize-autoloader --classmap-authoritative
