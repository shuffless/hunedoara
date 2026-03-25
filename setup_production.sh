#!/bin/bash
#
# Patient Hub - Production Setup Script (CloudPanel)
# Document root: /home/api/htdocs/hunedoara.api/
# Database already configured separately.
#
# Run as root: sudo bash setup_production.sh
#

set -e

APP_DIR="/home/api/htdocs/hunedoara.api"
SITE_USER="api"
SITE_GROUP="api"

echo "================================="
echo " Patient Hub - Production Setup"
echo " (CloudPanel)"
echo "================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run as root: sudo bash setup_production.sh"
    exit 1
fi

# Check that the app directory exists
if [ ! -d "$APP_DIR" ]; then
    echo "Error: $APP_DIR does not exist."
    echo "Make sure the site is created in CloudPanel first."
    exit 1
fi

# Prompt for database credentials
read -p "Enter database name [patient_hub]: " DB_NAME
DB_NAME=${DB_NAME:-patient_hub}

read -p "Enter database user [api]: " DB_USER
DB_USER=${DB_USER:-api}

read -sp "Enter database password: " DB_PASS
echo

# Step 1: Update config.php with database credentials
echo "[1/4] Updating config.php with database credentials..."
sed -i "s|define('DB_HOST', '.*');|define('DB_HOST', 'localhost');|" "$APP_DIR/config.php"
sed -i "s|define('DB_NAME', '.*');|define('DB_NAME', '$DB_NAME');|" "$APP_DIR/config.php"
sed -i "s|define('DB_USER', '.*');|define('DB_USER', '$DB_USER');|" "$APP_DIR/config.php"
sed -i "s|define('DB_PASS', '.*');|define('DB_PASS', '$DB_PASS');|" "$APP_DIR/config.php"
echo "   Config updated."

# Step 2: Set file permissions (CloudPanel uses site-specific user)
echo "[2/4] Setting file permissions..."
chown -R ${SITE_USER}:${SITE_GROUP} "$APP_DIR"
chmod -R 755 "$APP_DIR"
chmod 640 "$APP_DIR/config.php"
echo "   Permissions set (owner: ${SITE_USER}:${SITE_GROUP})."

# Step 3: Install systemd service for the listener
echo "[3/4] Installing listener service..."
cat > /etc/systemd/system/patient-hub-listener.service << EOF
[Unit]
Description=Patient Hub HL7/XML Listener
After=network.target mysql.service

[Service]
Type=simple
User=${SITE_USER}
Group=${SITE_GROUP}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/listener.php
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

# Step 4: Create log file with correct ownership
echo "[4/4] Setting up log file..."
touch /var/log/patient-hub-listener.log
chown ${SITE_USER}:${SITE_GROUP} /var/log/patient-hub-listener.log

echo ""
echo "================================="
echo " Setup Complete!"
echo "================================="
echo ""
echo " Web Interface: https://hunedoara.api/"
echo " Login:         manager / hunedoara"
echo ""
echo " Incoming data port: 5500 (TCP - HL7/XML)"
echo " HTTP API endpoint:  POST https://hunedoara.api/api/receive.php"
echo ""
echo " Destination:   192.168.20.80:6600 (configured in config.php)"
echo ""
echo " Listener logs: /var/log/patient-hub-listener.log"
echo " Service:       systemctl status patient-hub-listener"
echo ""
