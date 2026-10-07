ARG BASE_IMAGE=ghcr.io/gregory-malyuga/bootstrap-docker-php:latest
FROM ${BASE_IMAGE} AS base

WORKDIR /var/www/app

COPY --chown=www-data:www-data . .

RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/testing storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

USER www-data

FROM base AS dev
RUN composer install --prefer-dist --no-progress --optimize-autoloader

FROM base AS prod
RUN composer install --no-dev --prefer-dist --no-progress \
    --optimize-autoloader --classmap-authoritative
