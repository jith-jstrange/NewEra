<?php
/**
 * Repository Base class for Newera Plugin
 *
 * Provides a foundation for data repositories with query builder capabilities.
 */

namespace Newera\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Repository Base class
 */
abstract class RepositoryBase {
    /**
     * Database adapter instance
     *
     * @var DBAdapterInterface
     */
    protected $db;

    /**
     * Table name
     *
     * @var string
     */
    protected $table;

    /**
     * Constructor
     *
     * @param DBAdapterInterface $db
     */
    public function __construct(DBAdapterInterface $db) {
        $this->db = $db;
        if (empty($this->table)) {
            throw new \Exception('Table name must be defined in repository class');
        }
    }

    /**
     * Get the database adapter
     *
     * @return DBAdapterInterface
     */
    public function get_db() {
        return $this->db;
    }

    /**
     * Get the table name with prefix
     *
     * @return string
     */
    public function get_table() {
        return $this->db->get_table_prefix() . $this->table;
    }

    /**
     * Find a record by ID
     *
     * @param int $id
     * @return mixed
     */
    public function find($id) {
        $query = "SELECT * FROM " . $this->get_table() . " WHERE id = %d";
        return $this->db->get_row($this->db->prepare($query, [$id]));
    }

    /**
     * Find all records
     *
     * @param array $args
     * @return array
     */
    public function find_all($args = []) {
        $limit = isset($args['limit']) ? (int) $args['limit'] : -1;
        $offset = isset($args['offset']) ? (int) $args['offset'] : 0;

        $query = "SELECT * FROM " . $this->get_table();

        if ($limit > 0) {
            if ($offset > 0) {
                $query .= " LIMIT {$offset}, {$limit}";
            } else {
                $query .= " LIMIT {$limit}";
            }
        }

        return $this->db->get_results($query);
    }

    /**
     * Find records by criteria
     *
     * @param array $where
     * @param array $args
     * @return array
     */
    public function find_by($where, $args = []) {
        $query = "SELECT * FROM " . $this->get_table() . " WHERE ";
        $conditions = [];
        $values = [];

        foreach ($where as $column => $value) {
            $conditions[] = "{$column} = %s";
            $values[] = $value;
        }

        $query .= implode(' AND ', $conditions);

        if (isset($args['limit']) && $args['limit'] > 0) {
            $query .= " LIMIT " . (int) $args['limit'];
        }

        if (isset($args['offset']) && $args['offset'] > 0) {
            $query .= " OFFSET " . (int) $args['offset'];
        }

        if (!empty($values)) {
            $query = $this->db->prepare($query, $values);
        }

        return $this->db->get_results($query);
    }

    /**
     * Find a single record by criteria
     *
     * @param array $where
     * @return mixed
     */
    public function find_one($where) {
        $results = $this->find_by($where, ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Create a new record
     *
     * @param array $data
     * @return int|false
     */
    public function create($data) {
        return $this->db->insert($this->get_table(), $data);
    }

    /**
     * Update a record
     *
     * @param int $id
     * @param array $data
     * @return int|false
     */
    public function update($id, $data) {
        return $this->db->update(
            $this->get_table(),
            $data,
            ['id' => $id]
        );
    }

    /**
     * Delete a record
     *
     * @param int $id
     * @return int|false
     */
    public function delete($id) {
        return $this->db->delete(
            $this->get_table(),
            ['id' => $id]
        );
    }

    /**
     * Count records
     *
     * @param array $where
     * @return int
     */
    public function count($where = []) {
        $query = "SELECT COUNT(*) FROM " . $this->get_table();
        $values = [];

        if (!empty($where)) {
            $query .= " WHERE ";
            $conditions = [];

            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = %s";
                $values[] = $value;
            }

            $query .= implode(' AND ', $conditions);
        }

        if (!empty($values)) {
            $query = $this->db->prepare($query, $values);
        }

        $result = $this->db->get_var($query);
        return (int) $result;
    }

    /**
     * Check if record exists
     *
     * @param array $where
     * @return bool
     */
    public function exists($where) {
        return $this->count($where) > 0;
    }

    /**
     * Get the last insert ID
     *
     * @return int
     */
    public function get_last_insert_id() {
        return $this->db->get_insert_id();
    }

    /**
     * Start a transaction
     *
     * @return bool
     */
    public function begin_transaction() {
        return $this->db->begin_transaction();
    }

    /**
     * Commit the current transaction
     *
     * @return bool
     */
    public function commit() {
        return $this->db->commit();
    }

    /**
     * Rollback the current transaction
     *
     * @return bool
     */
    public function rollback() {
        return $this->db->rollback();
    }

    /**
     * Execute a raw query
     *
     * @param string $query
     * @param array $args
     * @return mixed
     */
    public function query($query, $args = []) {
        if (!empty($args)) {
            $query = $this->db->prepare($query, $args);
        }
        return $this->db->query($query);
    }

    /**
     * Execute a raw select query
     *
     * @param string $query
     * @param array $args
     * @return array
     */
    public function select($query, $args = []) {
        if (!empty($args)) {
            $query = $this->db->prepare($query, $args);
        }
        return $this->db->get_results($query);
    }
}
