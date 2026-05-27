#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

usage() {
  cat <<'EOF'
Usage: ./deploy.sh [--migrate] [--storage-link] [--no-cache] [--help]

Options:
  --migrate        Run database migrations with --force.
  --storage-link   Create the storage symlink.
  --no-cache       Skip Laravel cache building.
  --help           Show this help message.
EOF
}

MIGRATE=false
STORAGE_LINK=false
NO_CACHE=false

while [[ $# -gt 0 ]]; do
  case "$1" in
    --migrate)
      MIGRATE=true
      shift
      ;;
    --storage-link)
      STORAGE_LINK=true
      shift
      ;;
    --no-cache)
      NO_CACHE=true
      shift
      ;;
    --help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      usage
      exit 1
      ;;
  esac
done

if [[ ! -f .env ]]; then
  echo "Error: .env file not found in $SCRIPT_DIR"
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "Error: php is not installed or not on PATH"
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "Error: composer is not installed or not on PATH"
  exit 1
fi

echo "Deploying Laravel backend from: $SCRIPT_DIR"

echo "Installing PHP dependencies..."
composer install --optimize-autoloader --no-dev --no-interaction

echo "Checking APP_KEY..."
if ! grep -q '^APP_KEY=' .env; then
  echo "APP_KEY missing. Generating new key..."
  php artisan key:generate --force
elif grep -q '^APP_KEY=$' .env; then
  echo "APP_KEY blank. Generating new key..."
  php artisan key:generate --force
else
  echo "APP_KEY already set."
fi

if [[ "$NO_CACHE" == false ]]; then
  echo "Clearing and building caches..."
  php artisan config:clear --no-interaction
  php artisan route:clear --no-interaction
  php artisan view:clear --no-interaction
  php artisan config:cache --no-interaction
  php artisan route:cache --no-interaction
  php artisan view:cache --no-interaction
else
  echo "Skipping cache build (--no-cache specified)."
fi

if [[ "$MIGRATE" == true ]]; then
  echo "Running database migrations..."
  php artisan migrate --force --no-interaction
fi

if [[ "$STORAGE_LINK" == true ]]; then
  echo "Creating storage symlink..."
  php artisan storage:link --force
fi

echo "Restarting queue workers..."
php artisan queue:restart --no-interaction

echo "Deployment script finished successfully."
