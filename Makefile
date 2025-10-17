# Order Management System - Docker Environment Management

.PHONY: help dev prod stop clean logs health init

# Default target
help:
	@echo "Order Management System - Available Commands:"
	@echo ""
	@echo "Development:"
	@echo "  make dev          - Start development environment"
	@echo "  make init         - Initialize Laravel application"
	@echo "  make logs         - View development logs"
	@echo "  make shell        - Access development container shell"
	@echo ""
	@echo "Production:"
	@echo "  make prod         - Deploy production environment"
	@echo "  make prod-logs    - View production logs"
	@echo "  make prod-shell   - Access production container shell"
	@echo ""
	@echo "Management:"
	@echo "  make stop         - Stop all services"
	@echo "  make clean        - Clean up containers and volumes"
	@echo "  make health       - Run health checks"
	@echo "  make backup       - Backup database"
	@echo "  make restore      - Restore database from backup"
	@echo ""

# Development environment
dev:
	@echo "🚀 Starting development environment..."
	@chmod +x scripts/*.sh
	@./scripts/deploy-dev.sh

# Production environment
prod:
	@echo "🚀 Starting production environment..."
	@chmod +x scripts/*.sh
	@./scripts/deploy-prod.sh

# Initialize Laravel application
init:
	@echo "🔧 Initializing Laravel application..."
	@docker-compose -f docker-compose.dev.yml exec app /var/www/html/scripts/init-laravel.sh

# Stop services
stop:
	@echo "🛑 Stopping all services..."
	@docker-compose -f docker-compose.dev.yml down || true
	@docker-compose -f docker-compose.prod.yml down || true

# Clean up
clean: stop
	@echo "🧹 Cleaning up containers and volumes..."
	@docker-compose -f docker-compose.dev.yml down -v --remove-orphans || true
	@docker-compose -f docker-compose.prod.yml down -v --remove-orphans || true
	@docker system prune -f

# View logs
logs:
	@docker-compose -f docker-compose.dev.yml logs -f

prod-logs:
	@docker-compose -f docker-compose.prod.yml logs -f

# Access container shell
shell:
	@docker-compose -f docker-compose.dev.yml exec app bash

prod-shell:
	@docker-compose -f docker-compose.prod.yml exec app bash

# Health checks
health:
	@chmod +x scripts/health-check.sh
	@./scripts/health-check.sh

# Database backup
backup:
	@echo "💾 Creating database backup..."
	@mkdir -p backups
	@docker-compose -f docker-compose.dev.yml exec mysql mysqldump -u root -proot_password order_management_dev > backups/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@echo "✅ Backup created in backups/ directory"

# Database restore
restore:
	@echo "📥 Restoring database from backup..."
	@read -p "Enter backup file name: " backup_file; \
	docker-compose -f docker-compose.dev.yml exec -T mysql mysql -u root -proot_password order_management_dev < backups/$$backup_file
	@echo "✅ Database restored"

# Laravel commands
artisan:
	@docker-compose -f docker-compose.dev.yml exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	@docker-compose -f docker-compose.dev.yml exec app php artisan migrate

seed:
	@docker-compose -f docker-compose.dev.yml exec app php artisan db:seed

fresh:
	@docker-compose -f docker-compose.dev.yml exec app php artisan migrate:fresh --seed

# Frontend commands
npm:
	@docker-compose -f docker-compose.dev.yml exec app npm $(filter-out $@,$(MAKECMDGOALS))

build:
	@docker-compose -f docker-compose.dev.yml exec app npm run build

watch:
	@docker-compose -f docker-compose.dev.yml exec app npm run dev

# Testing
test:
	@docker-compose -f docker-compose.dev.yml exec app php artisan test

# Security
security-check:
	@echo "🔒 Running security checks..."
	@docker-compose -f docker-compose.dev.yml exec app composer audit
	@docker-compose -f docker-compose.dev.yml exec app php scripts/validate-env.php

# Catch-all target for artisan and npm commands
%:
	@: