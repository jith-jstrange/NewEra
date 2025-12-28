<?php
/**
 * Security Headers Configuration
 * 
 * Adds security headers to protect against common vulnerabilities
 */

namespace Newera\Security;

if (!defined('ABSPATH')) {
    exit;
}

class SecurityHeaders {
    /**
     * Initialize security headers
     */
    public static function init() {
        add_action('send_headers', [__CLASS__, 'set_security_headers']);
        add_action('admin_init', [__CLASS__, 'set_admin_security_headers']);
    }

    /**
     * Set security headers for frontend
     */
    public static function set_security_headers() {
        if (!headers_sent()) {
            // Prevent clickjacking
            header('X-Frame-Options: SAMEORIGIN');
            
            // XSS Protection
            header('X-XSS-Protection: 1; mode=block');
            
            // Prevent MIME type sniffing
            header('X-Content-Type-Options: nosniff');
            
            // Referrer Policy
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy (adjust as needed)
            if (apply_filters('newera_enable_csp', false)) {
                $csp = self::get_content_security_policy();
                header("Content-Security-Policy: $csp");
            }
            
            // Permissions Policy (formerly Feature-Policy)
            header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
            
            // HSTS (only for HTTPS)
            if (is_ssl()) {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }
        }
    }

    /**
     * Set security headers for admin
     */
    public static function set_admin_security_headers() {
        if (!headers_sent() && is_admin()) {
            // Additional admin-specific security
            header('X-Robots-Tag: noindex, nofollow');
        }
    }

    /**
     * Get Content Security Policy
     */
    private static function get_content_security_policy() {
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // WordPress requires inline scripts
            "style-src 'self' 'unsafe-inline'", // WordPress requires inline styles
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        return apply_filters('newera_csp_policy', implode('; ', $csp));
    }
}

// Initialize security headers
SecurityHeaders::init();
