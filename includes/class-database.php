<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Database {

    private $wpdb;
    private $charset_collate;
    private $db_version = '1.0.0';
    private $otps_table;
    private $guest_sessions_table;
    private $rate_limits_table;
    private $audit_log_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->charset_collate = $this->wpdb->get_charset_collate();
        $this->otps_table = $this->wpdb->prefix . 'zfl_otps';
        $this->guest_sessions_table = $this->wpdb->prefix . 'zfl_guest_sessions';
        $this->rate_limits_table = $this->wpdb->prefix . 'zfl_rate_limits';
        $this->audit_log_table = $this->wpdb->prefix . 'zfl_audit_log';
    }

    public function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $this->create_otps_table();
        $this->create_rate_limits_table();
        $this->create_audit_log_table();
        $this->create_guest_sessions_table();

        update_option('zfl_db_version', $this->db_version);
    }

    private function create_otps_table() {
        $table_name = $this->wpdb->prefix . 'zfl_otps';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email_hash varchar(64) NOT NULL,
            otp_hash varchar(64) NOT NULL,
            type varchar(20) NOT NULL DEFAULT 'otp',
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email_hash (email_hash),
            KEY expires_at (expires_at),
            KEY type (type),
            KEY email_expires (email_hash, expires_at)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    private function create_rate_limits_table() {
        $table_name = $this->wpdb->prefix . 'zfl_rate_limits';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            identifier varchar(255) NOT NULL,
            counter int(11) NOT NULL DEFAULT 1,
            window_start datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            lockout_until datetime DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY identifier (identifier),
            KEY window_start (window_start),
            KEY lockout_until (lockout_until)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    private function create_audit_log_table() {
        $table_name = $this->wpdb->prefix . 'zfl_audit_log';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            event varchar(50) NOT NULL,
            ip varchar(45) NOT NULL,
            user_agent text,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY event (event),
            KEY ip (ip),
            KEY created_at (created_at),
            KEY email_event (email, event)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    private function create_guest_sessions_table() {
        $table_name = $this->wpdb->prefix . 'zfl_guest_sessions';

        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token varchar(128) NOT NULL,
            email varchar(255) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token (token),
            KEY email (email),
            KEY expires_at (expires_at),
            KEY token_expires (token, expires_at)
        ) {$this->charset_collate};";

        dbDelta($sql);
    }

    public function cleanup_expired_records() {
        $current_time = current_time('mysql');

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->otps_table} WHERE expires_at < %s",
                $current_time
            )
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->guest_sessions_table} WHERE expires_at < %s",
                $current_time
            )
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->rate_limits_table}
                WHERE lockout_until IS NULL
                AND window_start < %s",
                date('Y-m-d H:i:s', strtotime('-1 hour'))
            )
        );

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "UPDATE {$this->rate_limits_table}
                SET lockout_until = NULL, counter = 0
                WHERE lockout_until < %s",
                $current_time
            )
        );

        $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->audit_log_table} WHERE created_at < %s",
                $thirty_days_ago
            )
        );
    }
}
