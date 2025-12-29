#!/bin/bash

###############################################################################
# Laravel Systemd Services Installation Script
# This script installs and enables all Laravel background services
###############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_PATH="/var/www/jkdm-crm/crm-api"
SYSTEMD_PATH="/etc/systemd/system"
SERVICE_USER="www-data"
SERVICE_GROUP="www-data"

echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  Laravel Background Services Installation Script              ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}ERROR: Please run as root (use sudo)${NC}"
    exit 1
fi

# Check if project path exists
if [ ! -d "$PROJECT_PATH" ]; then
    echo -e "${YELLOW}WARNING: Project path does not exist: $PROJECT_PATH${NC}"
    read -p "Enter the correct path to your Laravel project: " PROJECT_PATH

    if [ ! -d "$PROJECT_PATH" ]; then
        echo -e "${RED}ERROR: Invalid path${NC}"
        exit 1
    fi
fi

# Get the directory where this script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

echo -e "${GREEN}Configuration:${NC}"
echo "  Project Path: $PROJECT_PATH"
echo "  Service User: $SERVICE_USER"
echo "  Service Group: $SERVICE_GROUP"
echo ""

# Update service files with correct paths
echo -e "${YELLOW}Updating service files with correct paths...${NC}"
for service_file in "$SCRIPT_DIR"/*.service; do
    if [ -f "$service_file" ]; then
        # Create temporary file with updated paths
        sed "s|/var/www/jkdm-crm/crm-api|$PROJECT_PATH|g" "$service_file" > "${service_file}.tmp"
        mv "${service_file}.tmp" "$service_file"
    fi
done

# List of services to install
SERVICES=(
    "laravel-queue"
    "laravel-queue-notifications"
    "laravel-scheduler"
    "laravel-email-processor"
    "laravel-websocket"
)

# Function to install a service
install_service() {
    local service_name=$1
    local service_file="${service_name}.service"

    echo -e "${YELLOW}Installing ${service_name}...${NC}"

    # Copy service file to systemd directory
    if [ -f "$SCRIPT_DIR/$service_file" ]; then
        cp "$SCRIPT_DIR/$service_file" "$SYSTEMD_PATH/$service_file"
        chmod 644 "$SYSTEMD_PATH/$service_file"
        echo -e "${GREEN}  ✓ Copied service file${NC}"
    else
        echo -e "${RED}  ✗ Service file not found: $service_file${NC}"
        return 1
    fi

    # Reload systemd daemon
    systemctl daemon-reload

    # Enable service to start on boot
    systemctl enable "$service_file"
    echo -e "${GREEN}  ✓ Enabled service${NC}"

    # Start the service
    systemctl start "$service_file"

    # Check if service started successfully
    if systemctl is-active --quiet "$service_file"; then
        echo -e "${GREEN}  ✓ Service started successfully${NC}"
    else
        echo -e "${RED}  ✗ Service failed to start${NC}"
        systemctl status "$service_file" --no-pager
    fi

    echo ""
}

# Install all services
echo -e "${GREEN}Installing services...${NC}"
echo ""

for service in "${SERVICES[@]}"; do
    read -p "Install ${service}? (y/n) [y]: " response
    response=${response:-y}

    if [[ "$response" =~ ^[Yy]$ ]]; then
        install_service "$service"
    else
        echo -e "${YELLOW}Skipping ${service}${NC}"
        echo ""
    fi
done

# Final status check
echo -e "${GREEN}╔════════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║  Installation Summary                                          ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════════╝${NC}"
echo ""

for service in "${SERVICES[@]}"; do
    service_file="${service}.service"

    if systemctl is-enabled --quiet "$service_file" 2>/dev/null; then
        status=$(systemctl is-active "$service_file")
        if [ "$status" == "active" ]; then
            echo -e "${GREEN}✓${NC} $service_file - ${GREEN}running${NC}"
        else
            echo -e "${RED}✗${NC} $service_file - ${RED}$status${NC}"
        fi
    else
        echo -e "${YELLOW}○${NC} $service_file - ${YELLOW}not installed${NC}"
    fi
done

echo ""
echo -e "${GREEN}Installation complete!${NC}"
echo ""
echo -e "${YELLOW}Useful commands:${NC}"
echo "  View service status:    sudo systemctl status <service-name>"
echo "  View service logs:      sudo journalctl -u <service-name> -f"
echo "  Restart service:        sudo systemctl restart <service-name>"
echo "  Stop service:           sudo systemctl stop <service-name>"
echo "  Disable service:        sudo systemctl disable <service-name>"
echo ""
echo -e "${YELLOW}Example:${NC}"
echo "  sudo systemctl status laravel-queue.service"
echo "  sudo journalctl -u laravel-queue.service -f"
echo ""
