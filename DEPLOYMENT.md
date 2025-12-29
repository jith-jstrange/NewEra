# Newera WordPress Plugin - Deployment Guide

## 🚀 Quick Start: One-Click Deployment

The Newera WordPress plugin is designed for one-click deployment using Docker. Get started in under 5 minutes!

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) (20.10 or later)
- [Docker Compose](https://docs.docker.com/compose/install/) (2.0 or later)
- 4GB RAM minimum (8GB recommended)
- 10GB free disk space

### One-Click Deployment

1. **Clone the repository:**
   ```bash
   git clone https://github.com/jith-jstrange/NewEra.git
   cd NewEra
   ```

2. **Create environment file:**
   ```bash
   cp .env.example .env
   ```

3. **Generate WordPress salts:**
   ```bash
   # Visit https://api.wordpress.org/secret-key/1.1/salt/ and copy the values to .env
   # Or use this command (Linux/Mac):
   curl -s https://api.wordpress.org/secret-key/1.1/salt/ >> .env
   ```

4. **Start the application:**
   ```bash
   docker-compose up -d
   ```

5. **Access WordPress:**
   - WordPress: http://localhost:8080
   - Admin Panel: http://localhost:8080/wp-admin
   - Newera Dashboard: http://localhost:8080/wp-admin/admin.php?page=newera
   - phpMyAdmin: http://localhost:8081

**Default Credentials:**
- Username: `admin`
- Password: `admin` (change this immediately!)

---

## 📋 Table of Contents

1. [Deployment Methods](#deployment-methods)
2. [Environment Configuration](#environment-configuration)
3. [Production Deployment](#production-deployment)
4. [Cloud Platform Deployment](#cloud-platform-deployment)
5. [Manual Installation](#manual-installation)
6. [Backup and Restore](#backup-and-restore)
7. [Monitoring and Maintenance](#monitoring-and-maintenance)
8. [Troubleshooting](#troubleshooting)

---

## Deployment Methods

### Method 1: Docker Compose (Recommended)

**Best for:** Development, testing, and production deployments

```bash
# Start all services
docker-compose up -d

# View logs
docker-compose logs -f

# Stop all services
docker-compose down

# Rebuild after code changes
docker-compose up -d --build
```

### Method 2: Docker Only

**Best for:** Kubernetes, container orchestration platforms

```bash
# Build the image
docker build -t newera-plugin:latest .

# Run with existing WordPress installation
docker run -d \
  -v /path/to/wordpress/wp-content/plugins/newera:/var/www/html/wp-content/plugins/newera \
  newera-plugin:latest
```

### Method 3: Traditional WordPress Installation

**Best for:** Shared hosting, existing WordPress sites

See [Manual Installation](#manual-installation) section below.

---

## Environment Configuration

### Essential Environment Variables

Edit `.env` file with your configuration:

```bash
# Database Configuration
MYSQL_ROOT_PASSWORD=your-secure-root-password
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress
MYSQL_PASSWORD=your-secure-database-password

# WordPress Configuration
WORDPRESS_PORT=8080
WORDPRESS_DEBUG=false

# WordPress Salts (REQUIRED - Generate at https://api.wordpress.org/secret-key/1.1/salt/)
WORDPRESS_AUTH_KEY=your-unique-key-here
WORDPRESS_SECURE_AUTH_KEY=your-unique-key-here
# ... (add all 8 salts)
```

### Optional Configurations

```bash
# External PostgreSQL Database (Neon, Supabase, etc.)
EXTERNAL_DB_TYPE=postgresql
EXTERNAL_DB_CONNECTION_STRING=postgresql://user:pass@host:5432/db

# Stripe Payments
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_MODE=live

# AI Integrations
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...

# Project Management Integrations
LINEAR_API_KEY=lin_api_...
NOTION_API_KEY=secret_...
```

---

## Production Deployment

### Security Checklist

Before deploying to production:

- [ ] Change all default passwords
- [ ] Generate unique WordPress salts
- [ ] Set `WORDPRESS_DEBUG=false`
- [ ] Use strong database passwords
- [ ] Enable HTTPS/SSL
- [ ] Configure firewall rules
- [ ] Set up regular backups
- [ ] Review file permissions
- [ ] Configure rate limiting
- [ ] Enable security headers

### Performance Optimization

```bash
# Enable Redis cache
docker-compose up -d redis

# Configure in wp-config.php:
define('WP_CACHE', true);
define('WP_REDIS_HOST', 'redis');
define('WP_REDIS_PORT', 6379);
```

### SSL/HTTPS Setup

#### With Nginx Reverse Proxy

Create `docker-compose.prod.yml`:

```yaml
version: '3.8'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
    depends_on:
      - wordpress
    networks:
      - newera-network

  wordpress:
    ports: []  # Remove direct port exposure
```

#### With Let's Encrypt

```bash
# Install certbot
sudo apt-get install certbot

# Get certificate
sudo certbot certonly --standalone -d yourdomain.com

# Configure nginx with SSL
```

### Scaling

For high-traffic sites:

```bash
# Scale WordPress containers
docker-compose up -d --scale wordpress=3

# Add load balancer
docker-compose -f docker-compose.yml -f docker-compose.lb.yml up -d
```

---

## Cloud Platform Deployment

### AWS EC2

1. Launch EC2 instance (t3.medium or larger)
2. Install Docker and Docker Compose
3. Clone repository
4. Configure environment variables
5. Run `docker-compose up -d`

### Google Cloud Platform

1. Create Compute Engine instance
2. Enable Container-Optimized OS
3. Deploy using Cloud Run or GKE
4. Configure Cloud SQL for MySQL

### DigitalOcean

1. Create Droplet with Docker
2. Use DigitalOcean Managed Database
3. Configure floating IP for high availability

### Kubernetes Deployment

```bash
# Create namespace
kubectl create namespace newera

# Deploy using Helm or kubectl
kubectl apply -f k8s/

# Expose service
kubectl expose deployment newera --type=LoadBalancer --port=80
```

---

## Manual Installation

For traditional WordPress hosting:

### Step 1: Download Plugin

```bash
# Download latest release
wget https://github.com/jith-jstrange/NewEra/archive/refs/heads/main.zip
unzip main.zip
```

### Step 2: Install to WordPress

```bash
# Copy to WordPress plugins directory
cp -r NewEra-main /path/to/wordpress/wp-content/plugins/newera
```

### Step 3: Install Dependencies

```bash
cd /path/to/wordpress/wp-content/plugins/newera

# Install Composer dependencies
composer install --no-dev --optimize-autoloader
```

### Step 4: Activate Plugin

1. Go to WordPress Admin → Plugins
2. Find "Newera" in the list
3. Click "Activate"
4. Complete the setup wizard

---

## Backup and Restore

### Automated Backups

```bash
# Create backup script
cat > backup.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR=/backups

# Backup database
docker-compose exec -T db mysqldump -u root -p$MYSQL_ROOT_PASSWORD wordpress > $BACKUP_DIR/db_$DATE.sql

# Backup WordPress files
docker-compose exec -T wordpress tar -czf - /var/www/html > $BACKUP_DIR/wordpress_$DATE.tar.gz

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql" -mtime +30 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
EOF

chmod +x backup.sh

# Schedule daily backups
crontab -e
# Add: 0 2 * * * /path/to/backup.sh
```

### Manual Backup

```bash
# Backup database
docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > backup.sql

# Backup uploads
docker cp newera-wordpress:/var/www/html/wp-content/uploads ./uploads-backup
```

### Restore from Backup

```bash
# Restore database
docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < backup.sql

# Restore files
docker cp ./uploads-backup newera-wordpress:/var/www/html/wp-content/uploads
```

---

## Monitoring and Maintenance

### Health Checks

```bash
# Check container status
docker-compose ps

# Check WordPress health
curl http://localhost:8080/

# Check database connection
docker-compose exec wordpress wp db check --allow-root
```

### Log Management

```bash
# View WordPress logs
docker-compose logs -f wordpress

# View database logs
docker-compose logs -f db

# View Newera plugin logs
docker-compose exec wordpress tail -f /var/www/html/wp-content/newera-logs/newera.log
```

### Updates

```bash
# Update plugin
cd /path/to/NewEra
git pull origin main
docker-compose up -d --build

# Update WordPress core
docker-compose exec wordpress wp core update --allow-root

# Update WordPress plugins
docker-compose exec wordpress wp plugin update --all --allow-root
```

---

## Troubleshooting

### Common Issues

#### Container won't start

```bash
# Check logs
docker-compose logs wordpress

# Rebuild containers
docker-compose down
docker-compose up -d --build
```

#### Database connection failed

```bash
# Verify database is running
docker-compose ps db

# Check database logs
docker-compose logs db

# Test connection
docker-compose exec wordpress wp db check --allow-root
```

#### Plugin activation failed

```bash
# Check PHP errors
docker-compose exec wordpress tail -f /var/www/html/wp-content/debug.log

# Verify file permissions
docker-compose exec wordpress ls -la /var/www/html/wp-content/plugins/newera
```

#### Out of memory

```bash
# Increase PHP memory limit in .env
WORDPRESS_PHP_MEMORY_LIMIT=512M

# Restart containers
docker-compose restart
```

### Performance Issues

```bash
# Enable query logging
docker-compose exec db mysql -u root -p -e "SET GLOBAL slow_query_log = 'ON';"

# Monitor resource usage
docker stats

# Check Redis cache hit ratio
docker-compose exec redis redis-cli info stats
```

### Reset Everything

```bash
# ⚠️ WARNING: This will delete ALL data!

# Stop and remove containers
docker-compose down -v

# Remove all data
sudo rm -rf volumes/

# Start fresh
docker-compose up -d
```

---

## Support and Resources

- **Documentation:** https://github.com/jith-jstrange/NewEra
- **Issues:** https://github.com/jith-jstrange/NewEra/issues
- **Community:** [Discord/Slack Link]

---

## License

GPL v2 or later. See [LICENSE](LICENSE) file for details.
