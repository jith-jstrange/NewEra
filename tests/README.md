# Newera Plugin Unit Tests

Comprehensive unit test suite for Newera WordPress plugin core modules.

## Test Coverage

This test suite provides 80%+ coverage for the following core modules:

### Core Modules
- **Crypto.php** (`CryptoTest.php`) - AES-256-CBC encryption/decryption, IV handling, key derivation
- **StateManager.php** (`StateManagerTest.php`) - Secure credential storage, state management, CRUD operations
- **Bootstrap.php** (`BootstrapTest.php`) - Plugin initialization, component registration, filter exposure

### Database Modules
- **DBAdapterInterface** - Interface contract testing
- **WPDBAdapter** (`WPDBAdapterTest.php`) - WordPress database operations, transactions, prepared statements

### Module System
- **ModuleInterface** - Interface contract testing
- **BaseModule** (`BaseModuleTest.php`) - Base module functionality, credential encryption, state access
- **ModuleRegistry** (`ModuleRegistryTest.php`) - Auto-discovery, lifecycle management, credential isolation

## Running Tests

### All Tests
```bash
composer test
```

### Specific Test File
```bash
vendor/bin/phpunit tests/CryptoTest.php
```

### With Coverage Report
```bash
composer test-coverage
```

This generates an HTML coverage report in the `coverage/` directory.

### Quick Core Tests Only
```bash
composer test-unit
```

Runs only Crypto and StateManager tests for quick validation.

## Test Architecture

### Test Case Base Class
All tests extend `Newera\Tests\TestCase` which provides:
- WordPress function mocking
- Automatic test isolation
- Cleanup after each test
- Common setup/teardown

### Mock Infrastructure
- **MockStorage** - Simulates WordPress options storage
- **MockWPDB** - Simulates WordPress database operations
- **TestModule** - Concrete module implementation for testing

### Test Isolation
- Each test is completely isolated
- No dependencies between tests
- Automatic cleanup in tearDown()
- Mock storage reset between tests

## Test Coverage Requirements

### Minimum 80% Coverage
Tests must cover at least 80% of:
- Lines of code
- Functions/methods
- Branches

### Covered Scenarios
Each module tests:
- **Happy paths** - Normal operation with valid data
- **Edge cases** - Boundary conditions, empty values, special characters
- **Error handling** - Invalid input, missing data, corrupted data
- **Data types** - Strings, arrays, objects, numbers, booleans
- **Security** - Encryption round trips, credential isolation
- **Performance** - Large data sets, deeply nested structures

## Test Examples

### Testing Encryption Round Trip
```php
public function testEncryptDecryptStringData() {
    $original_data = 'Secret message';
    
    $encrypted = $this->crypto->encrypt($original_data);
    $this->assertIsArray($encrypted);
    $this->assertArrayHasKey('iv', $encrypted);
    
    $decrypted = $this->crypto->decrypt($encrypted);
    $this->assertEquals($original_data, $decrypted);
}
```

### Testing Secure Credential Storage
```php
public function testSetSecureAndGetSecure() {
    $module = 'payment_gateway';
    $key = 'api_key';
    $data = 'sk_test_1234567890';
    
    $this->stateManager->setSecure($module, $key, $data);
    $retrieved = $this->stateManager->getSecure($module, $key);
    
    $this->assertEquals($data, $retrieved);
}
```

### Testing Module Lifecycle
```php
public function testModuleBoot() {
    $this->module->setActive(true);
    $this->module->boot();
    
    $this->assertTrue($this->module->isActive());
}
```

## Test Fixtures

### WordPress Constants
Defined in `tests/bootstrap.php`:
- `ABSPATH` - WordPress root path
- `AUTH_KEY`, `SECURE_AUTH_KEY`, etc. - WordPress salts
- `NEWERA_VERSION` - Plugin version
- `NEWERA_PLUGIN_PATH` - Plugin directory path

### WordPress Functions
Mocked in `tests/bootstrap.php`:
- `get_option()`, `update_option()`, `delete_option()`
- `get_site_option()`, `add_site_option()`
- `wp_salt()` - Returns deterministic test salts
- `current_time()` - Returns current timestamp
- `is_admin()`, `add_action()`, `add_filter()`

## Performance Requirements

### Test Execution Time
- Full test suite: < 5 minutes
- Individual test files: < 30 seconds
- Single tests: < 1 second

### Memory Usage
- Tests should not consume excessive memory
- Large data tests verify scalability
- Mock storage is efficient and lightweight

## Best Practices

### 1. Test Naming
- Prefix with `test`
- Use descriptive names: `testEncryptDecryptStringData`
- One concept per test

### 2. Assertions
- Use specific assertions: `assertEquals`, `assertIsArray`
- Assert expected behavior, not implementation
- Multiple related assertions are OK

### 3. Test Data
- Use dummy credentials only (no real API keys)
- Deterministic data (no random values that can't be verified)
- Representative edge cases

### 4. Cleanup
- Always clean up in `tearDown()`
- Don't rely on test execution order
- Reset mock storage

### 5. Documentation
- Add PHPDoc blocks to test methods
- Explain complex test scenarios
- Document expected behavior

## Troubleshooting

### Test Failures
1. Check mock setup in `setUp()`
2. Verify cleanup in `tearDown()`
3. Ensure test isolation (no shared state)
4. Check for missing WordPress function mocks

### Coverage Issues
1. Add tests for uncovered branches
2. Test error conditions
3. Test edge cases and boundary conditions
4. Verify all public methods are tested

### Slow Tests
1. Reduce large data set sizes
2. Mock expensive operations
3. Avoid external API calls
4. Use efficient assertions

## Continuous Integration

Tests are automatically run on:
- Pull request creation
- Push to main branch
- Release tagging

CI checks:
- All tests pass
- 80%+ code coverage
- No PHP errors or warnings
- Tests complete in < 5 minutes

## Contributing

When adding new core modules:
1. Create corresponding test file
2. Extend `TestCase` base class
3. Cover all public methods
4. Test error conditions
5. Ensure 80%+ coverage
6. Update this README

## License

Same license as the Newera plugin (see LICENSE file in project root).
