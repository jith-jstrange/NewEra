<?php
/**
 * Mock WordPress WPDB class for testing
 */

namespace Newera\Tests\Mock;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * MockWPDB class for simulating WordPress database operations
 */
class MockWPDB {
    
    /**
     * Mock data storage
     */
    private static $data = [
        'options' => [],
        'users' => [],
        'posts' => [],
        'postmeta' => [],
        'usermeta' => [],
        'subscriptions' => []
    ];
    
    /**
     * Insert data into table
     */
    public static function insert($table, $data, $format = null) {
        if (!isset(self::$data[$table])) {
            self::$data[$table] = [];
        }
        
        // Generate ID if not provided
        if (!isset($data['ID'])) {
            $data['ID'] = count(self::$data[$table]) + 1;
        }
        
        self::$data[$table][] = $data;
        return $data['ID'];
    }
    
    /**
     * Update data in table
     */
    public static function update($table, $data, $where, $format = null, $where_format = null) {
        foreach (self::$data[$table] as &$row) {
            $match = true;
            foreach ($where as $key => $value) {
                if (!isset($row[$key]) || $row[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $row = array_merge($row, $data);
                return 1;
            }
        }
        return 0;
    }
    
    /**
     * Delete data from table
     */
    public static function delete($table, $where, $format = null) {
        $initial_count = count(self::$data[$table]);
        self::$data[$table] = array_filter(self::$data[$table], function($row) use ($where) {
            foreach ($where as $key => $value) {
                if (!isset($row[$key]) || $row[$key] !== $value) {
                    return true; // Keep this row
                }
            }
            return false; // Remove this row
        });
        return $initial_count - count(self::$data[$table]);
    }
    
    /**
     * Get query results
     */
    public static function get_results($query, $output = OBJECT) {
        // Simple query parsing for testing
        if (strpos($query, 'SELECT * FROM wp_subscriptions') !== false) {
            $results = self::$data['subscriptions'];
        } elseif (strpos($query, 'SELECT * FROM wp_users') !== false) {
            $results = self::$data['users'];
        } elseif (strpos($query, 'SELECT * FROM wp_options') !== false) {
            $results = array_map(function($item) {
                return (object) $item;
            }, self::$data['options']);
            return $output === ARRAY_A ? self::to_array($results) : $results;
        } else {
            $results = [];
        }
        
        if ($output === ARRAY_A) {
            return self::to_array($results);
        }
        
        return array_map(function($row) use ($output) {
            if ($output === OBJECT) {
                return (object) $row;
            } elseif ($output === ARRAY_A) {
                return $row;
            }
            return $row;
        }, $results);
    }
    
    /**
     * Get single variable
     */
    public static function get_var($query) {
        $results = self::get_results($query, ARRAY_A);
        return !empty($results) ? array_values($results[0])[0] : null;
    }
    
    /**
     * Get column values
     */
    public static function get_col($query) {
        $results = self::get_results($query, ARRAY_A);
        $column = [];
        
        if (!empty($results)) {
            $first_row = $results[0];
            $column = array_values($first_row);
        }
        
        return $column;
    }
    
    /**
     * Execute query
     */
    public static function query($query) {
        // Mock query execution
        return strpos($query, 'INSERT') !== false || 
               strpos($query, 'UPDATE') !== false || 
               strpos($query, 'DELETE') !== false ? 1 : 0;
    }
    
    /**
     * Insert user
     */
    public static function insert_user($user_data) {
        return self::insert('users', $user_data);
    }
    
    /**
     * Update user
     */
    public static function update_user($user_data) {
        if (isset($user_data['ID'])) {
            return self::update('users', $user_data, ['ID' => $user_data['ID']]);
        }
        return false;
    }
    
    /**
     * Get user by field
     */
    public static function get_user_by($field, $value) {
        foreach (self::$data['users'] as $user) {
            if (isset($user[$field]) && $user[$field] === $value) {
                return (object) $user;
            }
        }
        return false;
    }
    
    /**
     * Add test user
     */
    public static function add_test_user($user_data) {
        return self::insert('users', $user_data);
    }
    
    /**
     * Convert objects to arrays
     */
    private static function to_array($objects) {
        return array_map(function($obj) {
            if (is_object($obj)) {
                return (array) $obj;
            }
            return $obj;
        }, $objects);
    }
    
    /**
     * Clear all mock data
     */
    public static function clear_all() {
        self::$data = [
            'options' => [],
            'users' => [],
            'posts' => [],
            'postmeta' => [],
            'usermeta' => [],
            'subscriptions' => []
        ];
    }
    
    /**
     * Get all mock data
     */
    public static function get_all_data() {
        return self::$data;
    }
    
    /**
     * Set mock data
     */
    public static function set_data($table, $data) {
        self::$data[$table] = $data;
    }
}