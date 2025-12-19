<?php
/**
 * Mock WordPress storage functions for testing
 */

namespace Newera\Tests\Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MockStorage class for simulating WordPress options and storage
 */
class MockStorage {
    
    /**
     * Mock options storage
     */
    private static $options = [];
    
    /**
     * Mock emails storage
     */
    private static $emails = [];
    
    /**
     * Mock transients storage
     */
    private static $transients = [];
    
    /**
     * Get site option
     */
    public static function get_site_option($option_name, $default = false) {
        return isset(self::$options[$option_name]) ? self::$options[$option_name] : $default;
    }
    
    /**
     * Add site option
     */
    public static function add_site_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        self::$options[$option_name] = $option_value;
        return true;
    }
    
    /**
     * Update option
     */
    public static function update_option($option_name, $option_value, $autoload = null) {
        self::$options[$option_name] = $option_value;
        return true;
    }
    
    /**
     * Get option
     */
    public static function get_option($option_name, $default = false) {
        return isset(self::$options[$option_name]) ? self::$options[$option_name] : $default;
    }
    
    /**
     * Delete option
     */
    public static function delete_option($option_name) {
        unset(self::$options[$option_name]);
        return true;
    }
    
    /**
     * Add email to mock storage
     */
    public static function add_email($email_data) {
        self::$emails[] = $email_data;
        return true;
    }
    
    /**
     * Get all emails
     */
    public static function get_emails() {
        return self::$emails;
    }
    
    /**
     * Clear all emails
     */
    public static function clear_emails() {
        self::$emails = [];
        return true;
    }
    
    /**
     * Set transient
     */
    public static function set_transient($transient, $value, $expiration = 0) {
        self::$transients[$transient] = [
            'value' => $value,
            'expiration' => $expiration,
            'created' => time()
        ];
        return true;
    }
    
    /**
     * Get transient
     */
    public static function get_transient($transient) {
        if (!isset(self::$transients[$transient])) {
            return false;
        }
        
        $transient_data = self::$transients[$transient];
        
        // Check if expired
        if ($transient_data['expiration'] > 0 && 
            (time() - $transient_data['created']) > $transient_data['expiration']) {
            unset(self::$transients[$transient]);
            return false;
        }
        
        return $transient_data['value'];
    }
    
    /**
     * Delete transient
     */
    public static function delete_transient($transient) {
        unset(self::$transients[$transient]);
        return true;
    }
    
    /**
     * Clear all mock data
     */
    public static function clear_all() {
        self::$options = [];
        self::$emails = [];
        self::$transients = [];
        return true;
    }
    
    /**
     * Get all options
     */
    public static function get_all_options() {
        return self::$options;
    }
    
    /**
     * Get all transients
     */
    public static function get_all_transients() {
        return self::$transients;
    }
    
    /**
     * Simulate option update with metadata
     */
    public static function update_option_with_metadata($option_name, $option_value, $metadata = []) {
        self::$options[$option_name] = [
            'value' => $option_value,
            'metadata' => $metadata,
            'updated_at' => time()
        ];
        return true;
    }
    
    /**
     * Get option with metadata
     */
    public static function get_option_with_metadata($option_name) {
        if (!isset(self::$options[$option_name])) {
            return false;
        }
        
        $option_data = self::$options[$option_name];
        
        // If it's a simple value, return it
        if (!is_array($option_data) || !isset($option_data['value'])) {
            return $option_data;
        }
        
        return $option_data;
    }
    
    /**
     * Set multiple options at once
     */
    public static function set_multiple_options($options_array) {
        foreach ($options_array as $option_name => $option_value) {
            self::$options[$option_name] = $option_value;
        }
        return true;
    }
    
    /**
     * Get multiple options at once
     */
    public static function get_multiple_options($option_names) {
        $results = [];
        foreach ($option_names as $option_name) {
            $results[$option_name] = self::get_option($option_name);
        }
        return $results;
    }
    
    /**
     * Check if option exists
     */
    public static function option_exists($option_name) {
        return isset(self::$options[$option_name]);
    }
    
    /**
     * Get option count
     */
    public static function get_option_count() {
        return count(self::$options);
    }
    
    /**
     * Get storage size (approximate)
     */
    public static function get_storage_size() {
        return strlen(serialize(self::$options));
    }
}