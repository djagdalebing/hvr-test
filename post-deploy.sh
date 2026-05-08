#!/usr/bin/env bash
# Run this ONCE on the Hostinger server after extracting the tarball.
# It clears caches, links storage, and verifies the site is bootable.
set -e

cd "$(dirname "$0")"

echo "=> Setting permissions on storage and bootstrap/cache..."
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

echo "=> Clearing caches..."
php artisan config:clear || true
php artisan view:clear   || true
php artisan route:clear  || true
php artisan cache:clear  || true

echo "=> Linking public storage..."
php artisan storage:link || true

echo "=> Smoke-testing artisan boot..."
php artisan --version

echo ""
echo "✓ Post-deploy steps complete."
echo "  Now visit your dummy domain in a browser to test."
