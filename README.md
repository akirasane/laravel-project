# Multi-Platform Order Management System

A centralized order management system built with Laravel 12, FilamentPHP, and WebSocket real-time communication that consolidates orders from multiple e-commerce platforms (Shopee, Lazada, Shopify, TikTok).

## Features

- 🛒 **Multi-Platform Integration**: Connect to Shopee, Lazada, Shopify, and TikTok Shop
- 📊 **Unified Dashboard**: Centralized order management with real-time updates
- 🔄 **Flexible Workflows**: Configurable order processing workflows
- 🔐 **Secure Authentication**: Role-based access control with FilamentPHP
- 📱 **Real-time Notifications**: WebSocket-powered live updates
- 🏭 **Return Management**: Comprehensive return processing system
- 🐳 **Docker Ready**: Containerized for easy deployment

## Quick Start

### Prerequisites

- Docker and Docker Compose
- Make (optional, for convenience commands)

### Development Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd order-management-system
   ```

2. **Start development environment**
   ```bash
   make dev
   ```
   
   Or manually:
   ```bash
   chmod +x scripts/*.sh
   ./scripts/deploy-dev.sh
   ```

3. **Access the application**
   - Application: http://localhost:8080
   - Admin Panel: http://localhost:8080/admin
   - Mailpit: http://localhost:8025
   - WebSocket: ws://localhost:6001

### Production Setup

1. **Create Docker secrets**
   ```bash
   echo 'your_app_key' | docker secret create app_key -
   echo 'your_db_password' | docker secret create db_password -
   echo 'your_mysql_root_password' | docker secret create mysql_root_password -
   echo 'your_mysql_password' | docker secret create mysql_password -
   echo 'your_redis_password' | docker secret create redis_password -
   ```

2. **Deploy production environment**
   ```bash
   make prod
   ```

## Available Commands

### Development
- `make dev` - Start development environment
- `make init` - Initialize Laravel application
- `make logs` - View development logs
- `make shell` - Access development container shell

### Production
- `make prod` - Deploy production environment
- `make prod-logs` - View production logs
- `make prod-shell` - Access production container shell

### Management
- `make stop` - Stop all services
- `make clean` - Clean up containers and volumes
- `make health` - Run health checks
- `make backup` - Backup database
- `make restore` - Restore database from backup

### Laravel Commands
- `make artisan <command>` - Run artisan commands
- `make migrate` - Run database migrations
- `make seed` - Seed database
- `make fresh` - Fresh migration with seeding

### Frontend
- `make npm <command>` - Run npm commands
- `make build` - Build frontend assets
- `make watch` - Watch frontend changes

### Testing & Security
- `make test` - Run tests
- `make security-check` - Run security checks

## Configuration

### Environment Variables

The system uses different environment configurations:

- `.env.development` - Development environment
- `.env.production` - Production environment
- `.env.example` - Template with all available variables

### Platform API Configuration

Configure your platform credentials in the environment file:

```env
# Shopee
SHOPEE_PARTNER_ID=your_partner_id
SHOPEE_PARTNER_KEY=your_partner_key

# Lazada
LAZADA_APP_KEY=your_app_key
LAZADA_APP_SECRET=your_app_secret

# Shopify
SHOPIFY_API_KEY=your_api_key
SHOPIFY_API_SECRET=your_api_secret

# TikTok Shop
TIKTOK_APP_KEY=your_app_key
TIKTOK_APP_SECRET=your_app_secret
```

## Architecture

### Services

- **app**: Laravel application (PHP 8.3-FPM)
- **nginx**: Web server and reverse proxy
- **mysql**: Database server (MySQL 8.0)
- **redis**: Cache and session storage
- **queue**: Background job processing
- **websocket**: Real-time communication server
- **scheduler**: Laravel task scheduler

### Security Features

- OWASP Top 10 compliance
- Container security hardening
- Encrypted sensitive data storage
- Role-based access control
- Comprehensive audit logging
- Security headers implementation

## Development

### Project Structure

```
├── app/                    # Laravel application code
├── config/                 # Configuration files
├── database/              # Migrations, seeders, factories
├── docker/                # Docker configuration files
├── resources/             # Views, assets, language files
├── routes/                # Route definitions
├── scripts/               # Deployment and utility scripts
├── storage/               # Application storage
└── tests/                 # Test files
```

### Adding New Platform Integrations

1. Create a new connector class extending `PlatformConnector`
2. Implement the `PlatformConnectorInterface`
3. Add platform-specific configuration variables
4. Register the connector in the service provider

### Workflow Customization

Workflows can be customized through the FilamentPHP admin interface:

1. Navigate to Admin Panel → Workflows
2. Create new process flows
3. Define workflow steps and conditions
4. Assign tasks to users or roles

## Monitoring

### Health Checks

Run health checks to ensure all services are running properly:

```bash
make health
```

### Logs

View application logs:

```bash
# Development
make logs

# Production
make prod-logs

# Specific service
docker-compose logs -f <service-name>
```

### Performance Monitoring

- Application metrics via Laravel Telescope (development)
- Error tracking via Sentry (production)
- Database query monitoring
- Queue job monitoring

## Backup & Recovery

### Database Backup

```bash
make backup
```

### Database Restore

```bash
make restore
```

### File Storage Backup

Configure automated backups for file storage in production:

- Set up S3 bucket for file storage
- Configure automated database backups
- Implement disaster recovery procedures

## Troubleshooting

### Common Issues

1. **Container won't start**
   - Check Docker daemon is running
   - Verify port availability
   - Check environment variables

2. **Database connection failed**
   - Verify MySQL container is healthy
   - Check database credentials
   - Ensure network connectivity

3. **WebSocket connection issues**
   - Check port 6001 is available
   - Verify WebSocket server is running
   - Check firewall settings

### Debug Mode

Enable debug mode in development:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:

- Create an issue in the repository
- Check the documentation
- Review the troubleshooting guide