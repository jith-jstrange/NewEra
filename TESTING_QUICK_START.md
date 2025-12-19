# Testing Quick Start Guide

## 🚀 Quick Start

### Run All Tests
```bash
./run-tests.sh
# or
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# REST API tests
./run-tests.sh rest

# Authentication tests
./run-tests.sh auth

# Middleware (CORS, Rate Limiting) tests
./run-tests.sh middleware

# Webhook tests
./run-tests.sh webhooks

# GraphQL tests
./run-tests.sh graphql
```

### Run with Coverage Report
```bash
./run-tests.sh coverage
# Open coverage/index.html in browser
```

## 📊 Test Statistics

| Test Suite | Tests | Lines of Code | Coverage |
|------------|-------|---------------|----------|
| RESTControllerTest | 20+ | 700+ | REST endpoints |
| AuthManagerTest | 15+ | 550+ | JWT auth |
| MiddlewareManagerTest | 25+ | 650+ | CORS, rate limiting |
| WebhookManagerTest | 30+ | 750+ | Webhook delivery |
| GraphQLControllerTest | 40+ | 850+ | GraphQL API |
| **Total** | **130+** | **3500+** | **All APIs** |

## ✅ What's Tested

### REST API (RESTControllerTest.php)
- ✅ All CRUD operations (GET, POST, PUT, PATCH, DELETE)
- ✅ Status codes (200, 201, 204, 400, 403, 404, 429, 500)
- ✅ Pagination (limit/offset)
- ✅ Filtering & sorting
- ✅ Request validation & sanitization
- ✅ Response format verification

**Endpoints:**
- `/clients` - Client management
- `/projects` - Project management
- `/subscriptions` - Subscription management
- `/settings` - Settings management
- `/webhooks` - Webhook management
- `/activity` - Activity logs

### Authentication (AuthManagerTest.php)
- ✅ JWT token generation
- ✅ JWT token verification
- ✅ Token expiration
- ✅ Token refresh
- ✅ Token blacklisting
- ✅ Permission checks (user vs admin)
- ✅ Rate limiting per user

### Middleware (MiddlewareManagerTest.php)
- ✅ CORS allowed origins
- ✅ CORS denied origins
- ✅ CORS wildcard support
- ✅ Rate limiting per namespace
- ✅ Rate limiting per user
- ✅ Rate limit quota reset
- ✅ Request/response logging

**Rate Limits:**
- `clients`: 500 req/hour
- `projects`: 300 req/hour
- `subscriptions`: 200 req/hour
- `settings`: 50 req/hour
- `webhooks`: 100 req/hour
- `activity`: 1000 req/hour

### Webhooks (WebhookManagerTest.php)
- ✅ Webhook CRUD operations
- ✅ Successful delivery
- ✅ Failed delivery handling
- ✅ Retry logic (3 attempts)
- ✅ Exponential backoff (5min, 30min, 2hr)
- ✅ Signature generation (HMAC-SHA256)
- ✅ Signature validation
- ✅ Event filtering

### GraphQL (GraphQLControllerTest.php)
- ✅ Query resolution
- ✅ Mutation execution
- ✅ Schema validation
- ✅ Connection pattern pagination
- ✅ Cursor-based pagination
- ✅ Error handling
- ✅ Introspection
- ✅ Fragments
- ✅ Variables

## 🎯 Test Execution Time

Expected execution times:

- **RESTControllerTest**: ~30 seconds
- **AuthManagerTest**: ~20 seconds
- **MiddlewareManagerTest**: ~40 seconds
- **WebhookManagerTest**: ~50 seconds
- **GraphQLControllerTest**: ~60 seconds

**Total**: ~3.5 minutes (well under 10 minute target)

## 🔧 Prerequisites

### Required
- PHP 7.4 or higher
- Composer

### Install Dependencies
```bash
composer install
```

### Dependencies Installed
- `phpunit/phpunit` - Testing framework
- `firebase/php-jwt` - JWT authentication
- `webonyx/graphql-php` - GraphQL implementation
- `stripe/stripe-php` - Stripe integration (mocked in tests)

## 📁 Test File Structure

```
tests/
├── RESTControllerTest.php      # REST API endpoint tests
├── AuthManagerTest.php         # JWT authentication tests
├── MiddlewareManagerTest.php   # CORS & rate limiting tests
├── WebhookManagerTest.php      # Webhook delivery tests
├── GraphQLControllerTest.php   # GraphQL API tests
├── TestCase.php                # Base test class
├── bootstrap.php               # Test bootstrap
├── MockStorage.php             # Mock storage helper
└── MockWPDB.php                # Mock WordPress DB
```

## 🚦 CI/CD Integration

### GitHub Actions
Tests run automatically on:
- Push to `main`, `develop`, or test branches
- Pull requests to `main` or `develop`

See `.github/workflows/api-tests.yml`

### Local Pre-commit Hook
```bash
# .git/hooks/pre-commit
#!/bin/bash
./run-tests.sh || exit 1
```

## 🐛 Debugging Tests

### Run Single Test
```bash
vendor/bin/phpunit --filter testGetClientsReturns200
```

### Run with Verbose Output
```bash
vendor/bin/phpunit --testdox --verbose
```

### Run with Debug Output
```bash
vendor/bin/phpunit --debug
```

### Run and Stop on First Failure
```bash
vendor/bin/phpunit --stop-on-failure
```

## 📝 Writing New Tests

### Example Test
```php
public function testNewFeature() {
    // Arrange - Set up test data
    $this->state_manager->method('get_item')
        ->willReturn(['id' => 1, 'name' => 'Test']);
    
    // Act - Execute the code
    $result = $this->controller->doSomething();
    
    // Assert - Verify the result
    $this->assertEquals('expected', $result);
}
```

### Mocking WordPress Functions
```php
if (!function_exists('wp_custom_function')) {
    function wp_custom_function($arg) {
        return 'mocked_value';
    }
}
```

### Using Test Doubles
```php
// Create a mock
$mock = $this->createMock(ClassName::class);

// Set expectations
$mock->expects($this->once())
     ->method('methodName')
     ->with($this->equalTo('arg'))
     ->willReturn('result');
```

## 🎓 Best Practices

1. **Keep tests fast** - Use mocks, avoid I/O
2. **Test one thing** - Each test should test one behavior
3. **Use descriptive names** - `testGetClientsReturns200WithValidAuth`
4. **AAA pattern** - Arrange, Act, Assert
5. **Clean up** - Use `tearDown()` to reset state
6. **Test edge cases** - Empty data, null values, large datasets
7. **Mock external dependencies** - No live API calls

## 📚 Additional Resources

- [API_TESTS.md](./API_TESTS.md) - Detailed test documentation
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [REST API Documentation](./docs/rest-api.md)
- [GraphQL API Documentation](./docs/graphql-api.md)

## 🆘 Troubleshooting

### Tests not running?
```bash
# Check PHP version
php -v

# Check composer
composer --version

# Reinstall dependencies
rm -rf vendor
composer install
```

### Import errors?
```bash
# Regenerate autoload
composer dump-autoload
```

### Memory errors?
```bash
# Increase PHP memory limit
php -d memory_limit=512M vendor/bin/phpunit
```

## ✨ Next Steps

1. Run the tests: `./run-tests.sh`
2. Check coverage: `./run-tests.sh coverage`
3. Review test output
4. Add new tests as features are developed
5. Keep tests green! 🟢
