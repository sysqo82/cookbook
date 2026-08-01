#!/bin/sh
set -e

# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
  set -- php-fpm "$@"
fi

if [ "$1" = 'php-fpm' ] || [ "$1" = 'php' ] || [ "$1" = 'bin/console' ]; then
    composer install --prefer-dist --no-progress -o --no-interaction --ignore-platform-reqs

#    ./bin/console assets:install
    echo "Waiting for db to be ready..."
      until ./bin/console doctrine:database:create --if-not-exists > /dev/null 2>&1; do
        sleep 1
      done
        ./bin/console doctrine:migrations:migrate --no-interaction || echo "Warning: failed to run schema migration"
fi

# Ensure upload directories exist and are writable by php-fpm user
UPLOAD_ROOT="public/uploads"
UPLOAD_IMAGES_DIR="${UPLOAD_ROOT}/images"

mkdir -p "$UPLOAD_IMAGES_DIR"

# Fix ownership/permissions on every boot so edit forms can write uploads
chown -R www-data:www-data "$UPLOAD_ROOT"
find "$UPLOAD_ROOT" -type d -exec chmod 775 {} \;
find "$UPLOAD_ROOT" -type f -exec chmod 664 {} \;

# ponytail: worker containers skip nginx; CONTAINER_ROLE=worker to opt out
if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    service nginx start
fi

chmod -R 777 var

exec docker-php-entrypoint "$@"
