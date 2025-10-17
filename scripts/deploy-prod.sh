#!/bin/bash

# Production Deployment Script
set -e

echo "🚀 Deploying Order Management System (Production)..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "❌ Docker is not running. Please start Docker first."
    exit 1
fi

# Check if Docker secrets exist
REQUIRED_SECRETS=("app_key" "db_password" "mysql_root_password" "mysql_password" "redis_password")
for secret in "${REQUIRED_SECRETS[@]}"; do
    if ! docker secret ls | grep -q "$secret"; then
        echo "❌ Docker secret '$secret' not found. Please create it first:"
        echo "   echo 'your_secret_value' | docker secret create $secret -"
        exit 1
    fi
done

# Copy production environment file
if [ ! -f ".env" ]; then
    echo "📋 Copying production environment file..."
    cp .env.production .env
    echo "✅ Environment file created"
fi

# Build production images
echo "🏗️  Building production containers..."
docker-compose -f docker-compose.prod.yml build --no-cache

# Start production services
echo "🚀 Starting production services..."
docker-compose -f docker-compose.prod.yml up -d

# Wait for services to be ready
echo "⏳ Waiting for services to be ready..."
sleep 60

# Run production setup
echo "🔧 Setting up production environment..."
docker-compose -f docker-compose.prod.yml exec app bash -c "
    echo '🗄️  Running database migrations...'
    php artisan migrate --force
    
    echo '🔧 Optimizing application...'
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    
    echo '🔗 Creating storage link...'
    php artisan storage:link
    
    echo '📦 Installing FilamentPHP...'
    php artisan filament:install --panels
"

# Validate production environment
echo "🔍 Validating production environment..."
docker-compose -f docker-compose.prod.yml exec app php scripts/validate-env.php

# Run security checks
echo "🔒 Running security checks..."
docker-compose -f docker-compose.prod.yml exec app bash -c "
    echo '🔍 Checking file permissions...'
    find storage -type f -not -perm 644 -exec chmod 644 {} \;
    find storage -type d -not -perm 755 -exec chmod 755 {} \;
    
    echo '🔍 Checking configuration security...'
    php artisan config:show | grep -i debug
"

echo ""
echo "🎉 Production deployment complete!"
echo ""
echo "📋 Service URLs:"
echo "   🌐 Application: https://your-domain.com"
echo "   🔌 WebSocket: wss://your-domain.com:6001"
echo ""
echo "🔧 Useful commands:"
echo "   docker-compose -f docker-compose.prod.yml logs -f    # View logs"
echo "   docker-compose -f docker-compose.prod.yml exec app bash    # Access container"
echo "   docker-compose -f docker-compose.prod.yml down    # Stop services"
echo ""
echo "⚠️  Remember to:"
echo "   1. Configure your domain DNS"
echo "   2. Set up SSL certificates"
echo "   3. Configure firewall rules"
echo "   4. Set up monitoring and backups"