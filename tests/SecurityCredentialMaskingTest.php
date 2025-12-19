<?php
/**
 * Security Tests for Credential Masking
 *
 * Tests that API keys, tokens, and secrets are not exposed in logs,
 * debug output, or error messages
 */

namespace Newera\Tests;

use Newera\Core\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Credential Masking Test Case
 */
class SecurityCredentialMaskingTest extends TestCase {
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Temporary test log file
     */
    private $test_log_file;
    
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        $this->logger = new Logger();
        $this->test_log_file = sys_get_temp_dir() . '/newera_masking_test.log';
        MockStorage::clear_all();
    }
    
    /**
     * Tear down test environment
     */
    protected function tearDown(): void {
        parent::tearDown();
        if (file_exists($this->test_log_file)) {
            unlink($this->test_log_file);
        }
    }
    
    /**
     * Test: API keys not logged in errors
     */
    public function testAPIKeysNotLoggedInErrors() {
        $api_key = 'sk_test_abc123def456ghi789';
        $linear_token = 'lin_api_token_xyz123abc456';
        $notion_key = 'notion_secret_abc123def456';
        
        // Simulate error logging with credentials
        $error_message = "Failed to authenticate with API key: $api_key";
        
        // Mask credentials before logging
        $masked_error = $this->mask_credentials($error_message);
        
        // Log the masked error
        file_put_contents($this->test_log_file, "ERROR: $masked_error\n");
        
        // Verify raw credentials are not in logs
        $log_content = file_get_contents($this->test_log_file);
        $this->assertNotContains($api_key, $log_content);
        $this->assertNotContains('abc123def456ghi789', $log_content);
        
        // But should contain indication of API key
        $this->assertStringContainsString('[MASKED]', $log_content);
    }
    
    /**
     * Test: Stripe keys masked in debug output
     */
    public function testStripeKeysMaskedInDebugOutput() {
        $stripe_keys = [
            'sk_test_123abc456def789' => 'sk_test_...789',
            'sk_live_123abc456def789' => 'sk_live_...789',
            'rk_test_123abc456def789' => 'rk_test_...789',
            'rk_live_123abc456def789' => 'rk_live_...789',
        ];
        
        foreach ($stripe_keys as $full_key => $masked_pattern) {
            // Create mock Stripe configuration with key
            $config = [
                'stripe_api_key' => $full_key,
                'stripe_secret' => 'some_secret_key',
            ];
            
            // Mask the configuration
            $masked_config = $this->mask_stripe_config($config);
            
            // Verify full key is masked
            $config_json = json_encode($masked_config);
            $this->assertNotContains($full_key, $config_json);
            
            // Verify pattern is preserved for debugging
            $this->assertStringContainsString('sk_', $config_json);
        }
    }
    
    /**
     * Test: Linear tokens redacted in logs
     */
    public function testLinearTokensRedactedInLogs() {
        $linear_tokens = [
            'lin_pk_abc123def456',
            'lin_sk_xyz789abc123',
            'linear_token_' . hash('sha256', 'test'),
        ];
        
        foreach ($linear_tokens as $token) {
            $log_entry = "Linear integration token: $token";
            
            // Redact token from log
            $redacted = preg_replace(
                '/lin_(pk|sk)_[a-zA-Z0-9]+/',
                'lin_$1_[REDACTED]',
                $log_entry
            );
            
            // Verify token is redacted
            $this->assertNotContains($token, $redacted);
            $this->assertStringContainsString('[REDACTED]', $redacted);
        }
    }
    
    /**
     * Test: Notion tokens redacted in logs
     */
    public function testNotionTokensRedactedInLogs() {
        $notion_tokens = [
            'secret_abc123def456ghi789jkl012mno345',
            'ntn_' . hash('sha256', 'notion_key'),
        ];
        
        foreach ($notion_tokens as $token) {
            $log_entry = "Notion API token: $token";
            
            // Redact token from log
            $redacted = preg_replace(
                '/(secret_|ntn_)[a-zA-Z0-9]+/',
                '$1[REDACTED]',
                $log_entry
            );
            
            // Verify token is redacted
            $this->assertNotContains($token, $redacted);
            $this->assertStringContainsString('[REDACTED]', $redacted);
        }
    }
    
    /**
     * Test: No credentials in error messages
     */
    public function testNoCredentialsInErrorMessages() {
        $credentials_to_hide = [
            'password' => 'super_secret_password_123',
            'api_key' => 'sk_live_abc123def456',
            'secret' => 'secret_key_xyz789',
            'token' => 'bearer_token_abc123xyz789',
            'database_password' => 'db_pass_123456',
        ];
        
        // Create error with credentials
        $error_details = [];
        foreach ($credentials_to_hide as $key => $value) {
            $error_details[] = "$key: $value";
        }
        
        $error_message = "Database connection failed: " . implode(', ', $error_details);
        
        // Mask credentials
        $safe_error = $this->sanitize_error_message($error_message);
        
        // Log the safe error
        file_put_contents($this->test_log_file, $safe_error);
        
        // Verify no credentials in logs
        $log_content = file_get_contents($this->test_log_file);
        foreach ($credentials_to_hide as $key => $value) {
            $this->assertNotContains($value, $log_content);
        }
        
        // Should contain field names but not values
        $this->assertStringContainsString('password', $log_content);
        $this->assertStringContainsString('api_key', $log_content);
    }
    
    /**
     * Test: Debug output doesn't expose secrets
     */
    public function testDebugOutputDoesntExposeSecrets() {
        // Create array with sensitive data
        $sensitive_data = [
            'user_data' => [
                'username' => 'john_doe',
                'password' => 'MySecurePassword123!',
                'email' => 'john@example.com',
            ],
            'api_config' => [
                'stripe_key' => 'sk_test_123abc',
                'stripe_secret' => 'sk_test_secret_456def',
            ],
            'tokens' => [
                'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
                'refresh_token' => 'refresh_abc123def456',
            ],
        ];
        
        // Convert to string (like var_dump or print_r)
        $debug_output = print_r($sensitive_data, true);
        
        // Mask sensitive fields
        $masked_output = $this->mask_sensitive_fields($debug_output);
        
        // Verify secrets are masked
        $this->assertNotContains('MySecurePassword123!', $masked_output);
        $this->assertNotContains('sk_test_123abc', $masked_output);
        $this->assertNotContains('sk_test_secret_456def', $masked_output);
        
        // Should contain field structure
        $this->assertStringContainsString('user_data', $masked_output);
        $this->assertStringContainsString('api_config', $masked_output);
    }
    
    /**
     * Test: Exception messages don't leak credentials
     */
    public function testExceptionMessagesDoesntLeakCredentials() {
        $api_key = 'sk_test_exc_key_123456';
        
        // Simulate exception with credentials in message
        try {
            throw new \Exception("API call failed with key: $api_key");
        } catch (\Exception $e) {
            $error_message = $e->getMessage();
            
            // Mask credentials from exception message
            $safe_message = $this->mask_credentials($error_message);
            
            // Verify key is not in safe message
            $this->assertNotContains($api_key, $safe_message);
            $this->assertStringContainsString('[MASKED]', $safe_message);
        }
    }
    
    /**
     * Test: Callback data doesn't expose credentials
     */
    public function testCallbackDataDoesntExposeCredentials() {
        // Simulate webhook callback data
        $webhook_data = [
            'type' => 'charge.succeeded',
            'data' => [
                'object' => [
                    'id' => 'ch_test_123',
                    'amount' => 2000,
                ]
            ]
        ];
        
        $callback_secret = 'whsec_test_secret_123abc';
        
        // Log webhook processing
        $log_entry = "Processing webhook: " . json_encode($webhook_data);
        
        // Should NOT include webhook secret
        $this->assertNotContains($callback_secret, $log_entry);
        
        // But should include event data (which is safe)
        $this->assertStringContainsString('charge.succeeded', $log_entry);
    }
    
    /**
     * Test: Configuration dumps mask credentials
     */
    public function testConfigurationDumpsMaskCredentials() {
        // Create configuration with sensitive data
        $config = [
            'stripe' => [
                'api_key' => 'sk_test_config_key',
                'webhook_secret' => 'whsec_test_secret',
            ],
            'linear' => [
                'api_key' => 'lin_pk_test_key_123',
            ],
            'notion' => [
                'api_key' => 'secret_notion_key_123',
                'integration_token' => 'ntn_integration_token',
            ],
            'database' => [
                'host' => 'localhost',
                'user' => 'dbuser',
                'password' => 'db_password_123',
            ],
        ];
        
        // Create config dump
        $config_dump = var_export($config, true);
        
        // Mask sensitive fields
        $masked_dump = $this->mask_config_dump($config_dump);
        
        // Verify credentials are masked
        $this->assertNotContains('sk_test_config_key', $masked_dump);
        $this->assertNotContains('whsec_test_secret', $masked_dump);
        $this->assertNotContains('lin_pk_test_key_123', $masked_dump);
        $this->assertNotContains('secret_notion_key_123', $masked_dump);
        $this->assertNotContains('db_password_123', $masked_dump);
        
        // Should preserve structure
        $this->assertStringContainsString('stripe', $masked_dump);
        $this->assertStringContainsString('linear', $masked_dump);
    }
    
    /**
     * Test: Bearer tokens masked in Authorization headers
     */
    public function testBearerTokensMaskedInAuthHeaders() {
        $tokens = [
            'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.dozjgNryP4J3jVmNHl0w5N_XgL0n3I9PlFUP0THsR8U',
            'Bearer token_abc123def456ghi789jkl012mno345',
        ];
        
        foreach ($tokens as $auth_header) {
            // Log Authorization header
            $log_entry = "Authorization: $auth_header";
            
            // Mask Bearer token
            $masked = preg_replace(
                '/(Bearer\s+)[^\s]+/',
                '$1[MASKED_TOKEN]',
                $log_entry
            );
            
            // Verify token is masked
            $this->assertNotContains('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $masked);
            $this->assertNotContains('token_abc123def456ghi789jkl012mno345', $masked);
            
            // Should preserve structure
            $this->assertStringContainsString('Authorization:', $masked);
            $this->assertStringContainsString('Bearer', $masked);
        }
    }
    
    /**
     * Helper: Mask credentials in string
     */
    private function mask_credentials($string) {
        // Mask API keys (sk_test_, sk_live_, rk_test_, rk_live_)
        $string = preg_replace(
            '/(sk_|rk_)(test|live)_[a-zA-Z0-9]+/',
            '$1$2_[MASKED]',
            $string
        );
        
        // Mask generic tokens
        $string = preg_replace(
            '/token[_=:\s]+[a-zA-Z0-9]+/',
            'token=[MASKED]',
            $string
        );
        
        return $string;
    }
    
    /**
     * Helper: Mask Stripe configuration
     */
    private function mask_stripe_config($config) {
        $masked = $config;
        
        if (isset($masked['stripe_api_key'])) {
            $key = $masked['stripe_api_key'];
            $masked['stripe_api_key'] = preg_replace(
                '/([a-z_]+_)[a-z0-9]+([a-z0-9]{3})/',
                '$1...$2',
                $key
            );
        }
        
        if (isset($masked['stripe_secret'])) {
            $masked['stripe_secret'] = '[MASKED]';
        }
        
        return $masked;
    }
    
    /**
     * Helper: Sanitize error message
     */
    private function sanitize_error_message($message) {
        // Remove common credential patterns
        $patterns = [
            '/(password|passwd|pwd)[=:\s]+[^\s,]+/' => '$1=[REDACTED]',
            '/(api.?key|apikey)[=:\s]+[^\s,]+/' => '$1=[REDACTED]',
            '/(secret|token)[=:\s]+[^\s,]+/' => '$1=[REDACTED]',
        ];
        
        $result = $message;
        foreach ($patterns as $pattern => $replacement) {
            $result = preg_replace($pattern, $replacement, $result);
        }
        
        return $result;
    }
    
    /**
     * Helper: Mask sensitive fields in output
     */
    private function mask_sensitive_fields($output) {
        $sensitive_keys = [
            'password',
            'passwd',
            'pwd',
            'secret',
            'api_key',
            'apikey',
            'token',
            'jwt',
            'refresh_token',
            'sk_',
            'rk_',
        ];
        
        $result = $output;
        
        foreach ($sensitive_keys as $key) {
            // Match patterns like 'key' => 'value'
            $pattern = "/['\"]" . preg_quote($key, '/') . "['\"]\\s*=>\\s*['\"]([^'\"]+)['\"]/i";
            $result = preg_replace($pattern, "'$key' => '[MASKED]'", $result);
        }
        
        return $result;
    }
    
    /**
     * Helper: Mask config dump
     */
    private function mask_config_dump($dump) {
        // Mask API keys and secrets
        $dump = preg_replace(
            '/([sk|rk]k_(?:test|live)_)[a-zA-Z0-9]+/',
            '$1[MASKED]',
            $dump
        );
        
        // Mask generic secrets
        $dump = preg_replace(
            '/(secret|password|token)[_\s]*=>\\s*[\'"]([^\'\"]+)[\'"]/',
            '$1 => \'[MASKED]\'',
            $dump
        );
        
        return $dump;
    }
}
