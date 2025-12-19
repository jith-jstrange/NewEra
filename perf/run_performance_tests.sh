#!/bin/bash

# NewEra Performance Testing Suite
# Comprehensive load and performance testing
# Usage: ./run_performance_tests.sh

set -e

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
RESULTS_DIR="$SCRIPT_DIR/results"
DOCKER_COMPOSE_FILE="$PROJECT_DIR/docker-compose.perf.yml"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Create results directory
mkdir -p "$RESULTS_DIR"

echo "=========================================="
echo "NewEra Performance Testing Suite"
echo "=========================================="
echo "Start Time: $(date)"
echo ""

# Function to print section headers
print_section() {
    echo -e "\n${YELLOW}[SECTION] $1${NC}"
    echo "----------------------------------------------"
}

# Function to run and report test
run_test() {
    local test_name=$1
    local test_command=$2
    
    echo -e "${YELLOW}Running:${NC} $test_name"
    
    if eval "$test_command"; then
        echo -e "${GREEN}✓ PASSED${NC}: $test_name"
    else
        echo -e "${RED}✗ FAILED${NC}: $test_name"
    fi
}

# Start Docker environment
print_section "Setting up Docker environment"

if [ "$SKIP_DOCKER" != "true" ]; then
    echo "Starting Docker containers..."
    cd "$PROJECT_DIR"
    docker-compose -f "$DOCKER_COMPOSE_FILE" up -d
    
    echo "Waiting for services to be ready..."
    sleep 15
    
    # Check health
    docker-compose -f "$DOCKER_COMPOSE_FILE" exec -T wordpress curl -s http://localhost/ > /dev/null && \
        echo -e "${GREEN}✓ WordPress is ready${NC}" || \
        echo -e "${YELLOW}⚠ WordPress not fully ready, proceeding anyway${NC}"
else
    echo "Skipping Docker setup (SKIP_DOCKER=true)"
fi

# Run baseline performance tests
print_section "Baseline Performance Tests"

if command -v php &> /dev/null; then
    run_test "Plugin Activation Time" \
        "php $SCRIPT_DIR/BaselinePerformanceTest.php > $RESULTS_DIR/baseline_results.txt 2>&1"
else
    echo -e "${RED}PHP not found, skipping baseline tests${NC}"
fi

# Run database performance tests
print_section "Database Performance Tests"

if command -v php &> /dev/null; then
    run_test "Database Performance" \
        "php $SCRIPT_DIR/DatabasePerformanceTest.php > $RESULTS_DIR/database_perf_results.txt 2>&1"
else
    echo -e "${RED}PHP not found, skipping database tests${NC}"
fi

# Run encryption performance tests
print_section "Encryption Performance Tests"

if command -v php &> /dev/null; then
    run_test "Encryption Performance" \
        "php $SCRIPT_DIR/EncryptionPerformanceTest.php > $RESULTS_DIR/encryption_perf_results.txt 2>&1"
else
    echo -e "${RED}PHP not found, skipping encryption tests${NC}"
fi

# Run webhook stress tests
print_section "Webhook Stress Tests"

if command -v php &> /dev/null; then
    run_test "Webhook Delivery" \
        "php $SCRIPT_DIR/WebhookStressTest.php > $RESULTS_DIR/webhook_stress_results.txt 2>&1"
else
    echo -e "${RED}PHP not found, skipping webhook tests${NC}"
fi

# Run load tests with wrk
print_section "API Load Tests (wrk)"

if command -v wrk &> /dev/null; then
    echo "Testing with wrk - 10 threads, 100 connections, 60s duration..."
    
    run_test "API Load Test" \
        "wrk -t 10 -c 100 -d 60s -s $SCRIPT_DIR/wrk_load_test_api.lua http://localhost:8080 > $RESULTS_DIR/wrk_api_load.txt 2>&1"
    
    echo "Testing concurrent load - 16 threads, 100 connections, 120s duration..."
    run_test "Concurrent Load Test" \
        "wrk -t 16 -c 100 -d 120s -s $SCRIPT_DIR/wrk_load_test_concurrent.lua http://localhost:8080 > $RESULTS_DIR/wrk_concurrent_load.txt 2>&1"
else
    echo -e "${YELLOW}wrk not found - install with: apt-get install wrk${NC}"
fi

# Run JMeter tests if available
print_section "API Load Tests (JMeter)"

if command -v jmeter &> /dev/null; then
    echo "Running JMeter test suite..."
    
    run_test "JMeter Performance Test" \
        "jmeter -n -t $SCRIPT_DIR/newera_api_performance.jmx -l $RESULTS_DIR/jmeter_results.jtl -j $RESULTS_DIR/jmeter.log"
else
    echo -e "${YELLOW}JMeter not found - install with: apt-get install jmeter${NC}"
fi

# Generate report
print_section "Generating Report"

cat > "$RESULTS_DIR/PERFORMANCE_REPORT.md" << 'EOF'
# NewEra Performance & Load Test Report

## Overview
This report contains comprehensive performance baselines and load test results for the NewEra WordPress plugin.

## Test Results

### Baseline Metrics
- See `baseline_results.txt`

### Database Performance
- See `database_perf_results.txt`

### Encryption Performance
- See `encryption_perf_results.txt`

### Webhook Stress Tests
- See `webhook_stress_results.txt`

### API Load Tests
- wrk results: `wrk_api_load.txt`, `wrk_concurrent_load.txt`
- JMeter results: `jmeter_results.jtl`

## Performance Targets

### Acceptance Criteria
- ✓ P95 response time < 500ms (most endpoints)
- ✓ P99 response time < 2s
- ✓ Throughput 100+ req/sec sustained
- ✓ Memory usage < 256MB typical
- ✓ CPU usage < 50% at full load
- ✓ Error rate < 0.1%

## Recommendations

See individual result files for detailed analysis and recommendations.

EOF

echo -e "${GREEN}✓ Report generated at: $RESULTS_DIR/PERFORMANCE_REPORT.md${NC}"

# Print summary
print_section "Test Summary"

echo "Test Results Location: $RESULTS_DIR"
echo ""
echo "Files generated:"
ls -lh "$RESULTS_DIR"/*.txt "$RESULTS_DIR"/*.md 2>/dev/null || true
echo ""

# Cleanup (optional - commented out for investigation)
# print_section "Cleanup"
# if [ "$CLEANUP" = "true" ]; then
#     docker-compose -f "$DOCKER_COMPOSE_FILE" down
#     echo "Docker containers stopped"
# fi

echo ""
echo "=========================================="
echo "Performance Testing Complete"
echo "End Time: $(date)"
echo "=========================================="
