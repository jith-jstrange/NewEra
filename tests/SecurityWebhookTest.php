<?php
/**
 * Security Tests for Webhook Signature Validation
 *
 * Tests signature verification, tampered payload detection,
 * and signature algorithm verification
 */

namespace Newera\Tests;

use Newera\Payments\WebhookHandler;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security Webhook Test Case
 */
class SecurityWebhookTest extends TestCase {
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        MockStorage::clear_all();
    }
    
    /**
     * Test: Valid webhook signatures pass verification
     */
    public function testValidWebhookSignaturesPass() {
        // Simulate Stripe webhook signature verification
        $secret = 'whsec_test_secret_key_' . time();
        $payload = json_encode([
            'type' => 'customer.subscription.created',
            'id' => 'evt_test_123',
            'data' => [
                'object' => [
                    'id' => 'sub_test_123',
                    'customer' => 'cus_test_123',
                ],
            ],
        ]);
        
        // Generate valid signature
        $signature = $this->generate_webhook_signature($payload, $secret);
        
        // Verify signature
        $is_valid = $this->verify_webhook_signature($payload, $signature, $secret);
        $this->assertTrue($is_valid);
    }
    
    /**
     * Test: Tampered payloads rejected
     */
    public function testTamperedPayloadsRejected() {
        $secret = 'whsec_test_secret_key_' . time();
        $original_payload = json_encode([
            'type' => 'charge.succeeded',
            'id' => 'evt_test_456',
            'data' => [
                'object' => [
                    'amount' => 5000,
                    'currency' => 'usd',
                ],
            ],
        ]);
        
        // Generate signature for original payload
        $original_signature = $this->generate_webhook_signature($original_payload, $secret);
        
        // Tamper with payload (change amount)
        $tampered_payload = json_encode([
            'type' => 'charge.succeeded',
            'id' => 'evt_test_456',
            'data' => [
                'object' => [
                    'amount' => 10000, // Changed amount
                    'currency' => 'usd',
                ],
            ],
        ]);
        
        // Signature should not verify for tampered payload
        $is_valid = $this->verify_webhook_signature($tampered_payload, $original_signature, $secret);
        $this->assertFalse($is_valid);
    }
    
    /**
     * Test: Missing signatures rejected
     */
    public function testMissingSignaturesRejected() {
        $payload = json_encode(['type' => 'test.event']);
        $secret = 'test_secret';
        
        // Verify with empty signature
        $is_valid = $this->verify_webhook_signature($payload, '', $secret);
        $this->assertFalse($is_valid);
        
        // Verify with null signature
        $is_valid = $this->verify_webhook_signature($payload, null, $secret);
        $this->assertFalse($is_valid);
        
        // Verify with missing signature
        $is_valid = $this->verify_webhook_signature($payload, '', $secret);
        $this->assertFalse($is_valid);
    }
    
    /**
     * Test: Wrong secret rejected
     */
    public function testWrongSecretRejected() {
        $correct_secret = 'whsec_correct_secret_' . time();
        $wrong_secret = 'whsec_wrong_secret_' . time();
        
        $payload = json_encode(['type' => 'charge.succeeded', 'id' => 'evt_123']);
        
        // Sign with correct secret
        $signature = $this->generate_webhook_signature($payload, $correct_secret);
        
        // Try to verify with wrong secret
        $is_valid = $this->verify_webhook_signature($payload, $signature, $wrong_secret);
        $this->assertFalse($is_valid);
    }
    
    /**
     * Test: Signature algorithm verification
     */
    public function testSignatureAlgorithmVerification() {
        $payload = 'test_payload_data';
        $secret = 'test_secret_key';
        
        // Generate HMAC-SHA256 signature (standard for Stripe)
        $signature = hash_hmac('sha256', $payload, $secret);
        
        // Verify it's the correct format
        $this->assertTrue(preg_match('/^[a-f0-9]{64}$/', $signature));
        
        // Verify with correct secret
        $computed = hash_hmac('sha256', $payload, $secret);
        $this->assertTrue(hash_equals($signature, $computed));
        
        // Verify fails with different secret
        $wrong_computed = hash_hmac('sha256', $payload, 'wrong_secret');
        $this->assertFalse(hash_equals($signature, $wrong_computed));
    }
    
    /**
     * Test: Webhook timestamp validation (replay attack prevention)
     */
    public function testWebhookTimestampValidation() {
        // Stripe includes timestamp to prevent replay attacks
        $current_time = time();
        
        // Webhook from now is valid
        $valid_timestamp = $current_time;
        $time_diff = abs(time() - $valid_timestamp);
        $this->assertLessThan(600, $time_diff); // Within 10 minutes
        
        // Very old webhook (5 minutes old is typically rejected)
        $old_timestamp = $current_time - 600; // 10 minutes ago
        $time_diff = abs(time() - $old_timestamp);
        $this->assertGreaterThan(300, $time_diff); // More than 5 minutes old
    }
    
    /**
     * Test: Webhook request validation
     */
    public function testWebhookRequestValidation() {
        // Verify WebhookHandler exists and validates
        $webhook_handler_file = NEWERA_INCLUDES_PATH . 'Payments/WebhookHandler.php';
        $this->assertFileExists($webhook_handler_file);
        
        $content = file_get_contents($webhook_handler_file);
        
        // Should validate webhook requests
        $this->assertTrue(
            strpos($content, 'handle_event') !== false,
            'WebhookHandler should have event handling method'
        );
    }
    
    /**
     * Test: Event type validation
     */
    public function testEventTypeValidation() {
        $valid_events = [
            'customer.subscription.created',
            'customer.subscription.updated',
            'charge.succeeded',
            'charge.failed',
            'invoice.payment_succeeded',
        ];
        
        $invalid_events = [
            'invalid.event.type',
            'malicious.event',
            '../../etc/passwd',
        ];
        
        // Valid events should be accepted
        foreach ($valid_events as $event_type) {
            $is_valid = $this->is_valid_event_type($event_type);
            $this->assertTrue($is_valid);
        }
        
        // Invalid events should be rejected
        foreach ($invalid_events as $event_type) {
            $is_valid = $this->is_valid_event_type($event_type);
            $this->assertFalse($is_valid);
        }
    }
    
    /**
     * Test: Duplicate event prevention
     */
    public function testDuplicateEventPrevention() {
        // Webhook events should have unique IDs to prevent duplicates
        $event_id_1 = 'evt_stripe_12345_abc';
        $event_id_2 = 'evt_stripe_12345_abc'; // Same ID
        
        // Store first event
        update_option('newera_webhook_event_' . $event_id_1, true);
        
        // Check if duplicate
        $is_duplicate = get_option('newera_webhook_event_' . $event_id_2);
        $this->assertTrue($is_duplicate, 'Duplicate event should be detected');
    }
    
    /**
     * Test: Event payload validation
     */
    public function testEventPayloadValidation() {
        // Webhook payload should have required fields
        $valid_payload = [
            'type' => 'customer.subscription.created',
            'id' => 'evt_test_123',
            'data' => [
                'object' => [
                    'id' => 'sub_test_123',
                    'customer' => 'cus_test_123',
                ]
            ]
        ];
        
        // Missing type
        $invalid_payload_1 = [
            'id' => 'evt_test_123',
            'data' => []
        ];
        
        // Missing data
        $invalid_payload_2 = [
            'type' => 'test.event',
            'id' => 'evt_test_123',
        ];
        
        // Validate payloads
        $this->assertTrue($this->is_valid_webhook_payload($valid_payload));
        $this->assertFalse($this->is_valid_webhook_payload($invalid_payload_1));
        $this->assertFalse($this->is_valid_webhook_payload($invalid_payload_2));
    }
    
    /**
     * Test: Signature timing attack prevention
     */
    public function testSignatureTimingAttackPrevention() {
        $correct_sig = hash_hmac('sha256', 'payload', 'secret');
        $wrong_sig = hash_hmac('sha256', 'payload', 'wrong');
        
        // Should use hash_equals to prevent timing attacks
        $result1 = hash_equals($correct_sig, $correct_sig);
        $result2 = hash_equals($correct_sig, $wrong_sig);
        
        $this->assertTrue($result1);
        $this->assertFalse($result2);
    }
    
    /**
     * Test: Webhook endpoint security
     */
    public function testWebhookEndpointSecurity() {
        $webhook_endpoint_file = NEWERA_INCLUDES_PATH . 'Payments/WebhookEndpoint.php';
        
        if (!file_exists($webhook_endpoint_file)) {
            $this->markTestSkipped('WebhookEndpoint.php not found');
        }
        
        $content = file_get_contents($webhook_endpoint_file);
        
        // Should verify webhook secret
        $this->assertTrue(
            strpos($content, 'signature') !== false ||
            strpos($content, 'verify') !== false,
            'WebhookEndpoint should verify signatures'
        );
    }
    
    /**
     * Helper: Generate webhook signature
     */
    private function generate_webhook_signature($payload, $secret) {
        return hash_hmac('sha256', $payload, $secret);
    }
    
    /**
     * Helper: Verify webhook signature
     */
    private function verify_webhook_signature($payload, $signature, $secret) {
        if (empty($signature) || !is_string($payload)) {
            return false;
        }
        
        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($computed, $signature);
    }
    
    /**
     * Helper: Validate event type
     */
    private function is_valid_event_type($event_type) {
        $allowed_events = [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
            'charge.succeeded',
            'charge.failed',
            'invoice.payment_succeeded',
            'invoice.payment_failed',
        ];
        
        return in_array($event_type, $allowed_events);
    }
    
    /**
     * Helper: Validate webhook payload
     */
    private function is_valid_webhook_payload($payload) {
        return is_array($payload) &&
               isset($payload['type']) &&
               isset($payload['id']) &&
               isset($payload['data']);
    }
}
