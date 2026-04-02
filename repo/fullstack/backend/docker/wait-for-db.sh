#!/bin/sh
set -e

host="${DB_HOST:-mysql}"
port="${DB_PORT:-3306}"
user="${DB_USER:-root}"
password="${DB_PASSWORD:-}"

echo "Waiting for MySQL at ${host}:${port}..."
until mysql -h"${host}" -P"${port}" -u"${user}" -p"${password}" -e "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done
echo "MySQL is ready."
