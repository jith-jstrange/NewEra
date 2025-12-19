#!/bin/bash

# NewEra Performance Testing Environment Setup
# Initializes Docker containers and dependencies
# Usage: ./setup_perf_environment.sh

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DOCKER_COMPOSE_FILE="$PROJECT_DIR/docker-compose.perf.yml"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo "=========================================="
echo "NewEra Performance Testing Setup"
echo "=========================================="
echo ""

# Check prerequisites
echo -e "${YELLOW}Checking prerequisites...${NC}"

# Check Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}✗ Docker not found${NC}"
    echo "Install Docker: https://docs.docker.com/install/"
    exit 1
fi
echo -e "${GREEN}✓ Docker installed${NC}"

# Check Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo -e "${RED}✗ Docker Compose not found${NC}"
    echo "Install Docker Compose: https://docs.docker.com/compose/install/"
    exit 1
fi
echo -e "${GREEN}✓ Docker Compose installed${NC}"

# Check PHP (optional)
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo -e "${GREEN}✓ PHP installed: $PHP_VERSION${NC}"
else
    echo -e "${YELLOW}⚠ PHP not found (required for PHP tests)${NC}"
fi

# Check wrk (optional)
if command -v wrk &> /dev/null; then
    echo -e "${GREEN}✓ wrk installed${NC}"
else
    echo -e "${YELLOW}⚠ wrk not found (required for HTTP load tests)${NC}"
    echo "  Install: sudo apt-get install wrk"
fi

# Check JMeter (optional)
if command -v jmeter &> /dev/null; then
    echo -e "${GREEN}✓ JMeter installed${NC}"
else
    echo -e "${YELLOW}⚠ JMeter not found (required for comprehensive load tests)${NC}"
    echo "  Install: sudo apt-get install jmeter"
fi

echo ""
echo -e "${YELLOW}Setting up Docker environment...${NC}"

# Check if containers already running
if docker-compose -f "$DOCKER_COMPOSE_FILE" ps 2>/dev/null | grep -q "Up"; then
    echo -e "${YELLOW}Docker containers already running${NC}"
    read -p "Stop and restart? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker-compose -f "$DOCKER_COMPOSE_FILE" down
    else
        echo "Using existing containers"
        exit 0
    fi
fi

# Create results directory
mkdir -p "$SCRIPT_DIR/results"
echo -e "${GREEN}✓ Results directory created${NC}"

# Start Docker services
echo "Starting Docker containers (this may take a minute)..."
docker-compose -f "$DOCKER_COMPOSE_FILE" up -d

echo -e "${GREEN}✓ Docker containers started${NC}"
echo ""

# Wait for services
echo -e "${YELLOW}Waiting for services to be ready...${NC}"

# Check WordPress
for i in {1..30}; do
    if curl -s http://localhost:8080/ > /dev/null 2>&1; then
        echo -e "${GREEN}✓ WordPress ready${NC}"
        break
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}✗ WordPress failed to start${NC}"
        exit 1
    fi
    echo "  Attempt $i/30..."
    sleep 2
done

# Check MariaDB
for i in {1..10}; do
    if docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T db mysqladmin ping -h localhost > /dev/null 2>&1; then
        echo -e "${GREEN}✓ MariaDB ready${NC}"
        break
    fi
    if [ $i -eq 10 ]; then
        echo -e "${RED}✗ MariaDB failed to start${NC}"
    fi
    sleep 1
done

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Services Available:"
echo "  - WordPress: http://localhost:8080"
echo "  - Prometheus: http://localhost:9090"
echo "  - Grafana: http://localhost:3000 (admin/admin)"
echo ""
echo "Next Steps:"
echo "  1. Run tests:"
echo "     ./run_performance_tests.sh"
echo ""
echo "  2. Or run individual tests:"
echo "     php BaselinePerformanceTest.php"
echo "     php DatabasePerformanceTest.php"
echo "     php EncryptionPerformanceTest.php"
echo "     php WebhookStressTest.php"
echo "     php StressTest.php"
echo ""
echo "  3. View results:"
echo "     ls -la results/"
echo ""
echo "To stop containers:"
echo "  docker-compose -f docker-compose.perf.yml down"
echo ""
