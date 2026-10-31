# Ahjo proxy v3.
#
# Build from the repository root:
#   docker build -f docker/openshift/ahjo-proxy/Dockerfile .
ARG DRUPAL_DOCKER_TAG=8.5
FROM ghcr.io/city-of-helsinki/drupal-docker-base:${DRUPAL_DOCKER_TAG:-8.5}

# APP_ENV must be "prod". Other platform values (like "production") are
# not valid Symfony environments.
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    TOKEN_STORAGE_DIR=/var/www/html/var/storage

COPY ahjo-proxy/ /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-progress --prefer-dist --no-interaction --no-dev --optimize-autoloader \
 && bin/console cache:warmup \
 # OpenShift runs containers with a random UID in the root group.
 && mkdir -p var/storage \
 && chgrp -R 0 var \
 && chmod -R g=u var
