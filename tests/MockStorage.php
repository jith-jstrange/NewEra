<?php
/**
 * Mock storage for WordPress options during testing
 */

namespace Newera\Tests;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mock storage class for testing WordPress options
 */
class MockStorage {
    /**
     * Mock site options storage
     */
    private static $site_options = [];
    
    /**
     * Mock options storage
     */
    private static $options = [];
    
    /**
     * Get site option
     */
    public static function get_site_option($option_name, $default = false) {
        return isset(self::$site_options[$option_name]) ? self::$site_options[$option_name] : $default;
    }
    
    /**
     * Add site option
     */
    public static function add_site_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        self::$site_options[$option_name] = $option_value;
        return true;
    }
    
    /**
     * Get option
     */
    public static function get_option($option_name, $default = false) {
        return isset(self::$options[$option_name]) ? self::$options[$option_name] : $default;
    }
    
    /**
     * Add option
     */
    public static function add_option($option_name, $option_value, $deprecated = '', $autoload = 'yes') {
        if (isset(self::$options[$option_name])) {
            return false;
        }
        self::$options[$option_name] = $option_value;
        return true;
    }
    
    /**
     * Update option
     */
    public static function update_option($option_name, $option_value, $autoload = 'yes') {
        self::$options[$option_name] = $option_value;
        return true;
    }
    
    /**
     * Delete option
     */
    public static function delete_option($option_name) {
        if (isset(self::$options[$option_name])) {
            unset(self::$options[$option_name]);
            return true;
        }
        return false;
    }
    
    /**
     * Clear all mock storage
     */
    public static function clear_all() {
        self::$site_options = [];
        self::$options = [];
    }
    
    /**
     * Get all stored options (for debugging)
     */
    public static function get_all_options() {
        return self::$options;
    }
    
    /**
     * Get all stored site options (for debugging)
     */
    public static function get_all_site_options() {
        return self::$site_options;
    }
}