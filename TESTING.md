# Testing & CI/CD Documentation

## Overview
This project uses automated testing to ensure the stability and security of the Newera WordPress Plugin. The testing pipeline is built on PHPUnit and GitHub Actions.

## Test Suites

We have categorized tests into the following suites:

1.  **Unit Tests** (`tests/Unit`): Fast, isolated tests for individual classes and functions. Mock external dependencies.
2.  **Security Tests** (`tests/Security`): Specific tests for security features, encryption, and access control.
3.  **API Tests** (`tests/API`): Tests for REST/GraphQL endpoints and internal APIs.
4.  **Integration Tests** (`tests/Integration`): Tests that involve multiple components and the database.
5.  **E2E Tests** (`tests/E2E`): End-to-end tests that simulate user flows (slower).

## Running Tests Locally

### Prerequisites
- Docker & Docker Compose
- Composer (optional, if running locally without Docker)

### Using Docker (Recommended)

To run the full test suite in a containerized environment:

```bash
docker-compose -f docker-compose.test.yml up --exit-code-from test-runner
```

This will spin up MySQL, WordPress, and a test runner container, execute the tests, and shut down.

### Running Manually

If you have PHP and Composer installed locally:

```bash
# Install dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run a specific suite
vendor/bin/phpunit --testsuite Unit

# Generate HTML coverage report
vendor/bin/phpunit --coverage-html build/coverage
```

## CI/CD Pipeline

The CI pipeline is defined in `.github/workflows/test.yml` and runs on every Push and Pull Request to `main` or `develop`.

### Pipeline Stages

1.  **Fast Tests** (Parallel):
    *   Unit Tests
    *   Security Tests
    *   API Tests
2.  **Integration Tests**: Runs after Fast Tests pass. Requires MySQL.
3.  **E2E Tests**: Runs after Integration Tests pass.
4.  **Reporting**: Aggregates test results and comments on the PR.
5.  **Notifications**: Sends Slack notifications on failure.

### Coverage

We use Codecov for coverage tracking.
*   **Target Coverage**: 80%
*   **Threshold**: 1% (Build fails if coverage drops by more than 1%)

Coverage reports are generated in `build/logs/clover-*.xml` and uploaded to Codecov.

## Test Writing Guidelines

1.  **Isolation**: Unit tests should not depend on the database or external services. Use Mocks.
2.  **Naming**: Test classes should end with `Test.php`. Test methods should start with `test`.
3.  **Assertions**: Use specific assertions (e.g., `assertSame`, `assertArrayHasKey`) rather than generic `assertTrue`.
4.  **Security**: Always mock sensitive data. Never commit real credentials.
5.  **Flakiness**: Avoid `sleep()` or reliance on execution time. Use deterministic checks.

## Adding New Tests

1.  Identify the type of test (Unit, API, etc.).
2.  Create a new class in the appropriate `tests/Directory`.
3.  Extend `PHPUnit\Framework\TestCase`.
4.  Ensure namespace matches the directory structure (e.g., `Newera\Tests\Unit`).

Example:
```php
namespace Newera\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MyNewTest extends TestCase {
    public function testFeatureWorks() {
        $this->assertTrue(true);
    }
}
```
