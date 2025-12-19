#!/bin/bash
# API Test Runner Script
# Usage: ./run-tests.sh [test-suite]
# Examples:
#   ./run-tests.sh              # Run all tests
#   ./run-tests.sh rest         # Run REST API tests only
#   ./run-tests.sh auth         # Run authentication tests only
#   ./run-tests.sh middleware   # Run middleware tests only
#   ./run-tests.sh webhooks     # Run webhook tests only
#   ./run-tests.sh graphql      # Run GraphQL tests only

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo -e "${YELLOW}Installing dependencies...${NC}"
    composer install --no-interaction
fi

# Determine which tests to run
TEST_SUITE=${1:-all}

echo -e "${GREEN}Running Newera API Tests${NC}"
echo "========================================"

case $TEST_SUITE in
    all)
        echo "Running all test suites..."
        vendor/bin/phpunit --testdox
        ;;
    rest)
        echo "Running REST API tests..."
        vendor/bin/phpunit --testdox tests/RESTControllerTest.php
        ;;
    auth)
        echo "Running Authentication tests..."
        vendor/bin/phpunit --testdox tests/AuthManagerTest.php
        ;;
    middleware)
        echo "Running Middleware (CORS, Rate Limiting) tests..."
        vendor/bin/phpunit --testdox tests/MiddlewareManagerTest.php
        ;;
    webhooks)
        echo "Running Webhook tests..."
        vendor/bin/phpunit --testdox tests/WebhookManagerTest.php
        ;;
    graphql)
        echo "Running GraphQL tests..."
        vendor/bin/phpunit --testdox tests/GraphQLControllerTest.php
        ;;
    coverage)
        echo "Running tests with coverage report..."
        vendor/bin/phpunit --coverage-html coverage
        echo -e "${GREEN}Coverage report generated in coverage/index.html${NC}"
        ;;
    *)
        echo -e "${RED}Unknown test suite: $TEST_SUITE${NC}"
        echo "Available test suites: all, rest, auth, middleware, webhooks, graphql, coverage"
        exit 1
        ;;
esac

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
else
    echo -e "${RED}✗ Some tests failed${NC}"
fi

exit $EXIT_CODE
