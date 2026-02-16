<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Rate_Limiter {

    private $wpdb;
    private $table_rate_limits;

    const EMAIL_LIMIT_PER_HOUR = 3;
    const EMAIL_LIMIT_PER_30_SEC = 5;
    const IP_LIMIT_PER_HOUR = 20;
    const LOCKOUT_DURATION_MINUTES = 30;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_rate_limits = $this->wpdb->prefix . 'zfl_rate_limits';
    }

    public function check_email_limit($email) {
        if ($this->is_test_mode_enabled()) {
            return array(
                'allowed' => true,
                'remaining' => self::EMAIL_LIMIT_PER_HOUR
            );
        }

        $identifier = 'email_' . hash('sha256', strtolower(trim($email)));

        if ($this->is_locked_out($identifier)) {
            return array(
                'allowed' => false,
                'reason' => 'locked_out',
                'message' => __('Too many attempts. Please try again later.', 'zero-friction-login')
            );
        }

        $hourly_count = $this->get_attempt_count($identifier, 3600);
        if ($hourly_count >= self::EMAIL_LIMIT_PER_HOUR) {
            $this->apply_lockout($identifier, self::LOCKOUT_DURATION_MINUTES);
            return array(
                'allowed' => false,
                'reason' => 'hourly_limit',
                'message' => __('Too many requests. Please try again in 30 minutes.', 'zero-friction-login')
            );
        }

        $recent_count = $this->get_attempt_count($identifier, 30);
        if ($recent_count >= self::EMAIL_LIMIT_PER_30_SEC) {
            return array(
                'allowed' => false,
                'reason' => 'rate_limit',
                'message' => __('Too many requests. Please wait before trying again.', 'zero-friction-login')
            );
        }

        return array(
            'allowed' => true,
            'remaining' => self::EMAIL_LIMIT_PER_HOUR - $hourly_count
        );
    }

    public function check_ip_limit($ip) {
        if ($this->is_test_mode_enabled()) {
            return array(
                'allowed' => true,
                'remaining' => self::IP_LIMIT_PER_HOUR
            );
        }

        $identifier = 'ip_' . hash('sha256', $ip);

        if ($this->is_locked_out($identifier)) {
            return array(
                'allowed' => false,
                'reason' => 'locked_out',
                'message' => __('Too many attempts from this IP. Please try again later.', 'zero-friction-login')
            );
        }

        $hourly_count = $this->get_attempt_count($identifier, 3600);
        if ($hourly_count >= self::IP_LIMIT_PER_HOUR) {
            $this->apply_lockout($identifier, self::LOCKOUT_DURATION_MINUTES);
            return array(
                'allowed' => false,
                'reason' => 'ip_limit',
                'message' => __('Too many requests from this IP. Please try again in 30 minutes.', 'zero-friction-login')
            );
        }

        return array(
            'allowed' => true,
            'remaining' => self::IP_LIMIT_PER_HOUR - $hourly_count
        );
    }

    public function record_attempt($identifier) {
        if ($this->is_test_mode_enabled()) {
            return true;
        }

        $identifier_hash = is_string($identifier) && strpos($identifier, '_') !== false
            ? $identifier
            : 'custom_' . hash('sha256', $identifier);

        $current_time = current_time('mysql');

        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, counter, window_start
                FROM {$this->table_rate_limits}
                WHERE identifier = %s
                LIMIT 1",
                $identifier_hash
            )
        );

        if ($existing) {
            $window_start_time = strtotime($existing->window_start);
            $current_timestamp = strtotime($current_time);

            if (($current_timestamp - $window_start_time) > 3600) {
                $this->wpdb->update(
                    $this->table_rate_limits,
                    array(
                        'counter' => 1,
                        'window_start' => $current_time
                    ),
                    array('id' => $existing->id),
                    array('%d', '%s'),
                    array('%d')
                );
            } else {
                $this->wpdb->update(
                    $this->table_rate_limits,
                    array('counter' => $existing->counter + 1),
                    array('id' => $existing->id),
                    array('%d'),
                    array('%d')
                );
            }
        } else {
            $this->wpdb->insert(
                $this->table_rate_limits,
                array(
                    'identifier' => $identifier_hash,
                    'counter' => 1,
                    'window_start' => $current_time
                ),
                array('%s', '%d', '%s')
            );
        }

        return true;
    }

    public function apply_lockout($identifier, $duration_minutes = 30) {
        if ($this->is_test_mode_enabled()) {
            return true;
        }

        $lockout_until = date('Y-m-d H:i:s', strtotime("+{$duration_minutes} minutes"));

        $existing = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table_rate_limits} WHERE identifier = %s",
                $identifier
            )
        );

        if ($existing) {
            $this->wpdb->update(
                $this->table_rate_limits,
                array('lockout_until' => $lockout_until),
                array('identifier' => $identifier),
                array('%s'),
                array('%s')
            );
        } else {
            $this->wpdb->insert(
                $this->table_rate_limits,
                array(
                    'identifier' => $identifier,
                    'counter' => 0,
                    'window_start' => current_time('mysql'),
                    'lockout_until' => $lockout_until
                ),
                array('%s', '%d', '%s', '%s')
            );
        }

        return true;
    }

    private function is_locked_out($identifier) {
        $current_time = current_time('mysql');

        $lockout = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT lockout_until
                FROM {$this->table_rate_limits}
                WHERE identifier = %s
                AND lockout_until > %s
                LIMIT 1",
                $identifier,
                $current_time
            )
        );

        return !empty($lockout);
    }

    private function get_attempt_count($identifier, $window_seconds = 3600) {
        $window_start = date('Y-m-d H:i:s', strtotime("-{$window_seconds} seconds"));

        $count = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT counter
                FROM {$this->table_rate_limits}
                WHERE identifier = %s
                AND window_start >= %s
                LIMIT 1",
                $identifier,
                $window_start
            )
        );

        return intval($count);
    }

    public function get_client_ip() {
        $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? trim(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if (filter_var($remote_addr, FILTER_VALIDATE_IP)) {
            return $remote_addr;
        }

        return '0.0.0.0';
    }

    private function is_test_mode_enabled() {
        return (bool) get_option('zfl_test_mode', false);
    }
}
