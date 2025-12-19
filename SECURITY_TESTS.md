# Security Test Suite Documentation

This document describes the comprehensive security test suite for the Newera WordPress plugin.

## Test Coverage Overview

The security test suite includes 8 test classes covering the following areas:

### 1. SecurityEncryptionTest.php
Tests encryption of sensitive data and credential protection.

**Tests include:**
- ✅ Credentials encrypted in wp_options
- ✅ Decryption only works with correct key
- ✅ Invalid keys fail gracefully
- ✅ IV randomization on each encryption
- ✅ No plaintext credentials in logs
- ✅ Encryption metadata preserved
- ✅ Encryption consistency across multiple operations
- ✅ Large credential storage support

**Key assertions:**
- API keys are stored in encrypted form only
- Base64 encoded data is not readable as plaintext
- Each encryption produces unique IV
- Failed decryptions return false safely

### 2. SecurityAuthenticationTest.php
Tests authentication mechanisms, JWT tokens, and permission checks.

**Tests include:**
- ✅ OAuth redirect URLs not hardcoded
- ✅ No developer-owned API keys in code
- ✅ JWT cannot be forged with wrong secret
- ✅ Token expiration enforced
- ✅ Admin-only endpoints enforce permission checks
- ✅ Non-admin requests rejected
- ✅ Refresh token flow works correctly
- ✅ Revoked tokens rejected
- ✅ Session token validation

**Key assertions:**
- Configuration is loaded from StateManager, not hardcoded
- JWT signatures use HS256 algorithm
- Expired tokens throw proper exceptions
- Admin capability checks are enforced
- Tokens can be revoked and blacklisted

### 3. SecurityXSSTest.php
Tests cross-site scripting (XSS) prevention measures.

**Tests include:**
- ✅ Input sanitization for client names
- ✅ Input sanitization for project titles
- ✅ Output escaping in admin pages
- ✅ HTML entities properly encoded
- ✅ Malicious JavaScript in form inputs rejected
- ✅ API response escaping
- ✅ Data attributes XSS prevention
- ✅ URL handling prevents JavaScript protocol
- ✅ JSON response injection prevention
- ✅ CSS injection prevention
- ✅ SVG/XML injection prevention
- ✅ DOM-based XSS prevention

**XSS payloads tested:**
- `<script>alert("XSS")</script>`
- `<img src="x" onerror="alert('XSS'">`
- `<svg onload="alert('XSS'">`
- `javascript:alert("XSS")`
- `<iframe src="javascript:alert('XSS')"></iframe>`
- And many more dangerous patterns

**Key assertions:**
- Dangerous HTML tags are stripped
- Event handlers (onload, onerror, onclick, etc.) are removed
- JavaScript protocol URLs are removed
- Special characters are properly escaped

### 4. SecuritySQLInjectionTest.php
Tests SQL injection prevention mechanisms.

**Tests include:**
- ✅ Prepared statements used in database layer
- ✅ SQL injection attempts with special characters blocked
- ✅ Parameterized queries prevent injection
- ✅ Union-based SQL injection attempts blocked
- ✅ Numeric input validation prevents injection
- ✅ String column input validation
- ✅ Search input SQL injection prevention
- ✅ Meta value queries use prepared statements
- ✅ Order by injection prevention
- ✅ LIMIT injection prevention
- ✅ Table name whitelisting
- ✅ Comment stripping prevents injection

**SQL injection payloads tested:**
- `'; DROP TABLE users; --`
- `1' OR '1'='1`
- `' UNION SELECT NULL,NULL,NULL --`
- `'; WAITFOR DELAY '00:00:05'--`
- And many more dangerous patterns

**Key assertions:**
- All database queries use prepare() or esc_sql()
- Quotes are properly escaped
- SQL keywords are escaped as string literals
- UNION queries cannot be injected
- Only whitelisted columns allowed in ORDER BY

### 5. SecurityCSRFTest.php
Tests cross-site request forgery (CSRF) protection.

**Tests include:**
- ✅ Setup Wizard nonce validation
- ✅ Admin actions require valid nonces
- ✅ Invalid nonces rejected
- ✅ Nonces are action-specific
- ✅ Cross-origin POST requests protected
- ✅ Form submission nonces included
- ✅ AJAX requests validate nonces
- ✅ Nonce expiration (time-based)
- ✅ POST data validation
- ✅ Referer header validation
- ✅ Nonce field in forms

**Key assertions:**
- All forms include nonce fields
- Nonce verification uses wp_verify_nonce()
- Nonces are action-specific
- Modified nonces fail verification
- Empty or missing nonces are rejected

### 6. SecurityWebhookTest.php
Tests webhook signature validation and security.

**Tests include:**
- ✅ Valid webhook signatures pass verification
- ✅ Tampered payloads rejected
- ✅ Missing signatures rejected
- ✅ Wrong secret rejected
- ✅ Signature algorithm verification
- ✅ Webhook timestamp validation (replay attack prevention)
- ✅ Webhook request validation
- ✅ Event type validation
- ✅ Duplicate event prevention
- ✅ Event payload validation
- ✅ Signature timing attack prevention
- ✅ Webhook endpoint security

**Key assertions:**
- Signatures use HMAC-SHA256
- Any change to payload invalidates signature
- Signatures are verified with hash_equals() for timing attack prevention
- Event types are whitelisted
- Duplicate events are detected and prevented

### 7. SecurityCredentialMaskingTest.php
Tests that credentials are not exposed in logs or error messages.

**Tests include:**
- ✅ API keys not logged in errors
- ✅ Stripe keys masked in debug output
- ✅ Linear tokens redacted in logs
- ✅ Notion tokens redacted in logs
- ✅ No credentials in error messages
- ✅ Debug output doesn't expose secrets
- ✅ Exception messages don't leak credentials
- ✅ Callback data doesn't expose credentials
- ✅ Configuration dumps mask credentials
- ✅ Bearer tokens masked in Authorization headers

**Credential patterns masked:**
- Stripe API keys: `sk_test_*`, `sk_live_*`, `rk_test_*`, `rk_live_*`
- Linear API keys: `lin_pk_*`, `lin_sk_*`
- Notion tokens: `secret_*`, `ntn_*`
- Bearer tokens: `Bearer [token]`
- Database passwords
- API secrets

**Key assertions:**
- Credentials are masked with `[MASKED]` or `[REDACTED]`
- Field names are preserved for debugging
- Full credentials never appear in logs
- Exception messages sanitized before logging

### 8. CryptoTest.php (Enhanced)
The existing encryption test suite provides comprehensive coverage of:
- ✅ Encryption/decryption round trips
- ✅ Data type handling (strings, arrays, objects, numbers)
- ✅ Empty data rejection
- ✅ Invalid data handling
- ✅ Special characters and Unicode support
- ✅ Large data handling

## Running the Tests

### Prerequisites
- PHP 7.4+
- PHPUnit 9.0+
- OpenSSL extension
- WordPress salts configured (for testing)

### Execute Tests
```bash
# Run all tests
phpunit

# Run specific test class
phpunit tests/SecurityEncryptionTest.php

# Run specific test method
phpunit tests/SecurityXSSTest.php::SecurityXSSTest::testInputSanitizationForClientNames

# Run with coverage report
phpunit --coverage-html coverage/
```

### Configuration
Tests are configured in `phpunit.xml`:
- Bootstrap: `tests/bootstrap.php`
- Test suites: All files matching `*Test.php` in tests directory
- Code coverage: Includes all files in `includes/` directory

## Test Data

All tests use:
- ✅ Test-only credentials (prefixed with `_test_`)
- ✅ Mock WordPress functions
- ✅ Isolated test environments
- ✅ No external API calls
- ✅ No real credentials or secrets

## Security Assertions Summary

### Encryption
- 13 assertions covering encryption, key management, and IV randomization
- Tests ensure credentials are never stored as plaintext
- Verifies cryptographic strength and proper key derivation

### Authentication
- 12 assertions covering JWT tokens, permissions, and token lifecycle
- Tests ensure proper authorization checks
- Verifies token expiration and revocation

### XSS Prevention
- 30+ assertions testing various XSS vectors
- Tests both input sanitization and output escaping
- Covers DOM, attribute, and protocol-based XSS

### SQL Injection Prevention
- 20+ assertions testing parameterized queries and input validation
- Tests various injection techniques (union-based, time-based, stacked)
- Verifies whitelisting and proper escaping

### CSRF Protection
- 15+ assertions verifying nonce usage
- Tests form submissions and AJAX requests
- Verifies nonce validation and action-specificity

### Webhook Security
- 12+ assertions testing signature validation
- Tests timestamp validation for replay attack prevention
- Verifies event payload validation

### Credential Masking
- 15+ assertions verifying credentials are masked
- Tests various credential patterns (API keys, tokens, passwords)
- Verifies masking in logs, errors, and debug output

## Expected Test Results

All 150+ security assertions should pass, covering:
- ✅ 100% of encryption operations
- ✅ 100% of authentication flows
- ✅ XSS vectors in forms, outputs, and API responses
- ✅ SQL injection in queries, search, and meta fields
- ✅ CSRF in forms and AJAX operations
- ✅ Webhook signature validation
- ✅ Credential masking in all logging contexts

## Continuous Integration

Tests should be run:
- On every commit (via pre-commit hook)
- In CI/CD pipeline before deployment
- As part of regular security audits
- When updating security-related code

## Reporting Security Issues

If a test fails, indicating a potential security issue:
1. **DO NOT commit the code** - Fix the underlying issue
2. **DO NOT disable the test** - Tests enforce security requirements
3. **Investigation Required** - Determine root cause
4. **Fix Implementation** - Address the vulnerability
5. **Verify Tests Pass** - All security tests must pass

## Test Maintenance

Tests should be updated when:
- New security vulnerabilities are discovered
- New credential types are added
- New authentication mechanisms are implemented
- New API endpoints are created
- Security best practices are updated

## References

- [OWASP Testing Guide](https://owasp.org/www-project-web-security-testing-guide/)
- [CWE-79 Cross-site Scripting](https://cwe.mitre.org/data/definitions/79.html)
- [CWE-89 SQL Injection](https://cwe.mitre.org/data/definitions/89.html)
- [CWE-352 Cross-Site Request Forgery](https://cwe.mitre.org/data/definitions/352.html)
- [JWT Best Practices](https://tools.ietf.org/html/rfc8725)
