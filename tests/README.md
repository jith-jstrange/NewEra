# Newera Plugin Test Suite

This directory contains all tests for the Newera WordPress plugin.

## Test Files

### Unit Tests
- **CryptoTest.php** - Encryption and decryption functionality
- **StateManagerTest.php** - State management and option handling
- **AICommandTest.php** - AI command processing

### Security Tests (New)
- **SecurityEncryptionTest.php** - Encryption and credential protection
- **SecurityAuthenticationTest.php** - Authentication, JWT, and permissions
- **SecurityXSSTest.php** - Cross-site scripting (XSS) prevention
- **SecuritySQLInjectionTest.php** - SQL injection prevention
- **SecurityCSRFTest.php** - Cross-site request forgery (CSRF) prevention
- **SecurityWebhookTest.php** - Webhook signature validation
- **SecurityCredentialMaskingTest.php** - Credential exposure prevention

## Test Infrastructure

### Bootstrap
- **bootstrap.php** - Test environment setup with mock WordPress functions
- **TestCase.php** - Base test case class with common setup
- **MockStorage.php** - In-memory storage for WordPress options
- **MockWPDB.php** - Mock WordPress database adapter

## Running Tests

### Run All Tests
```bash
phpunit
```

### Run Specific Test Class
```bash
phpunit tests/SecurityEncryptionTest.php
```

### Run Specific Test Method
```bash
phpunit tests/SecurityXSSTest.php::SecurityXSSTest::testInputSanitizationForClientNames
```

### Run with Coverage Report
```bash
phpunit --coverage-html coverage/
phpunit --coverage-text
```

### Run in Verbose Mode
```bash
phpunit --verbose
```

## Test Statistics

- **Total Test Classes**: 10
- **Total Test Methods**: 87+
- **Total Assertions**: 150+
- **Security Domains Covered**: 7
- **Attack Vectors Tested**: 50+

## Security Test Coverage

### Encryption (11 tests)
- Credential storage in wp_options
- Key derivation and validation
- IV randomization
- Plaintext protection
- Large data handling

### Authentication (10 tests)
- JWT token generation and validation
- Token expiration enforcement
- Permission checking
- Session validation
- Token revocation

### XSS Prevention (11 tests)
- Input sanitization
- Output escaping
- HTML entity encoding
- Event handler removal
- Protocol handler validation

### SQL Injection Prevention (12 tests)
- Prepared statement usage
- Parameterized queries
- Special character escaping
- Comment injection prevention
- Table/column whitelisting

### CSRF Protection (11 tests)
- Nonce generation and validation
- Form protection
- AJAX request security
- Action-specific nonces
- Expiration handling

### Webhook Security (12 tests)
- Signature verification
- Timestamp validation
- Event validation
- Duplicate prevention
- Timing attack mitigation

### Credential Masking (10 tests)
- API key masking
- Token redaction
- Error message sanitization
- Debug output protection
- Log file protection

## Mock WordPress Environment

The test suite includes mocked WordPress functions:
- Option management (`get_option`, `update_option`, etc.)
- User management (`get_user_by`, `user_can`, etc.)
- Sanitization (`sanitize_text_field`, `esc_html`, etc.)
- Time functions (`current_time`, transients, etc.)
- And many more...

See `bootstrap.php` for complete list.

## Test Data

All tests use:
- Test-only credentials (prefixed with `_test_`)
- Mock data that doesn't interact with real services
- Isolated test environments
- No external API calls
- No real database access

## Configuration

Tests are configured in `phpunit.xml`:
- Bootstrap file: `tests/bootstrap.php`
- Test suites: All files matching `*Test.php`
- Code coverage: Includes `includes/` directory
- Colors and verbose output enabled

## Expected Results

When all prerequisites are met:
- ✅ 87+ tests should pass
- ✅ 150+ assertions should pass
- ✅ No failures or errors
- ✅ No skipped tests
- ✅ Fast execution (< 30 seconds)

## Common Test Patterns

### Testing Encryption
```php
$data = 'sensitive';
$encrypted = $this->crypto->encrypt($data);
$decrypted = $this->crypto->decrypt($encrypted);
$this->assertEquals($data, $decrypted);
```

### Testing Security
```php
// Test that dangerous input is rejected
$dangerous = '<script>alert("xss")</script>';
$sanitized = sanitize_text_field($dangerous);
$this->assertNotContains('<script>', $sanitized);
```

### Testing Authorization
```php
// Test that non-admin users are rejected
$user = new MockWPUser(2, 'subscriber');
$this->assertFalse($user->allcaps['manage_options'] ?? false);
```

## Adding New Tests

When adding new tests:
1. Extend `TestCase` class
2. Use proper test naming: `test*` methods
3. Include assertions for positive and negative cases
4. Clean up resources in `tearDown()`
5. Document test intent
6. Use mock data only

Example:
```php
class NewSecurityTest extends TestCase {
    public function testSecurityFeature() {
        // Arrange
        $data = 'test_data';
        
        // Act
        $result = securityFunction($data);
        
        // Assert
        $this->assertTrue($result);
    }
}
```

## Troubleshooting

### Tests Not Running
- Verify PHP version (7.4+)
- Check PHPUnit installation
- Ensure OpenSSL extension loaded
- Verify file permissions

### Specific Test Failing
- Check mock functions are defined
- Verify test data setup
- Check assertion conditions
- Review test documentation

### Coverage Report Issues
- Install Xdebug extension
- Check file permissions for coverage/
- Verify coverage whitelist in phpunit.xml

## Continuous Integration

Tests should run on:
- Pre-commit hooks
- Pull request validation
- CI/CD pipeline
- Regular scheduled audits
- Before production deployment

## Security Validation Checklist

Before merging changes:
- [ ] All 87+ tests pass
- [ ] All 150+ assertions pass
- [ ] No security warnings
- [ ] No credentials in logs
- [ ] No XSS vulnerabilities
- [ ] No SQL injection vulnerabilities
- [ ] CSRF protection verified
- [ ] Encryption validated
- [ ] Authentication enforced

## References

- PHPUnit Documentation: https://phpunit.de/
- WordPress Plugin Testing: https://developer.wordpress.org/plugins/testing/
- OWASP Testing Guide: https://owasp.org/
- CWE-79 (XSS): https://cwe.mitre.org/data/definitions/79.html
- CWE-89 (SQL Injection): https://cwe.mitre.org/data/definitions/89.html
- CWE-352 (CSRF): https://cwe.mitre.org/data/definitions/352.html

## Support

For questions about the test suite:
1. Check this README
2. Review test documentation in SECURITY_TESTS.md
3. Examine test code comments
4. Check bootstrap.php for mock functions
