ARG BASE_IMAGE
FROM ${BASE_IMAGE} AS base

WORKDIR /var/www/app

COPY --chown=www-data:www-data . .

USER www-data

FROM base AS dev
RUN composer install --prefer-dist --no-progress --optimize-autoloader

FROM base AS prod
RUN composer install --no-dev --prefer-dist --no-progress \
    --optimize-autoloader --classmap-authoritative
