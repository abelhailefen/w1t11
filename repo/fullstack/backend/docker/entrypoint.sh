#!/bin/sh
set -e

/var/www/html/docker/wait-for-db.sh

# Generate JWT keypair if not present
if [ ! -f /var/www/html/config/jwt/private.pem ]; then
  mkdir -p /var/www/html/config/jwt
  openssl genpkey -out /var/www/html/config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096 -pass pass:"${JWT_PASSPHRASE}"
  openssl pkey -in /var/www/html/config/jwt/private.pem -out /var/www/html/config/jwt/public.pem -pubout -passin pass:"${JWT_PASSPHRASE}"
  echo "JWT keypair generated."
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed:initial --no-interaction
php bin/console app:migrate:fix-license-encryption --no-interaction

chown -R www-data:www-data /var/www/html/var
mkdir -p "${CREDENTIAL_UPLOAD_DIR:-/var/app/uploads/credentials}"
chown -R www-data:www-data /var/app/uploads

exec "$@"
