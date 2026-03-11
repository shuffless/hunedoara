#!/bin/bash
#
# Patient Hub Setup Script
# Run as root: sudo bash setup.sh
#

set -e

echo "================================="
echo " Patient Hub - Setup"
echo "================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo bash setup.sh"
    exit 1
fi

# Prompt for MySQL root password
read -sp "Enter MySQL root password (leave empty if none): " MYSQL_ROOT_PASS
echo

# Generate bcrypt hash for the default password
BCRYPT_HASH=$(php -r "echo password_hash('hunedoara', PASSWORD_BCRYPT);")

# Create the database and tables
echo "[1/5] Setting up database..."
if [ -z "$MYSQL_ROOT_PASS" ]; then
    MYSQL_CMD="mysql -u root"
else
    MYSQL_CMD="mysql -u root -p$MYSQL_ROOT_PASS"
fi

# Replace placeholder hash with actual hash in SQL
sed "s|\\\$2y\\\$12\\\$MOF/rw4.yAso5cVh/iALMuEjgT2ynQVBlm.b7FuQNSNJFgw6kpg9S|$BCRYPT_HASH|g" db.sql | $MYSQL_CMD

echo "   Database 'patient_hub' created."

# Update config with MySQL password if provided
if [ -n "$MYSQL_ROOT_PASS" ]; then
    echo "[2/5] Updating config.php with database password..."
    sed -i "s|define('DB_PASS', '');|define('DB_PASS', '$MYSQL_ROOT_PASS');|" config.php
else
    echo "[2/5] Config.php using empty password (default)."
fi

# Set permissions
echo "[3/5] Setting file permissions..."
chown -R www-data:www-data /var/www/html/patient-hub
chmod -R 755 /var/www/html/patient-hub
chmod 640 config.php

# Install systemd service for the listener
echo "[4/5] Installing listener service..."
cat > /etc/systemd/system/patient-hub-listener.service << 'EOF'
[Unit]
Description=Patient Hub HL7/XML Listener
After=network.target mysql.service

[Service]
Type=simple
User=www-data
ExecStart=/usr/bin/php /var/www/html/patient-hub/listener.php
Restart=always
RestartSec=5
StandardOutput=append:/var/log/patient-hub-listener.log
StandardError=append:/var/log/patient-hub-listener.log

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable patient-hub-listener
systemctl start patient-hub-listener

echo "   Listener service installed and started on port 5500."

# Enable Apache mod_rewrite if not already
echo "[5/5] Checking Apache configuration..."
a2enmod rewrite 2>/dev/null || true
systemctl reload apache2 2>/dev/null || true

echo ""
echo "================================="
echo " Setup Complete!"
echo "================================="
echo ""
echo " Web Interface: http://your-server/patient-hub/"
echo " Login:         manager / hunedoara"
echo ""
echo " Incoming data port: 5500 (TCP - HL7/XML)"
echo " HTTP API endpoint:  POST http://your-server/patient-hub/api/receive.php"
echo ""
echo " Destination:   192.168.20.80:6600 (configured in config.php)"
echo ""
echo " Listener logs: /var/log/patient-hub-listener.log"
echo " Service:       systemctl status patient-hub-listener"
echo ""
