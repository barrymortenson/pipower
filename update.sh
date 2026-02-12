#!/bin/bash
#
# PiPower Update Script
# Pulls latest code and applies updates
#
set -e

INSTALL_DIR="/var/www/pipower"
REPO_DIR="/home/pi/pipower"
LOCK_FILE="/tmp/pipower-updating.lock"

# Always remove lock file on exit (success or failure)
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

echo "=== PiPower Update ==="
echo ""

# Create lock file to indicate update is in progress
touch "$LOCK_FILE"

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (sudo ./update.sh)"
    exit 1
fi

# Pull latest code
echo "Pulling latest code..."
cd "$REPO_DIR"
sudo -u pi git fetch origin
sudo -u pi git pull origin main

# Copy updated files
echo "Copying application files..."
rsync -a --delete \
    --exclude '.env' \
    --exclude '.git' \
    --exclude 'install.sh' \
    --exclude 'storage/logs/*' \
    --exclude 'storage/framework/cache/*' \
    --exclude 'storage/framework/sessions/*' \
    --exclude 'storage/framework/views/*' \
    --exclude 'database/database.sqlite' \
    "$REPO_DIR/" "$INSTALL_DIR/"

# Set ownership
chown -R www-data:www-data "$INSTALL_DIR"

# Install/update PHP dependencies
echo "Updating PHP dependencies..."
cd "$INSTALL_DIR"
sudo -u www-data composer install --no-dev --optimize-autoloader

# Run migrations
echo "Running migrations..."
sudo -u www-data php artisan migrate --force

# Rebuild frontend assets
if [ -f "$INSTALL_DIR/package.json" ]; then
    echo "Building frontend assets..."
    npm install --production=false
    npm run build
    rm -rf node_modules
    chown -R www-data:www-data "$INSTALL_DIR/public/build"
fi

# Clear caches
echo "Clearing caches..."
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear

# Restart services
echo "Restarting services..."
systemctl restart php*-fpm nginx pipower-scheduler

# Get current version (run as pi user to avoid git ownership issues)
VERSION=$(sudo -u pi git -C "$REPO_DIR" describe --tags --always 2>/dev/null || sudo -u pi git -C "$REPO_DIR" rev-parse --short HEAD)

echo ""
echo "=== PiPower Update Complete ==="
echo "Version: $VERSION"
echo ""
