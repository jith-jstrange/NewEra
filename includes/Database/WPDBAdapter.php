<?php
/**
 * WordPress Database Adapter for Newera Plugin
 *
 * Wraps WordPress $wpdb with the DBAdapterInterface.
 */

namespace Newera\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress Database Adapter
 */
class WPDBAdapter implements DBAdapterInterface {
    /**
     * WordPress database object
     *
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get the database connection
     *
     * @return \wpdb
     */
    public function get_connection() {
        return $this->wpdb;
    }

    /**
     * Execute a query
     *
     * @param string $query
     * @param array $args
     * @return mixed
     */
    public function query($query, $args = []) {
        if (!empty($args)) {
            $query = $this->prepare($query, $args);
        }
        return $this->wpdb->query($query);
    }

    /**
     * Get results from a query
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_results($query, $args = [], $output = 'OBJECT') {
        if (!empty($args)) {
            $query = $this->prepare($query, $args);
        }
        return $this->wpdb->get_results($query, $output);
    }

    /**
     * Get a single row
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_row($query, $args = [], $output = 'OBJECT') {
        if (!empty($args)) {
            $query = $this->prepare($query, $args);
        }
        return $this->wpdb->get_row($query, $output);
    }

    /**
     * Get a single column value
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return mixed
     */
    public function get_var($query, $args = [], $x = 0) {
        if (!empty($args)) {
            $query = $this->prepare($query, $args);
        }
        return $this->wpdb->get_var($query, $x);
    }

    /**
     * Get a single column as array
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return array
     */
    public function get_col($query, $args = [], $x = 0) {
        if (!empty($args)) {
            $query = $this->prepare($query, $args);
        }
        $results = $this->wpdb->get_col($query, $x);
        return $results ? $results : [];
    }

    /**
     * Insert a row
     *
     * @param string $table
     * @param array $data
     * @param array $format
     * @return int|false
     */
    public function insert($table, $data, $format = []) {
        $result = $this->wpdb->insert($table, $data, $format);
        return $result ? $this->wpdb->insert_id : false;
    }

    /**
     * Update rows
     *
     * @param string $table
     * @param array $data
     * @param array $where
     * @param array $format
     * @param array $where_format
     * @return int|false
     */
    public function update($table, $data, $where, $format = [], $where_format = []) {
        $result = $this->wpdb->update($table, $data, $where, $format, $where_format);
        return $result !== false ? $result : false;
    }

    /**
     * Delete rows
     *
     * @param string $table
     * @param array $where
     * @param array $where_format
     * @return int|false
     */
    public function delete($table, $where, $where_format = []) {
        $result = $this->wpdb->delete($table, $where, $where_format);
        return $result !== false ? $result : false;
    }

    /**
     * Get the last insert ID
     *
     * @return int
     */
    public function get_insert_id() {
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get the number of rows affected
     *
     * @return int
     */
    public function get_rows_affected() {
        return (int) $this->wpdb->rows_affected;
    }

    /**
     * Get the prefix for table names
     *
     * @return string
     */
    public function get_table_prefix() {
        return $this->wpdb->prefix;
    }

    /**
     * Prepare a query
     *
     * @param string $query
     * @param array $args
     * @return string
     */
    public function prepare($query, $args = []) {
        if (empty($args)) {
            return $query;
        }

        return $this->wpdb->prepare($query, ...$args);
    }

    /**
     * Get charset collation
     *
     * @return string
     */
    public function get_charset_collate() {
        return $this->wpdb->get_charset_collate();
    }

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function begin_transaction() {
        return (bool) $this->wpdb->query('START TRANSACTION');
    }

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit() {
        return (bool) $this->wpdb->query('COMMIT');
    }

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback() {
        return (bool) $this->wpdb->query('ROLLBACK');
    }

    /**
     * Test the connection
     *
     * @return bool
     */
    public function test_connection() {
        try {
            return (bool) $this->wpdb->query('SELECT 1');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get connection status
     *
     * @return array
     */
    public function get_connection_status() {
        return [
            'connected' => $this->test_connection(),
            'database' => $this->wpdb->dbname,
            'host' => $this->wpdb->dbhost,
            'prefix' => $this->wpdb->prefix,
            'charset' => $this->wpdb->charset,
            'collate' => $this->wpdb->collate,
        ];
    }
}
