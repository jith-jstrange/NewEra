<?php
/**
 * Database Adapter Interface for Newera Plugin
 *
 * Defines the contract for database adapters.
 */

namespace Newera\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database Adapter Interface
 */
interface DBAdapterInterface {
    /**
     * Get the database connection
     *
     * @return mixed
     */
    public function get_connection();

    /**
     * Execute a query
     *
     * @param string $query
     * @param array $args
     * @return mixed
     */
    public function query($query, $args = []);

    /**
     * Get results from a query
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_results($query, $args = [], $output = 'OBJECT');

    /**
     * Get a single row
     *
     * @param string $query
     * @param array $args
     * @param string $output
     * @return mixed
     */
    public function get_row($query, $args = [], $output = 'OBJECT');

    /**
     * Get a single column value
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return mixed
     */
    public function get_var($query, $args = [], $x = 0);

    /**
     * Get a single column as array
     *
     * @param string $query
     * @param array $args
     * @param int $x
     * @return array
     */
    public function get_col($query, $args = [], $x = 0);

    /**
     * Insert a row
     *
     * @param string $table
     * @param array $data
     * @param array $format
     * @return int|false
     */
    public function insert($table, $data, $format = []);

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
    public function update($table, $data, $where, $format = [], $where_format = []);

    /**
     * Delete rows
     *
     * @param string $table
     * @param array $where
     * @param array $where_format
     * @return int|false
     */
    public function delete($table, $where, $where_format = []);

    /**
     * Get the last insert ID
     *
     * @return int
     */
    public function get_insert_id();

    /**
     * Get the number of rows affected
     *
     * @return int
     */
    public function get_rows_affected();

    /**
     * Get the prefix for table names
     *
     * @return string
     */
    public function get_table_prefix();

    /**
     * Prepare a query
     *
     * @param string $query
     * @param array $args
     * @return string
     */
    public function prepare($query, $args = []);

    /**
     * Get charset collation
     *
     * @return string
     */
    public function get_charset_collate();

    /**
     * Begin a transaction
     *
     * @return bool
     */
    public function begin_transaction();

    /**
     * Commit a transaction
     *
     * @return bool
     */
    public function commit();

    /**
     * Rollback a transaction
     *
     * @return bool
     */
    public function rollback();

    /**
     * Test the connection
     *
     * @return bool
     */
    public function test_connection();

    /**
     * Get connection status
     *
     * @return array
     */
    public function get_connection_status();
}
