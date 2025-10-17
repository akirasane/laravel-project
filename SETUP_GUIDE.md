# Multi-Platform Order Management System - Setup Guide

This document provides a comprehensive guide for setting up the Docker environment and Laravel 12 project structure for the Order Management System.

## Prerequisites

- Docker and Docker Compose installed
- Git (optional, for version control)
- Make (optional, for convenience commands)

## Quick Start

### Option 1: Using Make (Recommended)
```bash
make dev
```

### Option 2: Manual Setup
Follow the detailed steps below for manual setup.

## Detailed Setup Process

### Step 1: Project Structure Setup

The project includes the following key files and directories:

```
├── docker/                     # Docker configuration files
│   ├── nginx/                 # Nginx configuration
│   ├── mysql/                 # MySQL configuration
│   ├── redis/                 # Redis configuration
│   ├── php/                   # PHP configuration
│   └── supervisor/            # Supervisor configuration
├── scripts/                   # Deployment and utility scripts
├── docker-compose.dev.yml     # Development environment
├── docker-compose.prod.yml    # Production environment
├── Dockerfile                 # Main application container
├── Dockerfile.prod           # Production container
├── .env.example              # Environment template
├── .env.development          # Development environment
├── .env.production           # Production environment
└── Makefile                  # Convenience commands
```

### Step 2: Build Docker Images

```bash
# Build all development images
docker-compose -f docker-compose.dev.yml build --no-cache

# Or build specific services
docker-compose -f docker-compose.dev.yml build --no-cache app
```

### Step 3: Start Services

```bash
# Start all services in detached mode
docker-compose -f docker-compose.dev.yml up -d

# Check service status
docker-compose -f docker-compose.dev.yml ps
```

### Step 4: Laravel Installation and Configuration

#### 4.1 Install Laravel 12
```bash
# Execute the Laravel installation script
docker-compose -f docker-compose.dev.yml exec app bash -c "/var/www/html/scripts/init-laravel.sh"
```

#### 4.2 Configure Git (if needed)
```bash
# Fix git ownership issues in container
docker-compose -f docker-compose.dev.yml exec app bash -c "git config --global --add safe.directory /var/www/html"
```

#### 4.3 Install PHP Dependencies
```bash
# Install FilamentPHP
docker-compose -f docker-compose.dev.yml exec app bash -c "composer require filament/filament:^4.0 --no-interaction"

# Install other required packages
docker-compose -f docker-compose.dev.yml exec app bash -c "composer require spatie/laravel-permission:^6.0 pusher/pusher-php-server:^7.2 predis/predis:^2.2 --no-interaction"

# Install Laravel Reverb (WebSocket server)
docker-compose -f docker-compose.dev.yml exec app bash -c "composer require laravel/reverb --no-interaction"
```

#### 4.4 Generate Application Key
```bash
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan key:generate --force"
```

#### 4.5 Install Node.js Dependencies
```bash
docker-compose -f docker-compose.dev.yml exec app bash -c "npm install"
```

### Step 5: Configure Environment

#### 5.1 Copy Environment File
```bash
# Copy development environment file
docker-compose -f docker-compose.dev.yml exec app bash -c "cp .env.development .env"
```

#### 5.2 Update Database Configuration
```bash
# Update .env file with correct database settings
docker-compose -f docker-compose.dev.yml exec app bash -c "
sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env &&
sed -i 's/# DB_HOST=127.0.0.1/DB_HOST=mysql/' .env &&
sed -i 's/# DB_PORT=3306/DB_PORT=3306/' .env &&
sed -i 's/# DB_DATABASE=laravel/DB_DATABASE=order_management_dev/' .env &&
sed -i 's/# DB_USERNAME=root/DB_USERNAME=laravel/' .env &&
sed -i 's/# DB_PASSWORD=/DB_PASSWORD=secret/' .env
"
```

#### 5.3 Add Redis and Cache Configuration
```bash
# Add Redis configuration to .env
docker-compose -f docker-compose.dev.yml exec app bash -c "
echo '' >> .env &&
echo '# Redis Configuration' >> .env &&
echo 'REDIS_HOST=redis' >> .env &&
echo 'REDIS_PASSWORD=null' >> .env &&
echo 'REDIS_PORT=6379' >> .env &&
echo 'REDIS_DB=0' >> .env &&
echo 'REDIS_CLIENT=predis' >> .env &&
echo '' >> .env &&
echo '# Cache Configuration' >> .env &&
echo 'CACHE_STORE=redis' >> .env &&
echo 'CACHE_PREFIX=order_mgmt_dev' >> .env &&
echo '' >> .env &&
echo '# Session Configuration' >> .env &&
echo 'SESSION_DRIVER=redis' >> .env &&
echo 'SESSION_LIFETIME=120' >> .env &&
echo '' >> .env &&
echo '# Queue Configuration' >> .env &&
echo 'QUEUE_CONNECTION=redis' >> .env
"
```

### Step 6: Setup FilamentPHP

```bash
# Install FilamentPHP panels
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan filament:install --panels"
```

### Step 7: Build Frontend Assets

```bash
# Build production assets
docker-compose -f docker-compose.dev.yml exec app bash -c "npm run build"
```

### Step 8: Database Setup

#### 8.1 Create Storage Link
```bash
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan storage:link"
```

#### 8.2 Publish Package Configurations
```bash
# Publish Laravel Reverb configuration
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan vendor:publish --provider='Laravel\Reverb\ReverbServiceProvider' --tag='reverb-config'"

# Publish Spatie Permission migrations
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan vendor:publish --provider='Spatie\Permission\PermissionServiceProvider'"
```

#### 8.3 Run Database Migrations
```bash
# Run all migrations
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan migrate --force"
```

### Step 9: Clear Caches and Restart Services

```bash
# Clear application caches
docker-compose -f docker-compose.dev.yml exec app bash -c "php artisan config:clear && php artisan cache:clear"

# Restart services that depend on configuration
docker-compose -f docker-compose.dev.yml restart queue websocket scheduler
```

## Service URLs

After successful setup, the following services will be available:

| Service | URL | Description |
|---------|-----|-------------|
| **Main Application** | http://localhost:8080 | Laravel application |
| **Admin Panel** | http://localhost:8080/admin | FilamentPHP admin interface |
| **Mailpit** | http://localhost:8025 | Email testing interface |
| **WebSocket** | ws://localhost:6001 | Real-time communication |
| **MySQL** | localhost:3307 | Database (external access) |
| **Redis** | localhost:6380 | Cache server (external access) |

## Useful Commands

### Container Management
```bash
# View all running containers
docker-compose -f docker-compose.dev.yml ps

# View logs for all services
docker-compose -f docker-compose.dev.yml logs -f

# View logs for specific service
docker-compose -f docker-compose.dev.yml logs -f app

# Access container shell
docker-compose -f docker-compose.dev.yml exec app bash

# Restart specific service
docker-compose -f docker-compose.dev.yml restart nginx

# Stop all services
docker-compose -f docker-compose.dev.yml down

# Stop and remove volumes
docker-compose -f docker-compose.dev.yml down -v
```

### Laravel Commands
```bash
# Run artisan commands
docker-compose -f docker-compose.dev.yml exec app php artisan [command]

# Examples:
docker-compose -f docker-compose.dev.yml exec app php artisan route:list
docker-compose -f docker-compose.dev.yml exec app php artisan migrate
docker-compose -f docker-compose.dev.yml exec app php artisan tinker
```

### Database Operations
```bash
# Access MySQL directly
docker-compose -f docker-compose.dev.yml exec mysql mysql -u laravel -psecret order_management_dev

# Backup database
docker-compose -f docker-compose.dev.yml exec mysql mysqldump -u root -proot_password order_management_dev > backup.sql

# Restore database
docker-compose -f docker-compose.dev.yml exec -T mysql mysql -u root -proot_password order_management_dev < backup.sql
```

### Redis Operations
```bash
# Access Redis CLI
docker-compose -f docker-compose.dev.yml exec redis redis-cli

# Monitor Redis
docker-compose -f docker-compose.dev.yml exec redis redis-cli monitor
```

## Troubleshooting

### Common Issues and Solutions

#### 1. Port Already in Use
```bash
# Check what's using the port
netstat -tulpn | grep :8080

# Kill the process or change port in docker-compose.dev.yml
```

#### 2. Permission Issues
```bash
# Fix file permissions
docker-compose -f docker-compose.dev.yml exec app chown -R www:www storage bootstrap/cache
docker-compose -f docker-compose.dev.yml exec app chmod -R 755 storage bootstrap/cache
```

#### 3. Database Connection Issues
```bash
# Check MySQL container health
docker-compose -f docker-compose.dev.yml exec mysql mysqladmin ping -h localhost

# Verify environment variables
docker-compose -f docker-compose.dev.yml exec app php artisan config:show database
```

#### 4. Redis Connection Issues
```bash
# Test Redis connection
docker-compose -f docker-compose.dev.yml exec redis redis-cli ping

# Check Redis configuration
docker-compose -f docker-compose.dev.yml exec app php artisan config:show cache
```

#### 5. WebSocket Issues
```bash
# Check Reverb server status
docker-compose -f docker-compose.dev.yml logs websocket

# Test WebSocket connection
docker-compose -f docker-compose.dev.yml exec app php artisan reverb:start --host=0.0.0.0 --port=6001
```

### Health Checks

Run the built-in health check script:
```bash
# Make script executable and run
chmod +x scripts/health-check.sh
./scripts/health-check.sh
```

Or use the Make command:
```bash
make health
```

## Production Deployment

For production deployment, use the production configuration:

```bash
# Create Docker secrets first
echo 'your_app_key' | docker secret create app_key -
echo 'your_db_password' | docker secret create db_password -
echo 'your_mysql_root_password' | docker secret create mysql_root_password -
echo 'your_mysql_password' | docker secret create mysql_password -
echo 'your_redis_password' | docker secret create redis_password -

# Deploy production environment
make prod
# or
chmod +x scripts/deploy-prod.sh
./scripts/deploy-prod.sh
```

## Development Workflow

### Daily Development Commands
```bash
# Start development environment
make dev

# View logs
make logs

# Access container
make shell

# Run migrations
make migrate

# Build assets
make build

# Run tests
make test

# Stop environment
make stop
```

### Code Changes Workflow
1. Make code changes in your local files
2. Changes are automatically reflected due to volume mounting
3. For configuration changes, restart relevant services:
   ```bash
   docker-compose -f docker-compose.dev.yml restart app nginx
   ```
4. For database changes, run migrations:
   ```bash
   make migrate
   ```

## Security Considerations

### Development Environment
- Uses non-root user in containers
- Implements proper file permissions
- Includes security headers in Nginx
- Uses environment-specific configurations

### Production Environment
- Docker secrets for sensitive data
- Encrypted data storage
- Security-hardened containers
- Comprehensive health checks
- Process supervision with Supervisor

## Performance Optimization

### Development
- Redis caching enabled
- Optimized Nginx configuration
- PHP-FPM tuning
- Asset compilation and optimization

### Production
- Multi-stage Docker builds
- Container resource limits
- Database query optimization
- CDN-ready asset handling

## Backup and Recovery

### Automated Backups
```bash
# Database backup
make backup

# Restore from backup
make restore
```

### Manual Backups
```bash
# Create backup directory
mkdir -p backups

# Backup database
docker-compose -f docker-compose.dev.yml exec mysql mysqldump -u root -proot_password order_management_dev > backups/backup_$(date +%Y%m%d_%H%M%S).sql

# Backup application files (if needed)
tar -czf backups/app_backup_$(date +%Y%m%d_%H%M%S).tar.gz --exclude=node_modules --exclude=vendor .
```

## Next Steps

After successful setup, you can:

1. **Access the application** at http://localhost:8080
2. **Access the admin panel** at http://localhost:8080/admin
3. **Start implementing features** according to the task list
4. **Create database seeders** for initial data
5. **Set up authentication** and user management
6. **Begin building the order management features**

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review container logs: `make logs`
3. Run health checks: `make health`
4. Consult the main README.md file
5. Check the Laravel and FilamentPHP documentation

---

**Note**: This setup guide is based on the actual commands executed during the initial setup process. All commands have been tested and verified to work correctly.