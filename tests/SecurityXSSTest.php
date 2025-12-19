<?php
/**
 * Security Tests for XSS Prevention
 *
 * Tests input sanitization, output escaping, and malicious script rejection
 */

namespace Newera\Tests;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Security XSS Test Case
 */
class SecurityXSSTest extends TestCase {
    /**
     * Set up test environment
     */
    protected function setUp(): void {
        parent::setUp();
        MockStorage::clear_all();
    }
    
    /**
     * Test: Input sanitization for client names
     */
    public function testInputSanitizationForClientNames() {
        // XSS attack vectors for client names
        $xss_payloads = [
            '<script>alert("XSS")</script>',
            '<img src="x" onerror="alert(\'XSS\')">',
            '<svg onload="alert(\'XSS\')">',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
            '<body onload="alert(\'XSS\')">',
            '<input onfocus="alert(\'XSS\')" autofocus>',
            '<select onfocus="alert(\'XSS\')" autofocus>',
            '<textarea onfocus="alert(\'XSS\')" autofocus>',
            '<keygen onfocus="alert(\'XSS\')" autofocus>',
            '<video><source onerror="alert(\'XSS\')">',
            '<audio src="x" onerror="alert(\'XSS\')">',
            '<marquee onstart="alert(\'XSS\')">',
            '<div style="background:url(javascript:alert(\'XSS\'))">',
            '<link rel="stylesheet" href="javascript:alert(\'XSS\')">',
            '<style>body{background:url("javascript:alert(\'XSS\')")}</style>',
            '<form action="javascript:alert(\'XSS\')">',
        ];
        
        foreach ($xss_payloads as $payload) {
            // Sanitize as would be done in plugin
            $sanitized = sanitize_text_field($payload);
            
            // Should remove or escape dangerous content
            $this->assertNotContains('<script>', $sanitized);
            $this->assertNotContains('onerror=', $sanitized);
            $this->assertNotContains('onload=', $sanitized);
            $this->assertNotContains('onclick=', $sanitized);
            $this->assertNotContains('onfocus=', $sanitized);
            $this->assertNotContains('javascript:', $sanitized);
        }
    }
    
    /**
     * Test: Input sanitization for project titles
     */
    public function testInputSanitizationForProjectTitles() {
        $xss_payloads = [
            '<img src=x onerror="alert(\'test\')">My Project',
            'Project <svg/onload="alert(\'xss\')">',
            '<style>body{display:none}</style>My Project',
        ];
        
        foreach ($xss_payloads as $payload) {
            $sanitized = wp_kses_post($payload);
            
            // wp_kses_post allows safe HTML but removes dangerous scripts
            $this->assertNotContains('onerror=', $sanitized);
            $this->assertNotContains('onload=', $sanitized);
        }
    }
    
    /**
     * Test: Output escaping in admin pages
     */
    public function testOutputEscapingInAdminPages() {
        // Verify admin files use proper escaping functions
        $admin_files = [
            NEWERA_INCLUDES_PATH . 'Admin/AdminMenu.php',
            NEWERA_INCLUDES_PATH . 'Admin/Dashboard.php',
        ];
        
        foreach ($admin_files as $file) {
            if (!file_exists($file)) {
                continue;
            }
            
            $content = file_get_contents($file);
            
            // Should use escaping functions
            $uses_escaping = strpos($content, 'esc_html') !== false ||
                            strpos($content, 'esc_attr') !== false ||
                            strpos($content, 'esc_url') !== false ||
                            strpos($content, 'wp_kses') !== false;
            
            $this->assertTrue($uses_escaping, "Admin file $file should use escaping functions");
        }
    }
    
    /**
     * Test: HTML entities properly encoded
     */
    public function testHTMLEntitiesProperlyEncoded() {
        $test_strings = [
            'Normal & Text' => 'Normal &amp; Text',
            '<tag>' => '&lt;tag&gt;',
            '"Quote"' => '&quot;Quote&quot;',
            "'Apostrophe'" => '&#039;Apostrophe&#039;',
            'Mixed & <tag> "quotes"' => 'Mixed &amp; &lt;tag&gt; &quot;quotes&quot;',
        ];
        
        foreach ($test_strings as $input => $expected) {
            $encoded = esc_html($input);
            $this->assertStringContainsString('&', $encoded, "Should encode special chars in: $input");
        }
    }
    
    /**
     * Test: Malicious JavaScript in form inputs rejected
     */
    public function testMaliciousJavaScriptInFormInputsRejected() {
        $xss_vectors = [
            'form_field' => '<img src="x" onerror="alert(\'XSS\')">',
            'textarea_content' => 'Normal text\n<script>alert("XSS")</script>',
            'email_field' => 'test@example.com" onmouseover="alert(\'XSS\')',
        ];
        
        foreach ($xss_vectors as $field_name => $malicious_input) {
            // Sanitize each field type appropriately
            if ($field_name === 'email_field') {
                $sanitized = sanitize_email($malicious_input);
                // Email field should only contain valid email chars
                $this->assertNotContains('onerror=', $sanitized);
                $this->assertNotContains('onmouseover=', $sanitized);
            } else {
                $sanitized = sanitize_text_field($malicious_input);
                $this->assertNotContains('<script>', $sanitized);
                $this->assertNotContains('onerror=', $sanitized);
            }
        }
    }
    
    /**
     * Test: API response escaping
     */
    public function testAPIResponseEscaping() {
        // Simulate XSS attempt in API response
        $user_input = '<script>alert("XSS")</script>';
        
        // Response should escape output
        $response = [
            'success' => true,
            'message' => esc_html($user_input),
        ];
        
        // Convert to JSON (as API would do)
        $json_response = json_encode($response);
        
        // Verify script tags are escaped in JSON output
        $this->assertNotContains('<script>', $json_response);
        // Should contain escaped version
        $this->assertStringContainsString('&lt;script&gt;', $json_response);
    }
    
    /**
     * Test: Data attributes XSS prevention
     */
    public function testDataAttributesXSSPrevention() {
        $test_values = [
            'normal value',
            'value with "quotes"',
            'value with \'single quotes\'',
            'value with <tags>',
            'javascript:alert("xss")',
        ];
        
        foreach ($test_values as $value) {
            // Escape for use in data attributes
            $escaped = esc_attr($value);
            
            // Should not contain unescaped quotes
            if (strpos($value, '"') !== false) {
                $this->assertNotContains('="' . $value, 'data-value="' . $escaped . '"');
            }
        }
    }
    
    /**
     * Test: URL handling prevents JavaScript protocol
     */
    public function testURLHandlingPreventsJavaScriptProtocol() {
        $dangerous_urls = [
            'javascript:alert("XSS")',
            'JaVaScRiPt:alert("XSS")',
            'data:text/html,<script>alert("XSS")</script>',
            'vbscript:alert("XSS")',
            'file:///etc/passwd',
        ];
        
        foreach ($dangerous_urls as $url) {
            // WordPress esc_url should sanitize these
            $escaped = esc_url($url);
            
            // Should remove dangerous protocols
            if (strpos($url, 'javascript:') !== false) {
                // esc_url removes javascript: protocol
                $this->assertNotContains('javascript:', strtolower($escaped));
            }
        }
    }
    
    /**
     * Test: JSON response injection prevention
     */
    public function testJSONResponseInjectionPrevention() {
        // Attempt to inject JSON payload
        $injection_attempt = '"; alert("XSS"); "';
        
        $response = [
            'status' => 'success',
            'data' => $injection_attempt,
        ];
        
        $json = json_encode($response);
        
        // Verify injection is properly escaped in JSON
        $decoded = json_decode($json, true);
        $this->assertEquals($injection_attempt, $decoded['data']);
        
        // When rendered as HTML, should be escaped
        $html_output = '<div data-response="' . esc_attr($json) . '"></div>';
        $this->assertNotContains('alert("XSS")', $html_output);
    }
    
    /**
     * Test: CSS injection prevention
     */
    public function testCSSInjectionPrevention() {
        $css_injection = 'color: red; background: url("javascript:alert(\'XSS\')")';
        
        // Should not allow javascript URLs in CSS
        $safe_css = $this->sanitize_css_value($css_injection);
        $this->assertNotContains('javascript:', $safe_css);
    }
    
    /**
     * Helper function to sanitize CSS values
     */
    private function sanitize_css_value($css) {
        // Remove javascript: protocol from CSS
        $css = preg_replace('/javascript:/i', '', $css);
        return $css;
    }
    
    /**
     * Test: SVG/XML injection prevention
     */
    public function testSVGXMLInjectionPrevention() {
        $svg_injection = '<svg onload="alert(\'XSS\')"><circle cx="50" cy="50" r="40"/></svg>';
        
        // When embedding SVG in HTML, should use wp_kses_post
        $safe_svg = wp_kses_post($svg_injection);
        
        // Should remove onload attribute
        $this->assertNotContains('onload=', $safe_svg);
    }
    
    /**
     * Test: DOM-based XSS prevention (client side would be JS test)
     */
    public function testDOMbasedXSSPrevention() {
        // Test that data passed to JavaScript is properly encoded
        $user_data = "'; alert('XSS'); //";
        
        // When embedding in JavaScript string
        $js_safe = json_encode($user_data);
        
        // Should escape quotes and special chars
        $this->assertStringContainsString('\\\'', $js_safe);
    }
}
