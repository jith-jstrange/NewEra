<?php
/**
 * Mock WordPress Database class for testing
 */

namespace Newera\Tests;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MockWPDB class for testing database operations
 */
class MockWPDB {
    /**
     * Mock data storage
     */
    private static $data = [];
    
    /**
     * Mock get_col method
     */
    public static function get_col($query) {
        // Simple mock that returns empty array for now
        // In a real implementation, we'd parse the query and return appropriate results
        return [];
    }
    
    /**
     * Add mock data for testing
     */
    public static function add_mock_data($option_name, $data) {
        self::$data[$option_name] = $data;
    }
    
    /**
     * Get mock data
     */
    public static function get_mock_data($option_name) {
        return isset(self::$data[$option_name]) ? self::$data[$option_name] : null;
    }
    
    /**
     * Clear all mock data
     */
    public static function clear() {
        self::$data = [];
    }
}