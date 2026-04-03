#!/bin/bash
# This script runs ALL tests inside Docker containers.
# Usage: ./run_tests.sh (from the repository root)
# Prerequisites: docker compose up -d must be running

set -e

if ! command -v docker >/dev/null 2>&1 || ! docker version >/dev/null 2>&1; then
  if command -v docker.exe >/dev/null 2>&1; then
    docker() { docker.exe "$@"; }
  else
    echo "docker command is required"
    exit 1
  fi
fi

echo "=== Waiting for backend schema readiness ==="
for i in $(seq 1 60); do
  if docker compose exec -T backend php -r '$dsn = getenv("DATABASE_URL") ?: ""; if ($dsn === "") { exit(1); } $parts = parse_url($dsn); parse_str($parts["query"] ?? "", $q); $db = isset($parts["path"]) ? ltrim($parts["path"], "/") : ""; if ($db === "") { exit(1); } $pdo = new PDO("mysql:host=" . ($parts["host"] ?? "mysql") . ";port=" . ($parts["port"] ?? 3306) . ";dbname=" . $db, $parts["user"] ?? "", $parts["pass"] ?? ""); $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = " . $pdo->quote($db) . " AND table_name = " . $pdo->quote("users")); exit(((int) $stmt->fetchColumn()) > 0 ? 0 : 1);' >/dev/null 2>&1; then
    echo "Backend schema is ready."
    break
  fi

  if [ "$i" -eq 60 ]; then
    echo "Backend schema did not become ready in time"
    exit 1
  fi

  sleep 2
done

echo "=== Ensuring database migrations and seed data ==="
docker compose exec -T backend php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -T backend php bin/console app:seed:initial --no-interaction
docker compose exec -T backend php bin/console app:migrate:fix-license-encryption --no-interaction

echo "=== Resetting auth state ==="
docker compose exec -T backend php -r '$dsn = getenv("DATABASE_URL"); $parts = parse_url($dsn); $db = ltrim($parts["path"] ?? "", "/"); $pdo = new PDO("mysql:host=" . ($parts["host"] ?? "mysql") . ";port=" . ($parts["port"] ?? 3306) . ";dbname=" . $db, $parts["user"] ?? "", $parts["pass"] ?? ""); $pdo->exec("DELETE FROM login_attempts"); $pdo->exec("DELETE FROM account_lockouts"); $pdo->exec("DELETE FROM captcha_challenges"); $updates = [["admin", "Admin@123", "ROLE_SYSTEM_ADMIN"], ["user", "User@123", "ROLE_USER"], ["content_admin", "Content@123", "ROLE_CONTENT_ADMIN"], ["reviewer", "Reviewer@123", "ROLE_CREDENTIAL_REVIEWER"], ["analyst", "Analyst@123", "ROLE_ANALYST"]]; $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, role = :role, status = :status, updated_at = NOW() WHERE username = :username"); foreach ($updates as $u) { $stmt->execute([":hash" => password_hash($u[1], PASSWORD_BCRYPT, ["cost" => 12]), ":role" => $u[2], ":status" => "ACTIVE", ":username" => $u[0]]); }'

echo "=== Running PHP Unit Tests ==="
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 backend php bin/phpunit --testsuite unit

echo "=== Running Frontend Unit Tests ==="
docker compose exec -T frontend-test npx jest --ci

echo "=== Resetting auth state for API tests ==="
docker compose exec -T backend php -r '$dsn = getenv("DATABASE_URL"); $parts = parse_url($dsn); $db = ltrim($parts["path"] ?? "", "/"); $pdo = new PDO("mysql:host=" . ($parts["host"] ?? "mysql") . ";port=" . ($parts["port"] ?? 3306) . ";dbname=" . $db, $parts["user"] ?? "", $parts["pass"] ?? ""); $pdo->exec("DELETE FROM login_attempts"); $pdo->exec("DELETE FROM account_lockouts"); $pdo->exec("DELETE FROM captcha_challenges"); $updates = [["admin", "Admin@123", "ROLE_SYSTEM_ADMIN"], ["user", "User@123", "ROLE_USER"], ["content_admin", "Content@123", "ROLE_CONTENT_ADMIN"], ["reviewer", "Reviewer@123", "ROLE_CREDENTIAL_REVIEWER"], ["analyst", "Analyst@123", "ROLE_ANALYST"]]; $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, role = :role, status = :status, updated_at = NOW() WHERE username = :username"); foreach ($updates as $u) { $stmt->execute([":hash" => password_hash($u[1], PASSWORD_BCRYPT, ["cost" => 12]), ":role" => $u[2], ":status" => "ACTIVE", ":username" => $u[0]]); }'

echo "=== Running API Integration Tests ==="
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 backend php bin/phpunit --testsuite api

echo "=== ALL TESTS PASSED ==="
