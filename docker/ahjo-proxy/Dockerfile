ARG DRUPAL_DOCKER_TAG=8.5
FROM ghcr.io/city-of-helsinki/drupal-docker-base:${DRUPAL_DOCKER_TAG:-8.5}

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    APP_CACHE_DIR=/tmp/ahjo-proxy/cache \
    TOKEN_STORAGE_DIR=/tmp/ahjo-proxy/storage

COPY ahjo-proxy/ /var/www/html/
WORKDIR /var/www/html
RUN \
    composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader && \
    bin/console cache:warmup
