#!/bin/bash

# Setup Script for Role-Based Menu System
# This script initializes the database and seeds the menu items

echo "========================================"
echo "Role-Based Menu System Setup"
echo "========================================"
echo ""

# Check if we're in the correct directory
if [ ! -f "artisan" ]; then
    echo "Error: This script must be run from the Laravel project root (crm-api)"
    exit 1
fi

echo "Step 1: Running database migrations..."
php artisan migrate --force

if [ $? -ne 0 ]; then
    echo "❌ Migration failed!"
    exit 1
fi
echo "✅ Migrations completed successfully"
echo ""

echo "Step 2: Seeding menu items..."
php artisan db:seed --class=MenuItemsSeeder --force

if [ $? -ne 0 ]; then
    echo "❌ Menu items seeding failed!"
    exit 1
fi
echo "✅ Menu items seeded successfully"
echo ""

echo "Step 3: Seeding role-menu relationships..."
php artisan db:seed --class=RoleMenuItemsSeeder --force

if [ $? -ne 0 ]; then
    echo "❌ Role-menu relationships seeding failed!"
    exit 1
fi
echo "✅ Role-menu relationships seeded successfully"
echo ""

echo "Step 4: Clearing cache..."
php artisan cache:clear

if [ $? -ne 0 ]; then
    echo "⚠️  Cache clear failed (not critical)"
else
    echo "✅ Cache cleared successfully"
fi
echo ""

echo "========================================"
echo "Setup Complete! 🎉"
echo "========================================"
echo ""
echo "Next steps:"
echo "1. Start your Laravel server: php artisan serve"
echo "2. Start your frontend dev server: npm run dev"
echo "3. Login with your credentials"
echo "4. Menus will be loaded dynamically based on your role"
echo ""
echo "To verify the setup:"
echo "  php artisan route:list --path=menus"
echo ""
echo "To test the API:"
echo "  curl -H 'Authorization: Bearer YOUR_TOKEN' http://localhost:8000/api/v1/menus"
echo ""
echo "For more information, see: ROLE_BASED_MENU_SYSTEM.md"
echo ""
