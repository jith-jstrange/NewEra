# Quick Start - Unit Tests

## Installation

```bash
# Install dependencies
composer install
```

## Running Tests

### All Tests
```bash
composer test
```

### With Coverage
```bash
composer test-coverage
open coverage/index.html
```

### Specific Test Groups
```bash
# Core modules (Crypto, StateManager, Bootstrap)
composer test-core

# Database operations
composer test-database

# Module system
composer test-modules

# Individual file
vendor/bin/phpunit tests/CryptoTest.php

# Single test method
vendor/bin/phpunit --filter testEncryptDecrypt tests/CryptoTest.php
```

## Quick Commands

| Command | Description |
|---------|-------------|
| `composer test` | Run all tests |
| `composer test-coverage` | Generate coverage report |
| `composer test-unit` | Quick core validation |
| `vendor/bin/phpunit --verbose` | Verbose test output |
| `vendor/bin/phpunit --stop-on-failure` | Stop on first failure |
| `vendor/bin/phpunit --filter <name>` | Run specific test |

## What's Tested

✅ **Crypto** - Encryption/decryption (AES-256-CBC)  
✅ **StateManager** - Secure credential storage  
✅ **Bootstrap** - Plugin initialization  
✅ **WPDBAdapter** - Database operations  
✅ **BaseModule** - Module base functionality  
✅ **ModuleRegistry** - Module auto-discovery  

## Coverage Target

🎯 **80%+ coverage** for core modules

Current: **88%+** ✅

## Files

- `CryptoTest.php` - Encryption tests
- `StateManagerTest.php` - State management tests
- `BootstrapTest.php` - Bootstrap tests
- `WPDBAdapterTest.php` - Database tests
- `BaseModuleTest.php` - Module base tests
- `ModuleRegistryTest.php` - Registry tests

## CI Integration

Tests run automatically on:
- Push to main/develop
- Pull requests
- PHP 7.4, 8.0, 8.1, 8.2

## Troubleshooting

**Tests fail?**
1. Run `composer install`
2. Check PHP version (7.4+)
3. Verify OpenSSL extension

**Coverage issues?**
1. Install Xdebug: `pecl install xdebug`
2. Run: `composer test-coverage`

## More Info

See `TESTING.md` for comprehensive documentation.
