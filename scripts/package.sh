#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="${ROOT_DIR}/build"
STAGING_DIR="${BUILD_DIR}/newera"
ZIP_FILE="${BUILD_DIR}/newera.zip"

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required to build the plugin package." >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm is required to build frontend assets." >&2
  exit 1
fi

echo "==> Preparing build workspace..."
rm -rf "${STAGING_DIR}" "${ZIP_FILE}"
mkdir -p "${STAGING_DIR}"

echo "==> Copying plugin sources..."
rsync -a "${ROOT_DIR}/" "${STAGING_DIR}/" \
  --exclude ".git" \
  --exclude ".github" \
  --exclude "build" \
  --exclude "scripts" \
  --exclude "node_modules" \
  --exclude "vendor" \
  --exclude "tests" \
  --exclude "coverage" \
  --exclude ".env" \
  --exclude "*.zip" \
  --exclude "*.tar.gz" \
  --exclude ".DS_Store" \
  --exclude "docker-compose.yml" \
  --exclude "docker-entrypoint.sh"

pushd "${STAGING_DIR}" >/dev/null

echo "==> Installing PHP dependencies..."
if [ -n "${COMPOSER_TOKEN:-}" ]; then
  export COMPOSER_AUTH="{\"github-oauth\":{\"github.com\":\"${COMPOSER_TOKEN}\"}}"
fi
if [ "$(id -u)" -eq 0 ]; then
  echo "Running as root; enabling COMPOSER_ALLOW_SUPERUSER for Composer."
  export COMPOSER_ALLOW_SUPERUSER=1
fi
composer install --no-dev --optimize-autoloader --no-interaction --no-progress

echo "==> Installing Node dependencies and building assets..."
npm install --no-progress --no-fund
npm run build

if [ ! -d "assets/js" ] || [ ! -d "assets/css" ] || \
   [ -z "$(find assets/js -maxdepth 1 -name '*.js' -print -quit)" ] || \
   [ -z "$(find assets/css -maxdepth 1 -name '*.css' -print -quit)" ]; then
  echo "Asset build failed; expected JS/CSS outputs under assets/." >&2
  exit 1
fi

rm -rf node_modules package-lock.json

popd >/dev/null

echo "==> Creating plugin archive..."
(
  cd "${BUILD_DIR}"
  zip -rq "$(basename "${ZIP_FILE}")" "newera"
)

echo "✅ Package created at ${ZIP_FILE}"
