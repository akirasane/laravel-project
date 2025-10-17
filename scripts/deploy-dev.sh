#!/bin/bash

# Development Deployment Script
set -e

echo "🚀 Deploying Order Management System (Development)..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Copy development environment file
if [ ! -f ".env" ]; then
    echo "📋 Copying development environment file..."
    cp .env.development .env
    echo "✅ Environment file created"
fi

# Build and start development containers
echo "🏗️  Building development containers..."
docker-compose -f docker-compose.dev.yml build --no-cache

echo "🚀 Starting development services..."
docker-compose -f docker-compose.dev.yml up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 30

# Initialize Laravel if needed
echo "🔧 Initializing Laravel application..."
docker-compose -f docker-compose.dev.yml exec app bash -c "
    if [ ! -f 'vendor/autoload.php' ]; then
        echo '📦 Installing Laravel and dependencies...'
        /var/www/html/scripts/init-laravel.sh
    fi
    
    echo '🔑 Generating application key...'
    php artisan key:generate --force
    
    echo '🗄️  Running database migrations...'
    php artisan migrate --force
    
    echo '🌱 Seeding database...'
    php artisan db:seed --force
    
    echo '🔗 Creating storage link...'
    php artisan storage:link
    
    echo '📦 Installing FilamentPHP...'
    php artisan filament:install --panels
    
    echo '🎨 Building frontend assets...'
    npm run build
"

# Validate environment
echo "🔍 Validating environment configuration..."
docker-compose -f docker-compose.dev.yml exec app php scripts/validate-env.php

echo ""
echo "🎉 Development deployment complete!"
echo ""
echo "📋 Service URLs:"
echo "   🌐 Application: http://localhost:8080"
echo "   📧 Mailpit: http://localhost:8025"
echo "   🔌 WebSocket: ws://localhost:6001"
echo "   🗄️  MySQL: localhost:3307"
echo "   📊 Redis: localhost:6380"
echo ""
echo "🔧 Useful commands:"
echo "   docker-compose -f docker-compose.dev.yml logs -f    # View logs"
echo "   docker-compose -f docker-compose.dev.yml exec app bash    # Access container"
echo "   docker-compose -f docker-compose.dev.yml down    # Stop services"