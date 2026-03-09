#!/bin/bash

# Mitel Sync Service - Quick Setup Script
# This script sets up the Mitel data sync service

set -e

echo "========================================="
echo "Mitel CCMData Sync Service Setup"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: This script must be run from the crm-api directory${NC}"
    exit 1
fi

echo -e "${YELLOW}Step 1: Running migrations...${NC}"
php artisan migrate

echo ""
echo -e "${GREEN}✓ Migrations completed${NC}"
echo ""

# Prompt for connection details
echo -e "${YELLOW}Step 2: Setting up Mitel connection${NC}"
echo ""
read -p "Enter Mitel Database Host (default: 10.0.0.84): " MITEL_HOST
MITEL_HOST=${MITEL_HOST:-10.0.0.84}

read -p "Enter Mitel Database Port (default: 1433): " MITEL_PORT
MITEL_PORT=${MITEL_PORT:-1433}

read -p "Enter Mitel Database Name (default: CCMData): " MITEL_DB
MITEL_DB=${MITEL_DB:-CCMData}

read -p "Enter Mitel Database Username: " MITEL_USER
if [ -z "$MITEL_USER" ]; then
    echo -e "${RED}Error: Username is required${NC}"
    exit 1
fi

read -s -p "Enter Mitel Database Password: " MITEL_PASS
echo ""
if [ -z "$MITEL_PASS" ]; then
    echo -e "${RED}Error: Password is required${NC}"
    exit 1
fi

# Create connection using artisan tinker
echo ""
echo -e "${YELLOW}Creating Mitel connection...${NC}"

php artisan tinker <<EOF
use App\Models\MitelConnection;

\$connection = MitelConnection::create([
    'name' => 'Primary CCMData',
    'description' => 'Main Mitel CCMData database connection',
    'host' => '${MITEL_HOST}',
    'port' => ${MITEL_PORT},
    'database' => '${MITEL_DB}',
    'username' => '${MITEL_USER}',
    'password' => '${MITEL_PASS}',
    'encrypt' => true,
    'trust_server_certificate' => true,
    'is_active' => true,
]);

echo "Connection created successfully: ID=" . \$connection->id . "\n";
EOF

echo ""
echo -e "${GREEN}✓ Connection created${NC}"
echo ""

# Test connection
echo -e "${YELLOW}Step 3: Testing connection...${NC}"
php artisan mitel:sync "Primary CCMData" --test

if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ Connection test successful${NC}"
    echo ""
else
    echo ""
    echo -e "${RED}✗ Connection test failed${NC}"
    echo "Please check your credentials and ensure the SQL Server is accessible."
    exit 1
fi

# Ask if user wants to run initial sync
echo ""
read -p "Do you want to run an initial sync now? (y/n): " RUN_SYNC

if [ "$RUN_SYNC" = "y" ] || [ "$RUN_SYNC" = "Y" ]; then
    echo ""
    echo -e "${YELLOW}Running initial sync...${NC}"
    php artisan mitel:sync "Primary CCMData" --type=full
    
    if [ $? -eq 0 ]; then
        echo ""
        echo -e "${GREEN}✓ Initial sync completed${NC}"
    else
        echo ""
        echo -e "${RED}✗ Sync failed${NC}"
        echo "Check the logs for details: storage/logs/laravel.log"
    fi
fi

echo ""
echo "========================================="
echo -e "${GREEN}Setup Complete!${NC}"
echo "========================================="
echo ""
echo "What's next?"
echo ""
echo "1. The sync service is now configured and will run automatically"
echo "   based on the schedule in config/mitel.php"
echo ""
echo "2. To manually trigger a sync:"
echo "   php artisan mitel:sync --all"
echo ""
echo "3. To test the connection:"
echo "   php artisan mitel:sync \"Primary CCMData\" --test"
echo ""
echo "4. To view sync logs:"
echo "   tail -f storage/logs/laravel.log"
echo ""
echo "5. Read the full documentation:"
echo "   cat MITEL_SYNC_GUIDE.md"
echo ""
echo "========================================="
