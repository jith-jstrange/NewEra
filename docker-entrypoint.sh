#!/bin/bash
set -e

# Newera Plugin Docker Entrypoint
# This script prepares the WordPress environment and activates the plugin

echo "🚀 Newera Plugin - Docker Entrypoint"

# Wait for database to be ready
echo "⏳ Waiting for database connection..."
until wp db check --allow-root 2>/dev/null; do
    echo "Waiting for database..."
    sleep 3
done

echo "✅ Database is ready!"

# Install WordPress if not already installed
if ! wp core is-installed --allow-root 2>/dev/null; then
    echo "📦 Installing WordPress..."
    
    wp core install \
        --url="${WORDPRESS_URL:-http://localhost:8080}" \
        --title="${WORDPRESS_TITLE:-Newera Plugin Demo}" \
        --admin_user="${WORDPRESS_ADMIN_USER:-admin}" \
        --admin_password="${WORDPRESS_ADMIN_PASSWORD:-admin}" \
        --admin_email="${WORDPRESS_ADMIN_EMAIL:-admin@example.com}" \
        --skip-email \
        --allow-root
    
    echo "✅ WordPress installed!"
else
    echo "✅ WordPress already installed"
fi

# Activate Newera plugin if not already active
if ! wp plugin is-active newera --allow-root 2>/dev/null; then
    echo "🔌 Activating Newera plugin..."
    wp plugin activate newera --allow-root
    echo "✅ Newera plugin activated!"
else
    echo "✅ Newera plugin already active"
fi

# Set recommended WordPress configuration
echo "⚙️  Configuring WordPress settings..."

# Enable pretty permalinks
wp rewrite structure '/%postname%/' --allow-root 2>/dev/null || true

# Disable file editing from admin
wp config set DISALLOW_FILE_EDIT true --raw --allow-root 2>/dev/null || true

# Set memory limits
wp config set WP_MEMORY_LIMIT '256M' --allow-root 2>/dev/null || true
wp config set WP_MAX_MEMORY_LIMIT '512M' --allow-root 2>/dev/null || true

# Enable object cache if Redis is available
if command -v redis-cli &> /dev/null && redis-cli -h "${REDIS_HOST:-redis}" ping &> /dev/null; then
    echo "🔴 Redis detected, enabling object cache..."
    wp config set WP_REDIS_HOST "${REDIS_HOST:-redis}" --allow-root 2>/dev/null || true
    wp config set WP_REDIS_PORT "${REDIS_PORT:-6379}" --allow-root 2>/dev/null || true
    
    # Install and activate Redis Object Cache plugin if available
    if ! wp plugin is-installed redis-cache --allow-root 2>/dev/null; then
        wp plugin install redis-cache --activate --allow-root 2>/dev/null || true
    fi
fi

# Create logs directory if it doesn't exist
mkdir -p /var/www/html/wp-content/newera-logs
chown -R www-data:www-data /var/www/html/wp-content/newera-logs
chmod -R 755 /var/www/html/wp-content/newera-logs

# Display access information
echo ""
echo "=================================================="
echo "🎉 Newera Plugin is ready!"
echo "=================================================="
echo "WordPress URL: ${WORDPRESS_URL:-http://localhost:8080}"
echo "Admin Username: ${WORDPRESS_ADMIN_USER:-admin}"
echo "Admin Password: ${WORDPRESS_ADMIN_PASSWORD:-admin}"
echo ""
echo "📊 Access Points:"
echo "  - WordPress: http://localhost:${WORDPRESS_PORT:-8080}"
echo "  - WordPress Admin: http://localhost:${WORDPRESS_PORT:-8080}/wp-admin"
echo "  - Newera Dashboard: http://localhost:${WORDPRESS_PORT:-8080}/wp-admin/admin.php?page=newera"
if [ "${PHPMYADMIN_PORT}" ]; then
    echo "  - phpMyAdmin: http://localhost:${PHPMYADMIN_PORT:-8081}"
fi
echo ""
echo "=================================================="

# Execute the main command
exec "$@"
