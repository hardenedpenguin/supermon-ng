#!/bin/bash

# Supermon-NG Version Check Script
# This script displays the current version and system information

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

APP_DIR="/var/www/html/supermon-ng"

echo -e "${BLUE}Supermon-NG Version Information${NC}"
echo "================================"

# Check if Supermon-NG is installed
if [ ! -d "$APP_DIR" ]; then
    echo -e "${YELLOW}Supermon-NG is not installed${NC}"
    exit 1
fi

# Get version information
if [ -f "$APP_DIR/includes/common.inc" ]; then
    VERSION=$(grep -o 'V4\.[0-9]\+\.[0-9]\+' "$APP_DIR/includes/common.inc" | head -1)
    VERSION_DATE=$(grep -o '"[^"]*"' "$APP_DIR/includes/common.inc" | grep -E '[A-Za-z]+ [0-9]+, [0-9]{4}' | head -1 | tr -d '"')
    
    echo -e "${GREEN}Version:${NC} $VERSION"
    echo -e "${GREEN}Date:${NC} $VERSION_DATE"
else
    echo -e "${YELLOW}Version information not available${NC}"
fi

# Get system information
echo ""
echo -e "${BLUE}System Information${NC}"
echo "=================="

# Service status
echo -e "${GREEN}Backend Service:${NC}"
if systemctl is-active supermon-ng-backend >/dev/null 2>&1; then
    echo "  Status: Running"
    echo "  PID: $(systemctl show supermon-ng-backend --property=MainPID --value)"
else
    echo "  Status: Not running"
fi

echo -e "${GREEN}Apache Service:${NC}"
if systemctl is-active apache2 >/dev/null 2>&1; then
    echo "  Status: Running"
else
    echo "  Status: Not running"
fi

# Node status service
if [ -f "$APP_DIR/user_files/sbin/node_info.ini" ]; then
    echo -e "${GREEN}Node Status Service:${NC}"
    if systemctl is-active supermon-ng-node-status.timer >/dev/null 2>&1; then
        echo "  Status: Running (Timer)"
    else
        echo "  Status: Not running"
    fi
fi

# Configuration files status
echo ""
echo -e "${BLUE}Configuration Status${NC}"
echo "====================="

CONFIG_FILES=(
    "user_files/global.inc"
    "user_files/authusers.inc"
    "user_files/favorites.ini"
    "user_files/privatenodes.txt"
)

for config_file in "${CONFIG_FILES[@]}"; do
    if [ -f "$APP_DIR/$config_file" ]; then
        echo -e "${GREEN}✓${NC} $config_file"
    else
        echo -e "${YELLOW}✗${NC} $config_file (missing)"
    fi
done

# Access URLs
echo ""
echo -e "${BLUE}Access URLs${NC}"
echo "==========="
echo -e "${GREEN}Local:${NC} http://localhost"
echo -e "${GREEN}Network:${NC} http://$(hostname -I | awk '{print $1}')"

# Check for other IP addresses
if command -v ip >/dev/null 2>&1; then
    OTHER_IPS=$(ip -o -4 addr show | awk '{print $4}' | cut -d'/' -f1 | grep -v '^127\.' | grep -v '^169\.254\.')
    if [ -n "$OTHER_IPS" ]; then
        echo -e "${GREEN}Other IPs:${NC}"
        echo "$OTHER_IPS" | while read -r ip; do
            echo "  http://$ip"
        done
    fi
fi

echo ""
echo -e "${BLUE}Update Information${NC}"
echo "==================="
echo "To check for updates or update to a newer version:"
echo "1. Download the latest version package"
echo "2. Extract it to a temporary directory"
echo "3. Run: sudo ./scripts/update.sh"
echo ""
echo "The update script will preserve your configurations when possible."
