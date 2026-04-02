#!/bin/sh
set -e

/var/www/html/docker/wait-for-db.sh

if [ ! -f /var/www/html/config/jwt/private.pem ] || [ ! -f /var/www/html/config/jwt/public.pem ]; then
  mkdir -p /var/www/html/config/jwt
  openssl genrsa -out /var/www/html/config/jwt/private.pem -aes256 -passout pass:"${JWT_PASSPHRASE}" 4096
  openssl rsa -pubout -in /var/www/html/config/jwt/private.pem -passin pass:"${JWT_PASSPHRASE}" -out /var/www/html/config/jwt/public.pem
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed:initial --no-interaction

exec "$@"
