<?php
/**
 * Subscription Repository for Newera Plugin
 *
 * Handles database operations for subscriptions.
 */

namespace Newera\Payments;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SubscriptionRepository class
 */
class SubscriptionRepository {
    /**
     * Table name
     *
     * @var string
     */
    private $table;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'subscriptions';
    }

    /**
     * Create a subscription
     *
     * @param array $data Subscription data
     * @return int|false Subscription ID or false
     */
    public function create($data) {
        global $wpdb;

        $defaults = [
            'client_id' => 0,
            'plan' => '',
            'status' => 'active',
            'amount' => null,
            'billing_cycle' => null,
            'start_date' => current_time('mysql', true),
            'end_date' => null,
            'auto_renew' => 1,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $data = array_merge($defaults, $data);

        $result = $wpdb->insert(
            $this->table,
            [
                'client_id' => intval($data['client_id']),
                'plan' => sanitize_text_field($data['plan']),
                'status' => sanitize_text_field($data['status']),
                'amount' => $data['amount'] !== null ? floatval($data['amount']) : null,
                'billing_cycle' => $data['billing_cycle'] ? sanitize_text_field($data['billing_cycle']) : null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'auto_renew' => intval($data['auto_renew']),
                'created_at' => $data['created_at'],
                'updated_at' => $data['updated_at'],
            ],
            [
                '%d',
                '%s',
                '%s',
                '%f',
                '%s',
                '%s',
                '%s',
                '%d',
                '%s',
                '%s',
            ]
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Get a subscription by ID
     *
     * @param int $id Subscription ID
     * @return object|null
     */
    public function get($id) {
        global $wpdb;

        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE id = %d AND deleted_at IS NULL",
            $id
        ));

        return $result;
    }

    /**
     * Get subscriptions by client ID
     *
     * @param int $client_id Client ID
     * @param string $status Filter by status
     * @return array
     */
    public function get_by_client($client_id, $status = null) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE client_id = %d AND deleted_at IS NULL",
            $client_id
        );

        if ($status !== null) {
            $query .= $wpdb->prepare(" AND status = %s", $status);
        }

        $query .= " ORDER BY created_at DESC";

        return $wpdb->get_results($query);
    }

    /**
     * Get subscriptions by plan
     *
     * @param string $plan Plan name
     * @param string $status Filter by status
     * @return array
     */
    public function get_by_plan($plan, $status = null) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE plan = %s AND deleted_at IS NULL",
            $plan
        );

        if ($status !== null) {
            $query .= $wpdb->prepare(" AND status = %s", $status);
        }

        $query .= " ORDER BY created_at DESC";

        return $wpdb->get_results($query);
    }

    /**
     * Get subscriptions by status
     *
     * @param string $status Status
     * @param int $limit Limit results
     * @param int $offset Offset results
     * @return array
     */
    public function get_by_status($status, $limit = 100, $offset = 0) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE status = %s AND deleted_at IS NULL ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $status,
            $limit,
            $offset
        );

        return $wpdb->get_results($query);
    }

    /**
     * Update a subscription
     *
     * @param int $id Subscription ID
     * @param array $data Update data
     * @return bool
     */
    public function update($id, $data) {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $update_data = [];
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['id', 'created_at'], true)) {
                continue;
            }

            $update_data[$key] = $value;
            if ($key === 'amount') {
                $formats[] = '%f';
            } elseif (in_array($key, ['client_id', 'auto_renew'], true)) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        if (empty($update_data)) {
            return true;
        }

        $result = $wpdb->update(
            $this->table,
            $update_data,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Soft delete a subscription
     *
     * @param int $id Subscription ID
     * @return bool
     */
    public function delete($id) {
        return $this->update($id, [
            'deleted_at' => current_time('mysql'),
            'status' => 'cancelled',
        ]);
    }

    /**
     * Get subscription count by status
     *
     * @param string $status Status
     * @return int
     */
    public function count_by_status($status) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE status = %s AND deleted_at IS NULL",
            $status
        ));

        return intval($count);
    }

    /**
     * Get total revenue from subscriptions
     *
     * @param string $status Filter by status
     * @return float
     */
    public function get_total_revenue($status = null) {
        global $wpdb;

        $query = "SELECT SUM(amount) as total FROM {$this->table} WHERE deleted_at IS NULL AND amount IS NOT NULL";

        if ($status !== null) {
            $query .= $wpdb->prepare(" AND status = %s", $status);
        }

        $result = $wpdb->get_row($query);

        return $result && $result->total ? floatval($result->total) : 0.0;
    }

    /**
     * Get expiring subscriptions (ending soon)
     *
     * @param int $days Number of days from now
     * @return array
     */
    public function get_expiring($days = 7) {
        global $wpdb;

        $date_limit = date('Y-m-d', strtotime("+{$days} days"));

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE status = 'active' AND end_date <= %s AND end_date IS NOT NULL AND deleted_at IS NULL ORDER BY end_date ASC",
            $date_limit
        );

        return $wpdb->get_results($query);
    }
}
