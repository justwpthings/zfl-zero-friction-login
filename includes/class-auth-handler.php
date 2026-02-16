<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Auth_Handler {

    private $wpdb;
    private $security;
    private $rate_limiter;
    private $audit_log_table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->security = new ZFL_Security();
        $this->rate_limiter = new ZFL_Rate_Limiter();
        $this->audit_log_table = $this->wpdb->prefix . 'zfl_audit_log';
    }

    public function request_otp($email, $type = 'otp') {
        $email = $this->security->sanitize_email($email);

        if (!$this->security->validate_email($email)) {
            return array(
                'success' => false,
                'error' => 'invalid_email',
                'message' => __('Please provide a valid email address.', 'zero-friction-login')
            );
        }

        $email_check = $this->rate_limiter->check_email_limit($email);
        if (!$email_check['allowed']) {
            $this->log_event($email, 'otp_rate_limited');
            return array(
                'success' => false,
                'error' => $email_check['reason'],
                'message' => $email_check['message']
            );
        }

        $ip = $this->rate_limiter->get_client_ip();
        $ip_check = $this->rate_limiter->check_ip_limit($ip);
        if (!$ip_check['allowed']) {
            $this->log_event($email, 'otp_rate_limited_ip');
            return array(
                'success' => false,
                'error' => $ip_check['reason'],
                'message' => $ip_check['message']
            );
        }

        $otp_length = ($type === 'otp') ? 6 : 8;
        $otp_type = ($type === 'magic_link') ? 'alphanumeric' : 'numeric';
        $otp = $this->security->generate_otp($otp_length, $otp_type);

        $stored = $this->security->store_otp($email, $otp, $type, 15);

        if (!$stored) {
            return array(
                'success' => false,
                'error' => 'storage_failed',
                'message' => __('Failed to generate OTP. Please try again.', 'zero-friction-login')
            );
        }

        $this->rate_limiter->record_attempt('email_' . hash('sha256', $email));
        $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));

        $this->log_event($email, 'otp_requested');

        return array(
            'success' => true,
            'otp' => $otp,
            'email' => $email,
            'type' => $type,
            'message' => __('OTP generated successfully.', 'zero-friction-login')
        );
    }

    public function verify_and_login($email, $otp) {
        $email = $this->security->sanitize_email($email);

        if (!$this->security->validate_email($email)) {
            return array(
                'success' => false,
                'error' => 'invalid_email',
                'message' => __('Invalid email address.', 'zero-friction-login')
            );
        }

        $verified = $this->security->verify_otp($email, $otp);

        if (!$verified) {
            $this->log_event($email, 'otp_verification_failed');
            return array(
                'success' => false,
                'error' => 'invalid_otp',
                'message' => __('Invalid or expired OTP.', 'zero-friction-login')
            );
        }

        $this->log_event($email, 'otp_verified');

        $user = get_user_by('email', $email);

        if ($user) {
            $login_result = $this->login_user($user);
            if ($login_result['success']) {
                $this->log_event($email, 'login_success');
            }
            return $login_result;
        } else {
            $guest_token = $this->security->generate_magic_token();
            $this->security->store_guest_session($email, $guest_token, 24);

            $this->log_event($email, 'guest_session_created');

            return array(
                'success' => true,
                'user_exists' => false,
                'guest_token' => $guest_token,
                'email' => $email,
                'message' => __('Verification successful. Guest session created.', 'zero-friction-login')
            );
        }
    }

    public function login_user($user) {
        if (!is_a($user, 'WP_User')) {
            zfl_log('Invalid user object in login_user().', 'error');
            return array(
                'success' => false,
                'error' => 'invalid_user',
                'message' => __('Invalid user object.', 'zero-friction-login')
            );
        }

        wp_clear_auth_cookie();
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        do_action('wp_login', $user->user_login, $user);

        return array(
            'success' => true,
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'message' => __('Login successful.', 'zero-friction-login')
        );
    }

    public function create_user_from_guest($email, $guest_token) {
        $email = $this->security->sanitize_email($email);

        if (!$this->security->validate_email($email)) {
            return array(
                'success' => false,
                'error' => 'invalid_email',
                'message' => __('Invalid email address.', 'zero-friction-login')
            );
        }

        $verified_email = $this->security->verify_guest_session($guest_token);
        if ($verified_email !== $email) {
            return array(
                'success' => false,
                'error' => 'invalid_token',
                'message' => __('Invalid or expired guest session.', 'zero-friction-login')
            );
        }

        if (email_exists($email)) {
            return array(
                'success' => false,
                'error' => 'user_exists',
                'message' => __('A user with this email already exists.', 'zero-friction-login')
            );
        }

        $username = $this->generate_username_from_email($email);
        $password = wp_generate_password(32, true, true);

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return array(
                'success' => false,
                'error' => 'creation_failed',
                'message' => $user_id->get_error_message()
            );
        }

        $this->security->invalidate_guest_session($guest_token);

        $user = get_user_by('id', $user_id);
        $login_result = $this->login_user($user);

        $this->log_event($email, 'user_created_from_guest');

        if ($login_result['success']) {
            $login_result['user_created'] = true;
        }

        return $login_result;
    }

    private function generate_username_from_email($email) {
        $base_username = sanitize_user(current(explode('@', $email)), true);
        $base_username = str_replace('.', '', $base_username);

        if (empty($base_username)) {
            $base_username = 'user';
        }

        $username = $base_username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        return $username;
    }

    public function log_event($email, $event) {
        $ip = $this->rate_limiter->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';

        $this->wpdb->insert(
            $this->audit_log_table,
            array(
                'email' => strtolower(trim($email)),
                'event' => $event,
                'ip' => $ip,
                'user_agent' => substr(sanitize_text_field($user_agent), 0, 500),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }

    public function get_audit_logs($email = null, $limit = 100) {
        $limit = max(1, intval($limit));

        if ($email) {
            // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->audit_log_table} WHERE email = %s ORDER BY created_at DESC LIMIT %d",
                    strtolower(trim($email)),
                    $limit
                )
            );
        }

        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is plugin-owned and derived from $wpdb->prefix.
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->audit_log_table} ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }
}
