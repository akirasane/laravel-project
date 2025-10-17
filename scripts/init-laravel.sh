#!/bin/bash

# Laravel 12 Initialization Script for Docker Environment
set -e

echo "🚀 Initializing Laravel 12 Order Management System..."

# Check if Laravel is already installed
if [ -f "composer.json" ] && grep -q "laravel/framework" composer.json; then
    echo "✅ Laravel already installed, skipping installation..."
else
    echo "📦 Installing Laravel 12..."
    
    # Create temporary directory for Laravel installation
    TEMP_DIR="/tmp/laravel_install"
    mkdir -p $TEMP_DIR
    
    # Install Laravel in temp directory
    cd $TEMP_DIR
    composer create-project laravel/laravel:^12.0 laravel_app --prefer-dist --no-interaction
    
    # Move Laravel files to working directory
    cd /var/www/html
    cp -r $TEMP_DIR/laravel_app/* .
    cp -r $TEMP_DIR/laravel_app/.* . 2>/dev/null || true
    
    # Clean up temp directory
    rm -rf $TEMP_DIR
    
    echo "✅ Laravel 12 installed successfully!"
fi

# Install additional required packages
echo "📦 Installing additional packages..."

composer require filament/filament:^4.0 --no-interaction
composer require beyondcode/laravel-websockets:^1.14 --no-interaction
composer require spatie/laravel-permission:^6.0 --no-interaction
composer require pusher/pusher-php-server:^7.2 --no-interaction
composer require predis/predis:^2.2 --no-interaction

echo "✅ Additional packages installed!"

# Copy environment files
echo "🔧 Setting up environment configuration..."

if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "✅ Environment file created from example"
fi

# Generate application key if not exists
if ! grep -q "APP_KEY=base64:" .env; then
    php artisan key:generate --ansi
    echo "✅ Application key generated"
fi

# Set proper permissions
echo "🔐 Setting file permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www:www storage bootstrap/cache

# Install Node.js dependencies
echo "📦 Installing Node.js dependencies..."
npm install

echo "🎉 Laravel 12 initialization complete!"
echo ""
echo "Next steps:"
echo "1. Update your .env file with proper database credentials"
echo "2. Run 'php artisan migrate' to set up the database"
echo "3. Run 'npm run build' to compile frontend assets"