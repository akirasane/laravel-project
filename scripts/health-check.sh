#!/bin/bash

# Health Check Script for Order Management System
set -e

echo "🏥 Running health checks..."

# Check if containers are running
echo "🔍 Checking container status..."
CONTAINERS=("app" "nginx" "mysql" "redis" "queue" "websocket" "scheduler")

for container in "${CONTAINERS[@]}"; do
    if docker ps --format "table {{.Names}}" | grep -q "order_management_${container}"; then
        echo "✅ ${container}: Running"
    else
        echo "❌ ${container}: Not running"
        exit 1
    fi
done

# Check application health
echo ""
echo "🔍 Checking application health..."

# Test web server response
if curl -f -s http://localhost:8080/up > /dev/null; then
    echo "✅ Web server: Responding"
else
    echo "❌ Web server: Not responding"
    exit 1
fi

# Test database connection
if docker-compose exec -T mysql mysqladmin ping -h localhost --silent; then
    echo "✅ Database: Connected"
else
    echo "❌ Database: Connection failed"
    exit 1
fi

# Test Redis connection
if docker-compose exec -T redis redis-cli ping | grep -q "PONG"; then
    echo "✅ Redis: Connected"
else
    echo "❌ Redis: Connection failed"
    exit 1
fi

# Test WebSocket server
if nc -z localhost 6001; then
    echo "✅ WebSocket: Listening"
else
    echo "❌ WebSocket: Not listening"
    exit 1
fi

# Check queue workers
QUEUE_WORKERS=$(docker-compose exec -T app php artisan queue:monitor | grep -c "Processing" || echo "0")
if [ "$QUEUE_WORKERS" -gt 0 ]; then
    echo "✅ Queue workers: Active ($QUEUE_WORKERS workers)"
else
    echo "⚠️  Queue workers: No active workers"
fi

# Check disk space
echo ""
echo "🔍 Checking system resources..."

DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -lt 80 ]; then
    echo "✅ Disk space: ${DISK_USAGE}% used"
else
    echo "⚠️  Disk space: ${DISK_USAGE}% used (Warning: >80%)"
fi

# Check memory usage
MEMORY_USAGE=$(free | grep Mem | awk '{printf "%.0f", $3/$2 * 100.0}')
if [ "$MEMORY_USAGE" -lt 80 ]; then
    echo "✅ Memory usage: ${MEMORY_USAGE}%"
else
    echo "⚠️  Memory usage: ${MEMORY_USAGE}% (Warning: >80%)"
fi

echo ""
echo "🎉 Health check completed successfully!"