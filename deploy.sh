#!/bin/bash

# Newera Plugin - Quick Deploy Script
# This script provides a one-click deployment solution

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${YELLOW}ℹ $1${NC}"
}

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Banner
echo "=================================================="
echo "  Newera WordPress Plugin - Quick Deploy"
echo "=================================================="
echo ""

# Check prerequisites
print_info "Checking prerequisites..."

if ! command_exists docker; then
    print_error "Docker is not installed. Please install Docker first."
    echo "Visit: https://docs.docker.com/get-docker/"
    exit 1
fi
print_success "Docker found"

if ! command_exists docker-compose; then
    print_error "Docker Compose is not installed."
    echo "Visit: https://docs.docker.com/compose/install/"
    exit 1
fi
print_success "Docker Compose found"

# Check if .env exists
if [ ! -f .env ]; then
    print_warning ".env file not found. Creating from .env.example..."
    cp .env.example .env
    
    print_info "Please edit .env file and update the following:"
    echo "  - MYSQL_ROOT_PASSWORD"
    echo "  - MYSQL_PASSWORD"
    echo "  - WordPress salts (visit https://api.wordpress.org/secret-key/1.1/salt/)"
    echo ""
    
    read -p "Press Enter when you've updated .env file (or Ctrl+C to cancel)..."
fi

# Prompt for deployment mode
echo ""
echo "Select deployment mode:"
echo "  1) Development (with debug mode)"
echo "  2) Production (optimized)"
read -p "Enter choice [1/2]: " DEPLOY_MODE

if [ "$DEPLOY_MODE" = "2" ]; then
    print_info "Configuring for production..."
    sed -i 's/WORDPRESS_DEBUG=true/WORDPRESS_DEBUG=false/' .env 2>/dev/null || true
    sed -i 's/NEWERA_ENV=development/NEWERA_ENV=production/' .env 2>/dev/null || true
    
    # Build production assets
    if command_exists npm; then
        print_info "Building production assets..."
        npm install --silent
        npm run build
        print_success "Assets built"
    fi
fi

# Stop existing containers
if docker-compose ps | grep -q "Up"; then
    print_info "Stopping existing containers..."
    docker-compose down
fi

# Pull latest images
print_info "Pulling latest Docker images..."
docker-compose pull

# Build and start containers
print_info "Building and starting containers..."
docker-compose up -d --build

# Wait for services to be ready
print_info "Waiting for services to start..."
sleep 10

# Check service health
print_info "Checking service health..."

if docker-compose ps | grep -q "wordpress.*Up"; then
    print_success "WordPress container is running"
else
    print_error "WordPress container failed to start"
    print_info "Check logs with: docker-compose logs wordpress"
    exit 1
fi

if docker-compose ps | grep -q "db.*Up"; then
    print_success "Database container is running"
else
    print_error "Database container failed to start"
    print_info "Check logs with: docker-compose logs db"
    exit 1
fi

# Get WordPress port from .env
WORDPRESS_PORT=$(grep WORDPRESS_PORT .env | cut -d '=' -f2 || echo "8080")

# Display access information
echo ""
echo "=================================================="
print_success "Deployment Complete!"
echo "=================================================="
echo ""
echo "Access your WordPress site at:"
echo "  🌐 WordPress: http://localhost:${WORDPRESS_PORT}"
echo "  🔐 Admin: http://localhost:${WORDPRESS_PORT}/wp-admin"
echo "  🎯 Newera Dashboard: http://localhost:${WORDPRESS_PORT}/wp-admin/admin.php?page=newera"
echo ""
echo "Default credentials (change immediately!):"
echo "  Username: admin"
echo "  Password: admin"
echo ""
echo "Useful commands:"
echo "  View logs: docker-compose logs -f"
echo "  Stop: docker-compose down"
echo "  Restart: docker-compose restart"
echo ""
echo "=================================================="

# Offer to open in browser
if command_exists xdg-open; then
    read -p "Open WordPress in browser? [y/N]: " OPEN_BROWSER
    if [ "$OPEN_BROWSER" = "y" ] || [ "$OPEN_BROWSER" = "Y" ]; then
        xdg-open "http://localhost:${WORDPRESS_PORT}" 2>/dev/null || true
    fi
elif command_exists open; then
    read -p "Open WordPress in browser? [y/N]: " OPEN_BROWSER
    if [ "$OPEN_BROWSER" = "y" ] || [ "$OPEN_BROWSER" = "Y" ]; then
        open "http://localhost:${WORDPRESS_PORT}" 2>/dev/null || true
    fi
fi

print_success "Deployment script completed successfully!"
