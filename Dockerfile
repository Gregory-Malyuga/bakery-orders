ARG BASE_IMAGE=ghcr.io/gregory-malyuga/bootstrap-docker-php:latest
FROM ${BASE_IMAGE} AS base

ENV APP_BASE_DIR=/var/www/app AUTORUN_ENABLED=false

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
USER root
RUN git config --system --add safe.directory /var/www/app
USER www-data
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

FROM base AS prod
RUN composer install --no-dev --prefer-dist --no-progress \
    --optimize-autoloader --classmap-authoritative
