<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Security {

    private $wpdb;
    private $table_otps;
    private $table_guest_sessions;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_otps = $this->wpdb->prefix . 'zfl_otps';
        $this->table_guest_sessions = $this->wpdb->prefix . 'zfl_guest_sessions';
    }

    private function get_auth_salt() {
        return wp_salt('auth');
    }

    public function generate_otp($length = 6, $type = 'numeric') {
        if ($type === 'numeric') {
            $characters = '0123456789';
        } else {
            $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }

        $otp = '';
        $max = strlen($characters) - 1;

        for ($i = 0; $i < $length; $i++) {
            $otp .= $characters[random_int(0, $max)];
        }

        return $otp;
    }

    public function hash_otp($otp) {
        return hash_hmac('sha256', $otp, $this->get_auth_salt());
    }

    public function hash_email($email) {
        return hash('sha256', strtolower(trim($email)));
    }

    public function store_otp($email, $otp, $type = 'otp', $expiry_minutes = 15) {
        $normalized_email = $this->sanitize_email($email);
        $email_hash = $this->hash_email($normalized_email);
        $otp_hash = $this->hash_otp($otp);
        $expires_at = date('Y-m-d H:i:s', current_time('timestamp') + ($expiry_minutes * 60));

        $table_name = $this->table_otps;
        $table_exists = $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        ) === $table_name;
        if (!$table_exists) {
            zfl_log("Table {$table_name} does not exist.", 'error');
            return false;
        }

        $this->invalidate_previous_otps($normalized_email);

        $result = $this->wpdb->insert(
            $table_name,
            array(
                'email_hash' => $email_hash,
                'otp_hash' => $otp_hash,
                'type' => $type,
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );

        if ($result === false) {
            zfl_log('Failed to insert OTP record. DB error: ' . $this->wpdb->last_error, 'error');
        }

        return $result !== false;
    }

    public function verify_otp($email, $otp) {
        $normalized_email = $this->sanitize_email($email);
        $email_hash = $this->hash_email($normalized_email);
        $otp_trimmed = trim($otp);
        $otp_hash = $this->hash_otp($otp_trimmed);
        $current_time = current_time('mysql');

        $failed_attempts = $this->get_failed_verification_attempts($normalized_email);
        if ($failed_attempts >= 3) {
            $sleep_time = min(8, pow(2, $failed_attempts - 2));
            zfl_log("verify_otp backoff applied for {$failed_attempts} failed attempts.", 'debug');
            sleep($sleep_time);
        }

        $stored_otp = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT id, otp_hash, expires_at
                FROM {$this->table_otps}
                WHERE email_hash = %s
                AND expires_at > %s
                ORDER BY created_at DESC
                LIMIT 1",
                $email_hash,
                $current_time
            )
        );

        if (!$stored_otp) {
            hash_hmac('sha256', $otp_trimmed, $this->get_auth_salt());
            $this->record_failed_verification($normalized_email);
            return false;
        }

        if (!hash_equals($stored_otp->otp_hash, $otp_hash)) {
            zfl_log('OTP hash mismatch during verification.', 'debug');
            $this->record_failed_verification($normalized_email);
            return false;
        }

        $deleted = $this->wpdb->delete(
            $this->table_otps,
            array('id' => $stored_otp->id),
            array('%d')
        );

        if ($deleted) {
            $this->clear_failed_verification_attempts($normalized_email);
            return true;
        } else {
            zfl_log('Failed to delete OTP record after verification. DB error: ' . $this->wpdb->last_error, 'error');
            return false;
        }
    }

    public function invalidate_previous_otps($email) {
        $normalized_email = $this->sanitize_email($email);
        $email_hash = $this->hash_email($normalized_email);

        $deleted_count = $this->wpdb->delete(
            $this->table_otps,
            array('email_hash' => $email_hash),
            array('%s')
        );

        return $deleted_count;
    }

    private function get_failed_verification_attempts($email) {
        $normalized_email = $this->sanitize_email($email);
        $transient_key = 'zfl_failed_' . $this->hash_email($normalized_email);
        $attempts = get_transient($transient_key);
        return $attempts ? intval($attempts) : 0;
    }

    private function record_failed_verification($email) {
        $normalized_email = $this->sanitize_email($email);
        $transient_key = 'zfl_failed_' . $this->hash_email($normalized_email);
        $attempts = $this->get_failed_verification_attempts($normalized_email);
        $attempts++;
        set_transient($transient_key, $attempts, 900);
    }

    private function clear_failed_verification_attempts($email) {
        $normalized_email = $this->sanitize_email($email);
        $transient_key = 'zfl_failed_' . $this->hash_email($normalized_email);
        delete_transient($transient_key);
    }

    public function generate_magic_token() {
        return bin2hex(random_bytes(32));
    }

    public function store_guest_session($email, $token, $expiry_hours = 24) {
        $expires_at = date('Y-m-d H:i:s', current_time('timestamp') + ($expiry_hours * 3600));

        $result = $this->wpdb->insert(
            $this->table_guest_sessions,
            array(
                'token' => $token,
                'email' => strtolower(trim($email)),
                'expires_at' => $expires_at,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );

        return $result !== false;
    }

    public function verify_guest_session($token) {
        $current_time = current_time('mysql');

        $session = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT email, expires_at
                FROM {$this->table_guest_sessions}
                WHERE token = %s
                AND expires_at > %s
                LIMIT 1",
                $token,
                $current_time
            )
        );

        if ($session) {
            return $session->email;
        }

        return false;
    }

    public function invalidate_guest_session($token) {
        return $this->wpdb->delete(
            $this->table_guest_sessions,
            array('token' => $token),
            array('%s')
        );
    }

    public function sanitize_email($email) {
        $email = trim($email);
        $email = strtolower($email);
        return sanitize_email($email);
    }

    public function validate_email($email) {
        return is_email($email);
    }
}
