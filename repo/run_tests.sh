#!/bin/bash
# This script runs ALL tests inside Docker containers.
# Usage: ./run_tests.sh (from the fullstack/ directory)
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

echo "=== Running PHP Unit Tests ==="
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 backend php bin/phpunit --testsuite unit

echo "=== Running Frontend Unit Tests ==="
docker compose exec -T frontend-test npx jest --ci

echo "=== Running API Integration Tests ==="
docker compose exec -T -e APP_ENV=test -e APP_DEBUG=1 backend php bin/phpunit --testsuite api

echo "=== ALL TESTS PASSED ==="
