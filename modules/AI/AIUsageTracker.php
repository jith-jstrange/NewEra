<?php
/**
 * Usage tracking, rate limiting, and quotas for AI calls.
 */

namespace Newera\Modules\AI;

use Newera\Core\Logger;
use Newera\Core\StateManager;

if (!defined('ABSPATH')) {
    exit;
}

class AIUsageTracker {
    const STATE_USAGE_EVENTS = 'ai_usage_events';
    const STATE_USAGE_MONTHLY = 'ai_usage_monthly';

    /**
     * @var StateManager|null
     */
    private $state_manager;

    /**
     * @var Logger|null
     */
    private $logger;

    /**
     * @param StateManager|null $state_manager
     * @param Logger|null $logger
     */
    public function __construct($state_manager = null, $logger = null) {
        $this->state_manager = $state_manager instanceof StateManager ? $state_manager : null;
        $this->logger = $logger instanceof Logger ? $logger : null;

        if ($this->state_manager === null && function_exists('apply_filters')) {
            $this->state_manager = apply_filters('newera_get_state_manager', null);
        }

        if ($this->logger === null && function_exists('apply_filters')) {
            $this->logger = apply_filters('newera_get_logger', null);
        }
    }

    /**
     * @param array $policies
     * @param string $provider
     * @param string $model
     * @return true|\WP_Error
     */
    public function enforce_policies($policies, $provider, $model) {
        $policies = is_array($policies) ? $policies : [];

        $rpm = isset($policies['max_requests_per_minute']) ? (int) $policies['max_requests_per_minute'] : 0;
        if ($rpm > 0) {
            $ok = $this->consume_rate_limit('rpm', $provider, $rpm, 60);
            if (is_wp_error($ok)) {
                return $ok;
            }
        }

        $month_key = $this->get_month_key();
        $monthly = $this->get_monthly_state();
        $totals = isset($monthly[$month_key]) && is_array($monthly[$month_key]) ? $monthly[$month_key] : [];

        $token_quota = isset($policies['monthly_token_quota']) ? (int) $policies['monthly_token_quota'] : 0;
        if ($token_quota > 0) {
            $used = (int) ($totals['tokens_total'] ?? 0);
            if ($used >= $token_quota) {
                return new \WP_Error('newera_ai_quota_tokens', 'Monthly token quota exceeded.', [
                    'month' => $month_key,
                    'used' => $used,
                    'quota' => $token_quota,
                ]);
            }
        }

        $cost_quota = isset($policies['monthly_cost_quota_usd']) ? (float) $policies['monthly_cost_quota_usd'] : 0;
        if ($cost_quota > 0) {
            $used_cost = (float) ($totals['cost_usd'] ?? 0);
            if ($used_cost >= $cost_quota) {
                return new \WP_Error('newera_ai_quota_cost', 'Monthly cost quota exceeded.', [
                    'month' => $month_key,
                    'used' => $used_cost,
                    'quota' => $cost_quota,
                ]);
            }
        }

        return true;
    }

    /**
     * @param string $type
     * @param string $provider
     * @param int $limit
     * @param int $window_seconds
     * @return true|\WP_Error
     */
    private function consume_rate_limit($type, $provider, $limit, $window_seconds) {
        if (!function_exists('get_transient') || !function_exists('set_transient')) {
            return true;
        }

        $provider = sanitize_key($provider);
        $key = 'newera_ai_rl_' . $type . '_' . $provider;

        $now = time();
        $window_start = $now - (int) $window_seconds;

        $entries = get_transient($key);
        if (!is_array($entries)) {
            $entries = [];
        }

        $entries = array_values(array_filter($entries, function($ts) use ($window_start) {
            return is_int($ts) && $ts >= $window_start;
        }));

        if (count($entries) >= $limit) {
            return new \WP_Error('newera_ai_rate_limited', 'AI rate limit exceeded. Please retry later.', [
                'provider' => $provider,
                'limit' => $limit,
                'window_seconds' => $window_seconds,
            ]);
        }

        $entries[] = $now;
        set_transient($key, $entries, (int) $window_seconds + 5);

        return true;
    }

    /**
     * @param array $event
     */
    public function record_event($event) {
        if (!$this->state_manager) {
            return;
        }

        $event = is_array($event) ? $event : [];

        $events = $this->state_manager->get_state_value(self::STATE_USAGE_EVENTS, []);
        if (!is_array($events)) {
            $events = [];
        }

        array_unshift($events, $event);
        $events = array_slice($events, 0, 200);

        $this->state_manager->update_state(self::STATE_USAGE_EVENTS, $events);

        $this->update_monthly_totals($event);
    }

    /**
     * @return array
     */
    public function get_recent_events() {
        if (!$this->state_manager) {
            return [];
        }

        $events = $this->state_manager->get_state_value(self::STATE_USAGE_EVENTS, []);
        return is_array($events) ? $events : [];
    }

    /**
     * @param string|null $month
     * @return array
     */
    public function get_monthly_totals($month = null) {
        $month_key = $month ? (string) $month : $this->get_month_key();
        $monthly = $this->get_monthly_state();
        $totals = isset($monthly[$month_key]) && is_array($monthly[$month_key]) ? $monthly[$month_key] : [];

        return array_merge([
            'requests' => 0,
            'tokens_prompt' => 0,
            'tokens_completion' => 0,
            'tokens_total' => 0,
            'cost_usd' => 0,
        ], $totals);
    }

    /**
     * @return bool
     */
    public function reset_usage() {
        if (!$this->state_manager) {
            return false;
        }

        $this->state_manager->update_state(self::STATE_USAGE_EVENTS, []);
        $this->state_manager->update_state(self::STATE_USAGE_MONTHLY, []);

        return true;
    }

    /**
     * @param array $event
     */
    private function update_monthly_totals($event) {
        if (!$this->state_manager) {
            return;
        }

        $month_key = $this->get_month_key();
        $monthly = $this->get_monthly_state();
        $totals = isset($monthly[$month_key]) && is_array($monthly[$month_key]) ? $monthly[$month_key] : [];

        $prompt_tokens = (int) ($event['prompt_tokens'] ?? 0);
        $completion_tokens = (int) ($event['completion_tokens'] ?? 0);
        $total_tokens = (int) ($event['total_tokens'] ?? ($prompt_tokens + $completion_tokens));
        $cost_usd = isset($event['cost_usd']) && $event['cost_usd'] !== null ? (float) $event['cost_usd'] : 0;

        $totals['requests'] = (int) ($totals['requests'] ?? 0) + 1;
        $totals['tokens_prompt'] = (int) ($totals['tokens_prompt'] ?? 0) + $prompt_tokens;
        $totals['tokens_completion'] = (int) ($totals['tokens_completion'] ?? 0) + $completion_tokens;
        $totals['tokens_total'] = (int) ($totals['tokens_total'] ?? 0) + $total_tokens;
        $totals['cost_usd'] = (float) ($totals['cost_usd'] ?? 0) + $cost_usd;

        $monthly[$month_key] = $totals;
        $this->state_manager->update_state(self::STATE_USAGE_MONTHLY, $monthly);
    }

    /**
     * @return string
     */
    private function get_month_key() {
        if (function_exists('current_time')) {
            $ts = (int) current_time('timestamp', true);
            return gmdate('Y-m', $ts);
        }

        return gmdate('Y-m');
    }

    /**
     * @return array
     */
    private function get_monthly_state() {
        if (!$this->state_manager) {
            return [];
        }

        $monthly = $this->state_manager->get_state_value(self::STATE_USAGE_MONTHLY, []);
        return is_array($monthly) ? $monthly : [];
    }
}
