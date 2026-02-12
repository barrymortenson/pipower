#!/bin/bash
#
# PiPower Installation Script
# Run this on a fresh Raspberry Pi OS installation
#
set -e

INSTALL_DIR="/var/www/pipower"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== PiPower Installation ==="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root (sudo ./install.sh)"
    exit 1
fi

# Set hostname
echo "Setting hostname to 'power-setup'..."
hostnamectl set-hostname power-setup
sed -i 's/127\.0\.1\.1.*/127.0.1.1\tpower-setup/' /etc/hosts

# Prevent cloud-init from resetting hostname on boot
echo "Configuring cloud-init to preserve hostname..."
if [ -f /etc/cloud/cloud.cfg ]; then
    sed -i 's/preserve_hostname: false/preserve_hostname: true/' /etc/cloud/cloud.cfg
fi
# Clear any cached hostname from cloud-init
rm -f /var/lib/cloud/data/set-hostname

# Update system
echo "Updating system packages..."
apt-get update
apt-get upgrade -y

# Install dependencies
echo "Installing dependencies..."
apt-get install -y \
    nginx \
    php-fpm \
    php-sqlite3 \
    php-mbstring \
    php-xml \
    php-curl \
    nut \
    nut-client \
    git \
    unzip

# Add shell aliases
echo "Adding shell aliases..."
cat >> /home/pi/.bashrc << 'ALIASES'

# PiPower aliases
alias pa='sudo -u www-data php /var/www/pipower/artisan'
alias shutdown='sudo shutdown -h now'
alias reboot='sudo reboot'
ALIASES

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

# Create install directory
echo "Setting up application directory..."
mkdir -p "$INSTALL_DIR"

# Copy application files (or clone from git)
if [ -d "$SCRIPT_DIR/app" ]; then
    echo "Copying application files..."
    cp -r "$SCRIPT_DIR/." "$INSTALL_DIR/"
    rm -rf "$INSTALL_DIR/.git"
    rm -f "$INSTALL_DIR/install.sh"
    rm -f "$INSTALL_DIR/provisioning/"*.service
else
    echo "Application files not found. Please copy the PiPower source to $INSTALL_DIR"
    exit 1
fi

# Set ownership
chown -R www-data:www-data "$INSTALL_DIR"

# Install PHP dependencies
echo "Installing PHP dependencies..."
cd "$INSTALL_DIR"
sudo -u www-data composer install --no-dev --optimize-autoloader

# Create database
echo "Setting up database..."
sudo -u www-data touch "$INSTALL_DIR/database/database.sqlite"
sudo -u www-data php "$INSTALL_DIR/artisan" migrate --force

# Create psysh config directory for tinker
mkdir -p /var/www/.config/psysh
chown -R www-data:www-data /var/www/.config

# Build frontend assets (if node_modules exists in source)
if [ -f "$INSTALL_DIR/package.json" ]; then
    echo "Building frontend assets..."
    if ! command -v npm &> /dev/null; then
        curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
        apt-get install -y nodejs
    fi
    cd "$INSTALL_DIR"
    npm install --production=false
    npm run build
    rm -rf node_modules
    chown -R www-data:www-data "$INSTALL_DIR/public/build"
fi

# Set up environment
echo "Configuring environment..."
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    chown www-data:www-data "$INSTALL_DIR/.env"
fi

# Ensure APP_KEY is set
if ! grep -q "^APP_KEY=base64:" "$INSTALL_DIR/.env"; then
    sudo -u www-data php "$INSTALL_DIR/artisan" key:generate
fi

# Configure for production
sed -i 's/APP_ENV=local/APP_ENV=production/' "$INSTALL_DIR/.env"
sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' "$INSTALL_DIR/.env"
sed -i 's/SESSION_DRIVER=database/SESSION_DRIVER=file/' "$INSTALL_DIR/.env"
# Note: Configure MAIL_PASSWORD, PLIVO_AUTH_ID, PLIVO_AUTH_TOKEN, and
# PLIVO_FROM_NUMBER in .env after installation for email/SMS notifications

# Configure nginx
echo "Configuring nginx..."
PHP_FPM_SOCK=$(find /run/php -name "php*-fpm.sock" 2>/dev/null | head -1)
if [ -z "$PHP_FPM_SOCK" ]; then
    PHP_FPM_SOCK="/run/php/php-fpm.sock"
fi

cat > /etc/nginx/sites-available/pipower << NGINXCONF
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    root $INSTALL_DIR/public;
    index index.php index.html;

    server_name _;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:$PHP_FPM_SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINXCONF

rm -f /etc/nginx/sites-enabled/default
ln -sf /etc/nginx/sites-available/pipower /etc/nginx/sites-enabled/
nginx -t && systemctl restart nginx

# Install scheduler service
echo "Installing scheduler service..."
cp "$SCRIPT_DIR/provisioning/pipower-scheduler.service" /etc/systemd/system/
systemctl daemon-reload

# Configure NUT
echo "Configuring NUT..."
if [ ! -f /etc/nut/ups.conf.bak ]; then
    cp /etc/nut/ups.conf /etc/nut/ups.conf.bak
fi

cat > /etc/nut/ups.conf << 'NUTCONF'
# PiPower NUT Configuration
[ups]
    driver = usbhid-ups
    port = auto
    desc = "Main UPS"
NUTCONF

cat > /etc/nut/nut.conf << 'NUTMODE'
MODE=standalone
NUTMODE

# Add www-data to nut group for upsc access
usermod -a -G nut www-data

# Configure permissions for software updates feature
echo "Configuring update permissions..."
# Allow www-data to traverse /home/pi
chmod o+x /home/pi
# Allow www-data to read the source repo for update checks
chmod -R o+rX /home/pi/PiPower
# Allow www-data to write git config
chown www-data:www-data /var/www
# Add safe.directory for www-data to read pi's git repo
sudo -u www-data git config --global --add safe.directory /home/pi/PiPower
# Allow www-data to run update.sh, git fetch, and system commands via sudo without password
cat > /etc/sudoers.d/pipower << 'SUDOERS'
Defaults:www-data env_keep += "XDG_RUNTIME_DIR"
www-data ALL=(ALL) NOPASSWD: /home/pi/PiPower/update.sh
www-data ALL=(pi) NOPASSWD: /usr/bin/git
www-data ALL=(ALL) NOPASSWD: /sbin/reboot
www-data ALL=(ALL) NOPASSWD: /sbin/shutdown
www-data ALL=(ALL) NOPASSWD: /usr/bin/hostnamectl *
www-data ALL=(ALL) NOPASSWD: /usr/bin/hostnamectl
www-data ALL=(ALL) NOPASSWD: /bin/hostname *
www-data ALL=(ALL) NOPASSWD: /bin/hostname
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/ethernet-static.nmconnection /etc/NetworkManager/system-connections/ethernet-static.nmconnection
www-data ALL=(ALL) NOPASSWD: /usr/bin/chmod 600 /etc/NetworkManager/system-connections/ethernet-static.nmconnection
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmcli connection reload
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/hosts /etc/hosts
www-data ALL=(ALL) NOPASSWD: /bin/cp /tmp/hostname /etc/hostname
www-data ALL=(ALL) NOPASSWD: /bin/sed -i * /boot/firmware/cmdline.txt
www-data ALL=(ALL) NOPASSWD: /usr/bin/apt-get update
www-data ALL=(ALL) NOPASSWD: /usr/bin/apt-get install -y rpi-connect-lite
www-data ALL=(ALL) NOPASSWD: /usr/bin/loginctl enable-linger pi
www-data ALL=(pi) NOPASSWD:SETENV: /usr/bin/rpi-connect signin *
www-data ALL=(pi) NOPASSWD:SETENV: /usr/bin/rpi-connect signout
www-data ALL=(pi) NOPASSWD:SETENV: /usr/bin/rpi-connect status
www-data ALL=(pi) NOPASSWD:SETENV: /usr/bin/rpi-connect on
SUDOERS
chmod 440 /etc/sudoers.d/pipower
rm -f /etc/sudoers.d/pipower-update 2>/dev/null || true

# Set up USB permissions for NUT
echo "Configuring USB permissions..."
cat > /etc/udev/rules.d/99-nut-ups.rules << 'UDEVRULE'
# Allow NUT to access USB UPS devices
SUBSYSTEM=="usb", ATTR{idVendor}=="075d", MODE="0660", GROUP="nut"
SUBSYSTEM=="usb", DRIVER=="usbhid", MODE="0660", GROUP="nut"
UDEVRULE
udevadm control --reload-rules
udevadm trigger

# Enable and start NUT services
systemctl enable nut-server nut-client
systemctl restart nut-driver-enumerator nut-server

# Start application services
systemctl enable pipower-scheduler
systemctl start pipower-scheduler

# Verify installation
echo ""
echo "Verifying installation..."
sleep 2

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Web server is running"
else
    echo "✗ Web server check failed (HTTP $HTTP_CODE)"
    exit 1
fi

UPS_STATUS=$(upsc ups 2>&1)
if echo "$UPS_STATUS" | grep -q "battery.charge"; then
    echo "✓ UPS is connected"
else
    echo "✗ UPS not detected: $UPS_STATUS"
fi

# Clean up setup script from boot partition
rm -f /boot/firmware/pipower-setup.sh

# Remove any SSH keys to prevent credentials from leaking into distributable images
rm -rf /home/pi/.ssh/github_deploy /home/pi/.ssh/github_deploy.pub
sed -i '/github.com/,/IdentityFile/d' /home/pi/.ssh/config 2>/dev/null || true

# Clean up system for image creation
echo ""
echo "Cleaning up system..."
apt-get autoremove -y > /dev/null 2>&1
apt-get clean > /dev/null 2>&1
rm -rf /var/cache/apt/archives/*.deb
rm -rf /var/lib/apt/lists/*
rm -rf /tmp/*
rm -rf /var/tmp/*
journalctl --vacuum-time=1d > /dev/null 2>&1
rm -rf /var/log/*.gz /var/log/*.1 /var/log/*.old
cat /dev/null > /var/log/syslog 2>/dev/null || true
cat /dev/null > /var/log/messages 2>/dev/null || true

# Zero free space for better compression (optional but helps a lot)
echo -n "Zeroing free space for better compression (20-30 minutes)... "
dd if=/dev/zero of=/zero.fill bs=1M 2>/dev/null &
DD_PID=$!
SPINNER='|/-\'
while kill -0 $DD_PID 2>/dev/null; do
    for i in $(seq 0 3); do
        echo -ne "\b${SPINNER:$i:1}"
        sleep 0.2
    done
done
echo -e "\bdone"
rm -f /zero.fill

echo ""
echo "=== Installation Complete ==="
echo ""
CURRENT_IP=$(hostname -I | awk '{print $1}')
echo "Web interface: http://$CURRENT_IP"
