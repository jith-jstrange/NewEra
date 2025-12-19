# Newera Plugin Integration Tests

This directory contains comprehensive integration tests for the Newera WordPress plugin, testing module interactions and critical workflows across the entire system.

## Overview

Integration tests verify that different modules work correctly together and that data flows properly between components. These tests simulate real-world usage scenarios and external service interactions.

## Test Structure

### Core Integration Test Classes

1. **IntegrationTestCase** - Base class for all integration tests
   - Provides mock WordPress environment setup
   - Handles external service API mocking
   - Manages test data and cleanup

2. **SetupWizardIntegrationTest** - Tests complete wizard workflows
   - Wizard → StateManager → Crypto flow
   - Multi-step wizard completion
   - Data persistence across steps
   - OAuth provider configuration
   - Credential encryption

3. **AuthenticationIntegrationTest** - Tests authentication flows
   - Better-Auth → WordPress user synchronization
   - OAuth provider integration
   - Magic Link authentication
   - Role assignment logic
   - Session management

4. **DatabaseIntegrationTest** - Tests database operations
   - WordPress database initialization
   - External database adapter
   - Query routing and failover
   - Database switching
   - Data consistency

5. **PaymentsIntegrationTest** - Tests payment workflows
   - Stripe integration setup
   - Plan creation and sync
   - Webhook processing
   - Subscription state transitions
   - Invoice handling

6. **ProjectSyncIntegrationTest** - Tests project synchronization
   - Linear API integration
   - Notion database mapping
   - Bidirectional sync
   - Conflict resolution
   - Activity logging

7. **AIIntegrationTest** - Tests AI functionality
   - Provider configuration (OpenAI, Anthropic)
   - API integration and responses
   - Rate limiting
   - Cost tracking
   - Command execution

8. **CredentialIsolationTest** - Tests security
   - Module credential isolation
   - Cross-module access prevention
   - Namespace collision prevention
   - Compromise scenarios

## Mock Classes

### MockWPDB
Simulates WordPress database operations for testing without a real database.

### MockStorage
Simulates WordPress options and storage mechanisms.

### MockHTTP
Mocks external API calls to prevent live service interactions during testing.

## Running Integration Tests

### Command Line
```bash
# Run all integration tests
./tests/run_integration_tests.php

# Run specific test file
php vendor/bin/phpunit tests/Integration/SetupWizardIntegrationTest.php

# Run with PHPUnit directly
php vendor/bin/phpunit -c phpunit.xml tests/Integration/
```

### Test Configuration
- PHP 7.4+ required
- PHPUnit 9.0+ required
- Composer dependencies recommended
- No live external API calls (all mocked)

## Test Scenarios Covered

### Setup Wizard Workflows
✅ Complete wizard flow from intro to completion  
✅ Data persistence across wizard steps  
✅ Wizard resume functionality  
✅ OAuth provider selection and configuration  
✅ Credential encryption and storage  
✅ Multi-module credential management  

### Authentication Flows
✅ OAuth provider → WordPress user sync  
✅ Magic Link authentication  
✅ Role assignment (admin vs client)  
✅ Session token generation  
✅ Multiple provider linking  
✅ Authentication failure handling  

### Database Operations
✅ WordPress DB initialization  
✅ External database setup  
✅ Query routing through adapters  
✅ Failover when external DB down  
✅ Mid-operation database switching  
✅ Data consistency verification  

### Payment Processing
✅ Stripe API integration  
✅ Plan creation and synchronization  
✅ Webhook processing  
✅ Subscription state transitions  
✅ Invoice email delivery  
✅ Payment failure handling  

### Project Synchronization
✅ Linear API connection and data fetch  
✅ Notion database integration  
✅ Bidirectional sync  
✅ Conflict resolution strategies  
✅ Activity logging  
✅ Bulk operations  

### AI Integration
✅ Provider selection and configuration  
✅ API calls to OpenAI and Anthropic  
✅ Rate limiting enforcement  
✅ Cost tracking and accumulation  
✅ Command execution  
✅ Error handling and fallback  

### Security & Isolation
✅ Credential isolation between modules  
✅ Namespace collision prevention  
✅ Compromise scenario handling  
✅ Access control verification  
✅ Encryption/decryption verification  

## Mock External Services

### Linear API
- Issue creation and management
- Project synchronization
- Webhook handling

### Stripe API
- Subscription management
- Webhook processing
- Invoice handling

### OpenAI/Anthropic APIs
- Chat completions
- Embeddings generation
- Rate limiting simulation

### Notion API
- Database operations
- Page creation and updates

## Test Data Management

### Automatic Cleanup
- Each test cleans up after itself
- Mock storage is reset between tests
- No persistent test data

### Isolation
- Tests run in isolated environments
- No shared state between tests
- Consistent results every run

## Coverage Areas

### Critical Workflows
- End-to-end user onboarding
- Payment processing flows
- Data synchronization processes
- Authentication journeys

### Edge Cases
- API failures and timeouts
- Network connectivity issues
- Invalid data handling
- Rate limit scenarios

### Security
- Credential access controls
- Authentication bypass attempts
- Data encryption verification
- Cross-module isolation

## Continuous Integration

Integration tests are designed to run in CI/CD environments:
- No external dependencies required
- Predictable and reproducible results
- Fast execution (sub-second per test)
- Clear pass/fail reporting

## Mock Limitations

While comprehensive, the mock classes have some limitations:
- Some complex API edge cases may not be fully simulated
- Rate limiting is simplified
- Webhook signature validation is mocked
- Complex nested data structures may be simplified

## Future Enhancements

Potential improvements for integration tests:
- Docker-based test environment
- Real database integration for specific tests
- More sophisticated API simulation
- Performance testing integration
- Load testing scenarios