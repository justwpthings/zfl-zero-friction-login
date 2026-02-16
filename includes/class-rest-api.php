<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_REST_API {

    private $namespace = 'zfl/v1';
    private $security;
    private $rate_limiter;
    private $auth_handler;
    private $otps_table;

    public function __construct() {
        global $wpdb;

        $this->security = new ZFL_Security();
        $this->rate_limiter = new ZFL_Rate_Limiter();
        $this->auth_handler = new ZFL_Auth_Handler();
        $this->otps_table = $wpdb->prefix . 'zfl_otps';

        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'ensure_tables_exist'), 5);
    }

    public function ensure_tables_exist() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zfl_otps';
        $table_exists = $this->table_exists($table_name);

        if (!$table_exists) {
            zfl_log('Database tables not found. Attempting to create them.', 'warning');
            require_once ZFL_PLUGIN_DIR . 'includes/class-database.php';
            $database = new ZFL_Database();
            $database->create_tables();
            zfl_log('Database tables creation attempted.', 'info');
        }
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/request-auth', array(
            'methods' => 'POST',
            'callback' => array($this, 'request_auth'),
            'permission_callback' => array($this, 'check_public_permission'),
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email'
                ),
                'display_name' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        register_rest_route($this->namespace, '/verify-otp', array(
            'methods' => 'POST',
            'callback' => array($this, 'verify_otp'),
            'permission_callback' => array($this, 'check_public_permission'),
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => 'is_email'
                ),
                'otp' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));

        register_rest_route($this->namespace, '/verify-magic', array(
            'methods' => 'GET',
            'callback' => array($this, 'verify_magic'),
            'permission_callback' => array($this, 'check_public_permission'),
            'args' => array(
                'token' => array(
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ),
                'email' => array(
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_email'
                )
            )
        ));

        register_rest_route($this->namespace, '/config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_config'),
            'permission_callback' => '__return_true'
        ));

        register_rest_route($this->namespace, '/init-check', array(
            'methods' => 'GET',
            'callback' => array($this, 'check_initialization'),
            'permission_callback' => array($this, 'check_admin_permission')
        ));

        register_rest_route($this->namespace, '/logout', array(
            'methods' => 'POST',
            'callback' => array($this, 'logout_user'),
            'permission_callback' => array($this, 'check_logout_permission')
        ));
    }

    public function check_initialization() {
        global $wpdb;

        $tables = array(
            'zfl_otps' => $wpdb->prefix . 'zfl_otps',
            'zfl_rate_limits' => $wpdb->prefix . 'zfl_rate_limits',
            'zfl_audit_log' => $wpdb->prefix . 'zfl_audit_log',
            'zfl_guest_sessions' => $wpdb->prefix . 'zfl_guest_sessions'
        );

        $status = array();
        $all_exist = true;

        foreach ($tables as $name => $table) {
            $exists = $this->table_exists($table);
            $status[$name] = $exists;
            if (!$exists) {
                $all_exist = false;
            }
        }

        return new WP_REST_Response(array(
            'initialized' => $all_exist,
            'tables' => $status,
            'plugin_version' => ZFL_VERSION,
            'db_version' => get_option('zfl_db_version', 'not_set')
        ), 200);
    }

    public function check_public_permission($request) {
        if (!$request instanceof WP_REST_Request) {
            return true;
        }

        $method = strtoupper((string) $request->get_method());
        if (in_array($method, array('GET', 'HEAD', 'OPTIONS'), true)) {
            return true;
        }

        if ($this->has_valid_rest_nonce($request) || $this->is_same_origin_request($request)) {
            return true;
        }

        /**
         * Allows site owners to keep legacy integrations that call public POST routes
         * without a nonce/origin header. Disabled by default for safer installs.
         */
        $allow_unsafe = (bool) apply_filters('zfl_allow_public_api_without_nonce', false, $request);
        if ($allow_unsafe) {
            return true;
        }

        return new WP_Error(
            'zfl_invalid_nonce',
            __('Security check failed.', 'zero-friction-login'),
            array('status' => 403)
        );
    }

    public function check_admin_permission() {
        return current_user_can('manage_options');
    }

    public function check_logout_permission($request) {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'zfl_forbidden',
                __('You are not allowed to do that.', 'zero-friction-login'),
                array('status' => 401)
            );
        }

        if (!$this->has_valid_rest_nonce($request)) {
            return new WP_Error(
                'zfl_invalid_nonce',
                __('Security check failed.', 'zero-friction-login'),
                array('status' => 403)
            );
        }

        return true;
    }

    public function request_auth($request) {
        $email = $request->get_param('email');
        $email = $this->security->sanitize_email($email);
        $display_name = $request->get_param('display_name');
        $is_registration = !empty($display_name);
        $allow_registration = (bool)get_option('zfl_allow_registration', true);

        if (!$this->security->validate_email($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Invalid email address.', 'zero-friction-login'),
                'error_type' => 'validation_error'
            ), 400);
        }

        if ($is_registration && !$allow_registration) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Registration is currently disabled.', 'zero-friction-login'),
                'error_type' => 'registration_disabled'
            ), 403);
        }

        $user_exists = get_user_by('email', $email) !== false;
        $is_login_without_account = !$is_registration && !$user_exists;

        if ($is_registration && $user_exists) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('An account with this email already exists. Please use the login form instead.', 'zero-friction-login'),
                'error_type' => 'user_exists'
            ), 400);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'zfl_otps';
        $table_exists = $this->table_exists($table_name);

        if (!$table_exists) {
            zfl_log('Database tables not initialized.', 'error');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('System not properly initialized. Please contact administrator.', 'zero-friction-login'),
                'error_type' => 'database_error'
            ), 500);
        }

        $email_check = $this->rate_limiter->check_email_limit($email);
        if (!$email_check['allowed']) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $email_check['message'],
                'reason' => $email_check['reason'],
                'error_type' => 'rate_limit'
            ), 429);
        }

        $ip = $this->rate_limiter->get_client_ip();
        $ip_check = $this->rate_limiter->check_ip_limit($ip);
        if (!$ip_check['allowed']) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $ip_check['message'],
                'reason' => $ip_check['reason'],
                'error_type' => 'rate_limit'
            ), 429);
        }

        if ($is_registration) {
            set_transient('zfl_display_name_' . md5($email), sanitize_text_field((string) $display_name), 1800);
            set_transient('zfl_is_registration_' . md5($email), true, 1800);
        }

        $login_method = get_option('zfl_login_method', '6_digit_numeric');
        $otp_expiry = get_option('zfl_otp_expiry', 5);

        $otp_params = $this->get_otp_params($login_method);

        if ($login_method === 'magic_link') {
            if (!$is_login_without_account) {
                $token = $this->security->generate_magic_token();
                $stored = $this->security->store_otp($email, $token, 'magic_link', $otp_expiry);

                if (!$stored) {
                    zfl_log('Failed to store magic link token.', 'error');
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => __('Failed to generate magic link. Please try again.', 'zero-friction-login'),
                        'error_type' => 'database_error'
                    ), 500);
                }

                $magic_link = site_url('/wp-json/zfl/v1/verify-magic?token=' . $token . '&email=' . urlencode($email));
                $email_sent = $this->send_email($email, null, $magic_link);

                if (!$email_sent) {
                    zfl_log('Failed to send magic link email.', 'error');
                }
            }

            $this->rate_limiter->record_attempt('email_' . hash('sha256', $email));
            $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));

            if ($is_login_without_account) {
                $this->auth_handler->log_event($email, 'login_attempt_nonexistent_user');
            } else {
                $this->auth_handler->log_event($email, 'magic_link_sent');
            }

            if (!$is_registration) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'method' => 'magic_link',
                    'expires_in' => $otp_expiry * 60,
                    'message' => __('If an account with this email exists, a sign-in link has been sent.', 'zero-friction-login'),
                    'show_info_notice' => true
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'method' => 'magic_link',
                'expires_in' => $otp_expiry * 60,
                'message' => __('Magic link sent to your email.', 'zero-friction-login')
            ), 200);
        } else {
            if (!$is_login_without_account) {
                $otp = $this->security->generate_otp($otp_params['length'], $otp_params['type']);
                $stored = $this->security->store_otp($email, $otp, 'otp', $otp_expiry);

                if (!$stored) {
                    zfl_log('Failed to store OTP.', 'error');
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => __('Failed to generate OTP. Please try again.', 'zero-friction-login'),
                        'error_type' => 'database_error'
                    ), 500);
                }

                $email_sent = $this->send_email($email, $otp, null);

                if (!$email_sent) {
                    zfl_log('Failed to send OTP email.', 'error');
                }
            }

            $this->rate_limiter->record_attempt('email_' . hash('sha256', $email));
            $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));

            if ($is_login_without_account) {
                $this->auth_handler->log_event($email, 'login_attempt_nonexistent_user');
            } else {
                $this->auth_handler->log_event($email, 'otp_requested');
            }

            if (!$is_registration) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'method' => 'otp',
                    'otp_length' => $otp_params['length'],
                    'otp_type' => $otp_params['type'],
                    'expires_in' => $otp_expiry * 60,
                    'message' => __('If an account with this email exists, a code has been sent.', 'zero-friction-login'),
                    'show_info_notice' => true
                ), 200);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'method' => 'otp',
                'otp_length' => $otp_params['length'],
                'otp_type' => $otp_params['type'],
                'expires_in' => $otp_expiry * 60,
                'message' => __('OTP sent to your email.', 'zero-friction-login')
            ), 200);
        }
    }

    public function verify_otp($request) {
        $email = $request->get_param('email');
        $email = $this->security->sanitize_email($email);
        $otp = trim($request->get_param('otp'));

        if (!$this->security->validate_email($email)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Invalid email address.', 'zero-friction-login'),
                'error_type' => 'validation_error'
            ), 400);
        }

        if (empty($otp)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('OTP is required.', 'zero-friction-login'),
                'error_type' => 'validation_error'
            ), 400);
        }

        $ip = $this->rate_limiter->get_client_ip();
        $ip_check = $this->rate_limiter->check_ip_limit($ip);
        if (!$ip_check['allowed']) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $ip_check['message'],
                'error_type' => 'rate_limit'
            ), 429);
        }

        $verified = $this->security->verify_otp($email, $otp);

        if (!$verified) {
            $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));
            $this->auth_handler->log_event($email, 'otp_verification_failed');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('Invalid or expired OTP. Please request a new code.', 'zero-friction-login'),
                'error_type' => 'verification_failed'
            ), 401);
        }

        $this->auth_handler->log_event($email, 'otp_verified');

        $user = get_user_by('email', $email);

        if ($user) {
            $login_result = $this->auth_handler->login_user($user);

            if ($login_result['success']) {
                $this->auth_handler->log_event($email, 'login_success');
                $redirect_url = $this->get_redirect_url();

                return new WP_REST_Response(array(
                    'success' => true,
                    'user_exists' => true,
                    'user_id' => $user->ID,
                    'redirect_url' => $redirect_url,
                    'message' => __('Login successful.', 'zero-friction-login')
                ), 200);
            } else {
                zfl_log('OTP login failed for existing user.', 'error');
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $login_result['message']
                ), 500);
            }
        } else {
            $is_registration = get_transient('zfl_is_registration_' . md5($email));
            $allow_registration = (bool)get_option('zfl_allow_registration', true);

            if (!$is_registration || !$allow_registration) {
                $this->auth_handler->log_event($email, 'otp_verified_no_account');
                delete_transient('zfl_display_name_' . md5($email));
                delete_transient('zfl_is_registration_' . md5($email));

                if (!$allow_registration && $is_registration) {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => __('Registration is currently disabled.', 'zero-friction-login'),
                        'error_type' => 'registration_disabled'
                    ), 403);
                }

                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('No account found with this email address. Please register first.', 'zero-friction-login'),
                    'error_type' => 'user_not_found'
                ), 401);
            }

            $display_name = get_transient('zfl_display_name_' . md5($email));
            if (!$display_name) {
                $display_name = $this->generate_display_name_from_email($email);
            }
            delete_transient('zfl_display_name_' . md5($email));
            delete_transient('zfl_is_registration_' . md5($email));

            $username = $this->generate_unique_username($email);
            $password = wp_generate_password(20, true, true);

            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_email' => $email,
                'user_pass' => $password,
                'display_name' => $display_name,
                'first_name' => $display_name,
                'role' => 'subscriber'
            ));

            if (is_wp_error($user_id)) {
                $this->auth_handler->log_event($email, 'user_creation_failed');
                zfl_log('Failed to create user after OTP verification.', 'error');
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => __('Failed to create user account. Please try again.', 'zero-friction-login')
                ), 500);
            }

            $this->auth_handler->log_event($email, 'user_created');
            $user = get_user_by('id', $user_id);
            $login_result = $this->auth_handler->login_user($user);

            if ($login_result['success']) {
                $this->auth_handler->log_event($email, 'login_success');
                $redirect_url = $this->get_redirect_url();

                return new WP_REST_Response(array(
                    'success' => true,
                    'user_exists' => false,
                    'user_created' => true,
                    'user_id' => $user_id,
                    'redirect_url' => $redirect_url,
                    'message' => __('Account created and logged in successfully.', 'zero-friction-login')
                ), 200);
            } else {
                zfl_log('OTP login failed for newly created user.', 'error');
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => $login_result['message']
                ), 500);
            }
        }
    }

    public function verify_magic($request) {
        $token = trim((string)$request->get_param('token'));
        $email_param = $request->get_param('email');
        $email = !empty($email_param) ? $this->security->sanitize_email($email_param) : '';
        $ip = $this->rate_limiter->get_client_ip();

        if (empty($token)) {
            $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));
            wp_safe_redirect(home_url('?zfl_error=invalid_token'));
            exit;
        }

        $ip_check = $this->rate_limiter->check_ip_limit($ip);
        if (!$ip_check['allowed']) {
            wp_safe_redirect(home_url('?zfl_error=rate_limited'));
            exit;
        }

        global $wpdb;
        $current_time = current_time('mysql');
        $token_hash = $this->security->hash_otp($token);
        $users_table = $wpdb->users;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Querying plugin-owned OTP table directly.
        $stored = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->otps_table}
                WHERE otp_hash = %s
                AND type = 'magic_link'
                AND expires_at > %s
                LIMIT 1",
                $token_hash,
                $current_time
            )
        );

        if (!$stored) {
            $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));
            $this->auth_handler->log_event($email ?: 'unknown', 'magic_link_verification_failed');
            wp_safe_redirect(home_url('?zfl_error=invalid_or_expired'));
            exit;
        }

        if ($email) {
            $email_hash = $this->security->hash_email($email);
            if ($stored->email_hash !== $email_hash) {
                $this->rate_limiter->record_attempt('ip_' . hash('sha256', $ip));
                $this->auth_handler->log_event($email, 'magic_link_email_mismatch');
                wp_safe_redirect(home_url('?zfl_error=invalid_token'));
                exit;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting one-time token from plugin-owned table.
        $wpdb->delete(
            $this->otps_table,
            array('id' => $stored->id),
            array('%d')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Matching hashed email against core users table.
        $users = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT user_email FROM {$users_table}
                WHERE MD5(LOWER(user_email)) = %s",
                md5(strtolower($email))
            )
        );

        $actual_email = null;
        foreach ($users as $u) {
            if ($this->security->hash_email($u->user_email) === $stored->email_hash) {
                $actual_email = $u->user_email;
                break;
            }
        }

        if (!$actual_email && $email) {
            $actual_email = $email;
        }

        if (!$actual_email) {
            wp_safe_redirect(home_url('?zfl_error=user_not_found'));
            exit;
        }

        $user = get_user_by('email', $actual_email);

        if ($user) {
            $login_result = $this->auth_handler->login_user($user);

            if ($login_result['success']) {
                $this->auth_handler->log_event($actual_email, 'magic_link_login_success');
                $redirect_url = $this->get_redirect_url();
                wp_safe_redirect($redirect_url);
                exit;
            } else {
                zfl_log('Magic link login failed for existing user.', 'error');
                wp_safe_redirect(home_url('?zfl_error=login_failed'));
                exit;
            }
        } else {
            $is_registration = get_transient('zfl_is_registration_' . md5($actual_email));
            $allow_registration = (bool)get_option('zfl_allow_registration', true);

            if (!$is_registration || !$allow_registration) {
                $this->auth_handler->log_event($actual_email, 'magic_link_verified_no_account');
                delete_transient('zfl_display_name_' . md5($actual_email));
                delete_transient('zfl_is_registration_' . md5($actual_email));
                wp_safe_redirect(home_url('?zfl_error=user_not_found'));
                exit;
            }

            $display_name = get_transient('zfl_display_name_' . md5($actual_email));
            if (!$display_name) {
                $display_name = $this->generate_display_name_from_email($actual_email);
            }
            delete_transient('zfl_display_name_' . md5($actual_email));
            delete_transient('zfl_is_registration_' . md5($actual_email));

            $username = $this->generate_unique_username($actual_email);
            $password = wp_generate_password(20, true, true);

            $user_id = wp_insert_user(array(
                'user_login' => $username,
                'user_email' => $actual_email,
                'user_pass' => $password,
                'display_name' => $display_name,
                'first_name' => $display_name,
                'role' => 'subscriber'
            ));

            if (is_wp_error($user_id)) {
                $this->auth_handler->log_event($actual_email, 'user_creation_failed_magic');
                zfl_log('Failed to create user via magic link.', 'error');
                wp_safe_redirect(home_url('?zfl_error=user_creation_failed'));
                exit;
            }

            $this->auth_handler->log_event($actual_email, 'user_created_via_magic');
            $user = get_user_by('id', $user_id);
            $login_result = $this->auth_handler->login_user($user);

            if ($login_result['success']) {
                $this->auth_handler->log_event($actual_email, 'magic_link_login_success');
                $redirect_url = $this->get_redirect_url();
                wp_safe_redirect($redirect_url);
                exit;
            } else {
                zfl_log('Magic link login failed for newly created user.', 'error');
                wp_safe_redirect(home_url('?zfl_error=login_failed'));
                exit;
            }
        }
    }

    public function get_config($request) {
        $login_method = get_option('zfl_login_method', '6_digit_numeric');
        $otp_expiry = get_option('zfl_otp_expiry', 5);
        $otp_params = $this->get_otp_params($login_method);

        $terms_page_id = get_option('zfl_terms_page', 0);
        $privacy_page_id = get_option('zfl_privacy_page', 0);
        $show_policy_links = get_option('zfl_show_policy_links', true);
        $hide_footer_credit = get_option('zfl_hide_footer_credit', false);
        $allow_registration = get_option('zfl_allow_registration', true);

        $logo_id = get_option('zfl_logo_id', 0);
        $logo_url = null;
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
        }

        $is_logged_in = is_user_logged_in();
        $current_user = null;
        $logout_redirect_url = null;

        if ($is_logged_in) {
            $user = wp_get_current_user();
            $current_user = array(
                'id' => $user->ID,
                'display_name' => $user->display_name,
                'email' => $user->user_email
            );
            $logout_redirect_url = $this->get_logout_redirect_url(false);
        }

        $primary_color = get_option('zfl_primary_color', '#0073aa');
        $button_background = get_option('zfl_button_background', '#0073aa');
        $button_text_color = get_option('zfl_button_text_color', '#ffffff');
        $secondary_button_background = get_option('zfl_secondary_button_background', '#f3f4f6');
        $secondary_button_text_color = get_option('zfl_secondary_button_text_color', '#374151');
        $tab_background = get_option('zfl_tab_background', '#ffffff');
        $tab_text_color = get_option('zfl_tab_text_color', '#6b7280');
        $active_tab_background = get_option('zfl_active_tab_background', '#f9fafb');
        $active_tab_text_color = get_option('zfl_active_tab_text_color', '#111827');
        $card_background = get_option('zfl_card_background', '#ffffff');
        $overlay_background = get_option('zfl_overlay_background', '#f0f0f0');
        $heading_font = get_option('zfl_heading_font', 'system_default');
        $body_font = get_option('zfl_body_font', 'system_default');
        $logo_width = get_option('zfl_logo_width', 150);

        $design_tokens = array(
            'text_primary_color' => get_option('zfl_text_primary_color', '#111827'),
            'text_secondary_color' => get_option('zfl_text_secondary_color', '#6b7280'),
            'text_muted_color' => get_option('zfl_text_muted_color', '#4b5563'),
            'text_label_color' => get_option('zfl_text_label_color', '#374151'),
            'text_inverse_color' => get_option('zfl_text_inverse_color', '#ffffff'),
            'font_size_base' => get_option('zfl_font_size_base', '16px'),
            'font_size_sm' => get_option('zfl_font_size_sm', '14px'),
            'font_size_xs' => get_option('zfl_font_size_xs', '12px'),
            'heading_size_h1' => get_option('zfl_heading_size_h1', '24px'),
            'heading_size_h2' => get_option('zfl_heading_size_h2', '20px'),
            'line_height_base' => get_option('zfl_line_height_base', '1.5'),
            'card_radius' => get_option('zfl_card_radius', '8px'),
            'card_shadow' => get_option('zfl_card_shadow', '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
            'card_padding' => get_option('zfl_card_padding', '20px'),
            'logged_in_card_padding' => get_option('zfl_logged_in_card_padding', '32px'),
            'modal_padding' => get_option('zfl_modal_padding', '24px'),
            'form_gap' => get_option('zfl_form_gap', '16px'),
            'section_gap' => get_option('zfl_section_gap', '16px'),
            'input_background' => get_option('zfl_input_background', '#ffffff'),
            'input_text_color' => get_option('zfl_input_text_color', '#111827'),
            'input_placeholder_color' => get_option('zfl_input_placeholder_color', '#9ca3af'),
            'input_border_color' => get_option('zfl_input_border_color', '#d1d5db'),
            'input_border_color_focus' => get_option('zfl_input_border_color_focus', '#9ca3af'),
            'input_border_width' => get_option('zfl_input_border_width', '1px'),
            'input_radius' => get_option('zfl_input_radius', '8px'),
            'input_focus_ring_color' => get_option('zfl_input_focus_ring_color', '#b2d5e5'),
            'input_focus_ring_width' => get_option('zfl_input_focus_ring_width', '3px'),
            'input_disabled_bg' => get_option('zfl_input_disabled_bg', '#f3f4f6'),
            'input_disabled_text' => get_option('zfl_input_disabled_text', '#9ca3af'),
            'input_error_border' => get_option('zfl_input_error_border', '#ef4444'),
            'input_padding_x' => get_option('zfl_input_padding_x', '12px'),
            'input_padding_y' => get_option('zfl_input_padding_y', '10px'),
            'otp_box_bg' => get_option('zfl_otp_box_bg', '#ffffff'),
            'otp_box_text_color' => get_option('zfl_otp_box_text_color', '#111827'),
            'otp_box_border_color' => get_option('zfl_otp_box_border_color', '#d1d5db'),
            'otp_box_border_color_filled' => get_option('zfl_otp_box_border_color_filled', '#9ca3af'),
            'otp_box_border_color_active' => get_option('zfl_otp_box_border_color_active', '#0073aa'),
            'otp_box_border_color_error' => get_option('zfl_otp_box_border_color_error', '#ef4444'),
            'otp_box_border_width' => get_option('zfl_otp_box_border_width', '2px'),
            'otp_box_radius' => get_option('zfl_otp_box_radius', '8px'),
            'otp_box_size_6_w' => get_option('zfl_otp_box_size_6_w', '44px'),
            'otp_box_size_6_h' => get_option('zfl_otp_box_size_6_h', '48px'),
            'otp_box_size_8_w' => get_option('zfl_otp_box_size_8_w', '48px'),
            'otp_box_size_8_h' => get_option('zfl_otp_box_size_8_h', '56px'),
            'otp_box_font_size' => get_option('zfl_otp_box_font_size', '20px'),
            'otp_box_gap' => get_option('zfl_otp_box_gap', '8px'),
            'otp_box_disabled_bg' => get_option('zfl_otp_box_disabled_bg', '#f3f4f6'),
            'button_radius' => get_option('zfl_button_radius', '8px'),
            'button_padding_x' => get_option('zfl_button_padding_x', '16px'),
            'button_padding_y' => get_option('zfl_button_padding_y', '10px'),
            'button_hover_background' => get_option('zfl_button_hover_background', '#006799'),
            'button_active_background' => get_option('zfl_button_active_background', '#006190'),
            'button_disabled_background' => get_option('zfl_button_disabled_background', '#d1d5db'),
            'button_disabled_text' => get_option('zfl_button_disabled_text', '#6b7280'),
            'secondary_button_hover_background' => get_option('zfl_secondary_button_hover_background', '#dadbdd'),
            'secondary_button_active_background' => get_option('zfl_secondary_button_active_background', '#cecfd1'),
            'destructive_button_background' => get_option('zfl_destructive_button_background', '#dc2626'),
            'destructive_button_text' => get_option('zfl_destructive_button_text', '#ffffff'),
            'destructive_button_hover_background' => get_option('zfl_destructive_button_hover_background', '#dc2626'),
            'destructive_button_active_background' => get_option('zfl_destructive_button_active_background', '#dc2626'),
            'destructive_button_disabled_background' => get_option('zfl_destructive_button_disabled_background', '#d1d5db'),
            'destructive_button_disabled_text' => get_option('zfl_destructive_button_disabled_text', '#6b7280'),
            'link_color' => get_option('zfl_link_color', '#2563eb'),
            'link_hover_color' => get_option('zfl_link_hover_color', '#1d4ed8'),
            'link_decoration' => get_option('zfl_link_decoration', 'underline'),
            'link_hover_decoration' => get_option('zfl_link_hover_decoration', 'underline'),
            'tab_radius' => get_option('zfl_tab_radius', '8px'),
            'tab_padding_x' => get_option('zfl_tab_padding_x', '16px'),
            'tab_padding_y' => get_option('zfl_tab_padding_y', '10px'),
            'tab_border_width' => get_option('zfl_tab_border_width', '2px'),
            'tab_border_color_inactive' => get_option('zfl_tab_border_color_inactive', 'transparent'),
            'tab_border_color_active' => get_option('zfl_tab_border_color_active', '#0073aa'),
            'notice_radius' => get_option('zfl_notice_radius', '8px'),
            'notice_padding' => get_option('zfl_notice_padding', '12px'),
            'notice_border_width' => get_option('zfl_notice_border_width', '1px'),
            'notice_error_bg' => get_option('zfl_notice_error_bg', '#fef2f2'),
            'notice_error_border' => get_option('zfl_notice_error_border', '#fecaca'),
            'notice_error_text' => get_option('zfl_notice_error_text', '#991b1b'),
            'notice_success_bg' => get_option('zfl_notice_success_bg', '#f0fdf4'),
            'notice_success_border' => get_option('zfl_notice_success_border', '#bbf7d0'),
            'notice_success_text' => get_option('zfl_notice_success_text', '#166534'),
            'notice_info_bg' => get_option('zfl_notice_info_bg', '#eff6ff'),
            'notice_info_border' => get_option('zfl_notice_info_border', '#bfdbfe'),
            'notice_info_text' => get_option('zfl_notice_info_text', '#1e40af'),
            'toast_success_bg' => get_option('zfl_toast_success_bg', '#16a34a'),
            'toast_error_bg' => get_option('zfl_toast_error_bg', '#dc2626'),
            'toast_info_bg' => get_option('zfl_toast_info_bg', '#2563eb'),
            'toast_text_color' => get_option('zfl_toast_text_color', '#ffffff'),
            'toast_radius' => get_option('zfl_toast_radius', '8px'),
            'toast_shadow' => get_option('zfl_toast_shadow', '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
            'toast_padding_x' => get_option('zfl_toast_padding_x', '24px'),
            'toast_padding_y' => get_option('zfl_toast_padding_y', '16px'),
            'toast_max_width' => get_option('zfl_toast_max_width', '448px'),
            'toast_close_color' => get_option('zfl_toast_close_color', '#ffffff'),
            'toast_close_hover_color' => get_option('zfl_toast_close_hover_color', '#e5e7eb'),
            'toast_close_bg' => get_option('zfl_toast_close_bg', 'transparent'),
            'toast_close_hover_bg' => get_option('zfl_toast_close_hover_bg', 'transparent'),
            'magic_icon_bg' => get_option('zfl_magic_icon_bg', '#dbeafe'),
            'magic_icon_color' => get_option('zfl_magic_icon_color', '#2563eb'),
            'success_icon_bg' => get_option('zfl_success_icon_bg', '#dcfce7'),
            'success_icon_color' => get_option('zfl_success_icon_color', '#16a34a'),
            'logged_in_icon_bg' => get_option('zfl_logged_in_icon_bg', '#dcfce7'),
            'logged_in_icon_color' => get_option('zfl_logged_in_icon_color', '#16a34a'),
            'modal_icon_bg' => get_option('zfl_modal_icon_bg', '#fee2e2'),
            'modal_icon_color' => get_option('zfl_modal_icon_color', '#dc2626'),
            'icon_circle_size_sm' => get_option('zfl_icon_circle_size_sm', '64px'),
            'icon_circle_size_md' => get_option('zfl_icon_circle_size_md', '80px'),
            'icon_size_sm' => get_option('zfl_icon_size_sm', '32px'),
            'icon_size_md' => get_option('zfl_icon_size_md', '40px'),
            'modal_background' => get_option('zfl_modal_background', '#ffffff'),
            'modal_text_color' => get_option('zfl_modal_text_color', '#111827'),
            'modal_radius' => get_option('zfl_modal_radius', '8px'),
            'modal_shadow' => get_option('zfl_modal_shadow', '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)'),
            'modal_overlay_color' => get_option('zfl_modal_overlay_color', '#000000'),
            'modal_overlay_opacity' => get_option('zfl_modal_overlay_opacity', '0.5'),
            'spinner_color' => get_option('zfl_spinner_color', '#2563eb'),
            'spinner_success_color' => get_option('zfl_spinner_success_color', '#0073aa'),
            'spinner_size_lg' => get_option('zfl_spinner_size_lg', '48px'),
            'spinner_size_sm' => get_option('zfl_spinner_size_sm', '32px'),
            'spinner_border_width' => get_option('zfl_spinner_border_width', '2px'),
            'footer_text_color' => get_option('zfl_footer_text_color', '#6b7280'),
            'footer_font_size' => get_option('zfl_footer_font_size', '12px'),
            'animation_enabled' => (bool)get_option('zfl_animation_enabled', true),
            'animation_slide_in_duration' => get_option('zfl_animation_slide_in_duration', '300ms'),
            'animation_scale_in_duration' => get_option('zfl_animation_scale_in_duration', '200ms'),
            'transition_duration' => get_option('zfl_transition_duration', '200ms')
        );

        $response_data = array(
            'login_method' => $login_method,
            'otp_length' => $otp_params['length'],
            'otp_type' => $otp_params['type'],
            'expiry_seconds' => $otp_expiry * 60,
            'site_name' => get_bloginfo('name'),
            'terms_page_url' => $terms_page_id ? get_permalink($terms_page_id) : null,
            'privacy_page_url' => $privacy_page_id ? get_permalink($privacy_page_id) : null,
            'show_policy_links' => (bool)$show_policy_links,
            'hide_footer_credit' => (bool)$hide_footer_credit,
            'allow_registration' => (bool)$allow_registration,
            'logo_url' => $logo_url,
            'logo_width' => $logo_width,
            'turnstile_enabled' => false,
            'is_logged_in' => $is_logged_in,
            'current_user' => $current_user,
            'logout_redirect_url' => $logout_redirect_url,
            'primary_color' => $primary_color,
            'button_background' => $button_background,
            'button_text_color' => $button_text_color,
            'secondary_button_background' => $secondary_button_background,
            'secondary_button_text_color' => $secondary_button_text_color,
            'tab_background' => $tab_background,
            'tab_text_color' => $tab_text_color,
            'active_tab_background' => $active_tab_background,
            'active_tab_text_color' => $active_tab_text_color,
            'card_background' => $card_background,
            'overlay_background' => $overlay_background,
            'heading_font' => $heading_font,
            'body_font' => $body_font
        );

        $response_data = array_merge($response_data, $design_tokens);

        return new WP_REST_Response($response_data, 200);
    }

    public function logout_user($request) {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => __('You are not logged in.', 'zero-friction-login')
            ), 401);
        }

        $user = wp_get_current_user();
        $email = $user->user_email;

        wp_logout();

        $this->auth_handler->log_event($email, 'user_logged_out');

        $redirect_url = $this->get_logout_redirect_url(true);

        return new WP_REST_Response(array(
            'success' => true,
            'message' => __('Logged out successfully.', 'zero-friction-login'),
            'redirect_url' => $redirect_url
        ), 200);
    }

    private function get_otp_params($login_method) {
        switch ($login_method) {
            case '6_digit_numeric':
                return array('length' => 6, 'type' => 'numeric');
            case '6_char_alphanumeric':
                return array('length' => 6, 'type' => 'alphanumeric');
            case '8_digit_numeric':
                return array('length' => 8, 'type' => 'numeric');
            case '8_char_alphanumeric':
                return array('length' => 8, 'type' => 'alphanumeric');
            case 'magic_link':
                return array('length' => 0, 'type' => 'magic_link');
            default:
                return array('length' => 6, 'type' => 'numeric');
        }
    }

    private function send_email($to_email, $otp = null, $magic_link = null) {
        $to_email = sanitize_email((string) $to_email);
        if ($to_email === '') {
            return false;
        }

        if ($magic_link) {
            $subject = get_option('zfl_email_subject_magic_link', __('Your magic login link for {SITE_NAME}', 'zero-friction-login'));
            $body = get_option('zfl_email_body_magic_link', $this->get_default_email_body_magic_link());
        } else {
            $subject = get_option('zfl_email_subject_otp', __('Your login code for {SITE_NAME}', 'zero-friction-login'));
            $body = get_option('zfl_email_body_otp', $this->get_default_email_body_otp());
        }

        $placeholders = array(
            '{OTP}' => $otp ?: '',
            '{MAGIC_LINK}' => $magic_link ?: '',
            '{SITE_NAME}' => get_bloginfo('name'),
            '{IP}' => $this->rate_limiter->get_client_ip(),
            '{BROWSER}' => $this->get_browser(),
            '{DEVICE}' => $this->get_device(),
            '{TIME}' => current_time('F j, Y g:i A')
        );

        $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
        $body = str_replace(array_keys($placeholders), array_values($placeholders), $body);

        $headers = array('Content-Type: text/html; charset=UTF-8');

        $enable_smtp = get_option('zfl_enable_smtp', false);
        if ($enable_smtp) {
            add_action('phpmailer_init', array($this, 'configure_smtp'));
        }

        $from_email = get_option('zfl_smtp_from_email', get_option('admin_email'));
        $from_name = get_option('zfl_smtp_from_name', get_bloginfo('name'));
        $from_email = sanitize_email((string) $from_email);
        $from_name = sanitize_text_field((string) $from_name);
        $from_name = str_replace(array("\r", "\n"), '', $from_name);

        $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';

        $body = nl2br($body);

        $logo_id = get_option('zfl_logo_id', 0);
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'full');
            if ($logo_url) {
                $logo_width = get_option('zfl_logo_width', 150);
                $body = '<div style="text-align: center; margin-bottom: 20px;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: ' . intval($logo_width) . 'px; height: auto;"></div>' . $body;
            }
        }

        $result = wp_mail($to_email, $subject, $body, $headers);

        if ($enable_smtp) {
            remove_action('phpmailer_init', array($this, 'configure_smtp'));
        }

        return $result;
    }

    public function configure_smtp($phpmailer) {
        $phpmailer->isSMTP();
        $phpmailer->Host = get_option('zfl_smtp_host', '');
        $phpmailer->SMTPAuth = true;
        $phpmailer->Port = get_option('zfl_smtp_port', 587);
        $phpmailer->Username = get_option('zfl_smtp_username', '');
        $phpmailer->Password = get_option('zfl_smtp_password', '');

        $encryption = get_option('zfl_smtp_encryption', 'tls');
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = 'ssl';
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = 'tls';
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAuth = false;
        }
    }

    private function get_browser() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return __('Unknown', 'zero-friction-login');
        }

        $user_agent = (string) wp_unslash($_SERVER['HTTP_USER_AGENT']);

        if (strpos($user_agent, 'Firefox') !== false) {
            return __('Firefox', 'zero-friction-login');
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            return __('Chrome', 'zero-friction-login');
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return __('Safari', 'zero-friction-login');
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return __('Edge', 'zero-friction-login');
        } elseif (strpos($user_agent, 'Opera') !== false || strpos($user_agent, 'OPR') !== false) {
            return __('Opera', 'zero-friction-login');
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            return __('Internet Explorer', 'zero-friction-login');
        }

        return __('Unknown', 'zero-friction-login');
    }

    private function get_device() {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return __('Unknown', 'zero-friction-login');
        }

        $user_agent = (string) wp_unslash($_SERVER['HTTP_USER_AGENT']);

        if (strpos($user_agent, 'Mobile') !== false || strpos($user_agent, 'Android') !== false) {
            return __('Mobile', 'zero-friction-login');
        } elseif (strpos($user_agent, 'Tablet') !== false || strpos($user_agent, 'iPad') !== false) {
            return __('Tablet', 'zero-friction-login');
        }

        return __('Desktop', 'zero-friction-login');
    }

    private function get_redirect_url() {
        $redirect_url = ZFL_Login_Redirector::get_redirect_url();
        if (!empty($redirect_url)) {
            return $redirect_url;
        }

        $redirect_setting = get_option('zfl_redirect_after_login', 'same_page');
        $page_redirect = $this->resolve_page_redirect($redirect_setting, 'zfl_redirect_login_page_id');
        if (!empty($page_redirect)) {
            return $page_redirect;
        }

        switch ($redirect_setting) {
            case 'page':
                $page_id = get_option('zfl_redirect_login_page_id', 0);
                return $page_id ? get_permalink($page_id) : home_url();
            case 'my_account':
                return admin_url('profile.php');
            case 'custom_url':
                $custom_url = esc_url_raw((string) get_option('zfl_custom_login_url', ''));
                return !empty($custom_url) ? $custom_url : home_url();
            case 'same_page':
            default:
                if (isset($_SERVER['HTTP_REFERER'])) {
                    $referer = $this->sanitize_same_site_redirect(wp_unslash($_SERVER['HTTP_REFERER']));
                    if (!empty($referer)) {
                        return $referer;
                    }
                }
                return home_url();
        }
    }

    private function sanitize_same_site_redirect($url) {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $validated = wp_validate_redirect(trim($url), '');
        if (empty($validated)) {
            return '';
        }

        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $target_host = wp_parse_url($validated, PHP_URL_HOST);

        if (!empty($target_host) && !empty($site_host) && strtolower($target_host) !== strtolower($site_host)) {
            return '';
        }

        return $validated;
    }

    private function table_exists($table_name) {
        global $wpdb;

        $table_name = sanitize_text_field((string)$table_name);
        if ($table_name === '') {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Lightweight table existence check on activation/runtime.
        $result = $wpdb->get_var(
            $wpdb->prepare('SHOW TABLES LIKE %s', $table_name)
        );

        return $result === $table_name;
    }

    private function resolve_page_redirect($value, $legacy_option_key) {
        $value = is_scalar($value) ? (string) $value : '';

        if (strpos($value, 'page:') === 0) {
            $page_id = intval(substr($value, 5));
            return $page_id ? get_permalink($page_id) : home_url();
        }

        if (ctype_digit($value)) {
            $page_id = absint($value);
            return $page_id ? get_permalink($page_id) : home_url();
        }

        if ($value === 'page' || $value === 'select_page') {
            $page_id = intval(get_option($legacy_option_key, 0));
            return $page_id ? get_permalink($page_id) : home_url();
        }

        return '';
    }

    private function get_logout_redirect_url($use_referer_for_same_page = true) {
        $redirect_setting = get_option('zfl_redirect_after_logout', 'same_page');
        $redirect_url = home_url();
        $page_redirect = $this->resolve_page_redirect($redirect_setting, 'zfl_redirect_logout_page_id');

        if (!empty($page_redirect)) {
            return $page_redirect;
        }

        switch ($redirect_setting) {
            case 'home':
                return home_url();
            case 'custom_url':
                $custom_url = esc_url_raw((string) get_option('zfl_custom_logout_url', ''));
                return !empty($custom_url) ? $custom_url : home_url();
            case 'same_page':
                if ($use_referer_for_same_page && isset($_SERVER['HTTP_REFERER'])) {
                    $referer = $this->sanitize_same_site_redirect(wp_unslash($_SERVER['HTTP_REFERER']));
                    if (!empty($referer)) {
                        return $referer;
                    }
                }
                return home_url();
            default:
                return $redirect_url;
        }
    }

    private function generate_unique_username($email) {
        $username = sanitize_user(substr($email, 0, strpos($email, '@')));
        $original_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $original_username . $counter;
            $counter++;
        }

        return $username;
    }

    private function generate_display_name_from_email($email) {
        $name = substr($email, 0, strpos($email, '@'));
        $name = str_replace(array('.', '_', '-'), ' ', $name);
        $name = ucwords($name);
        return $name;
    }

    private function get_default_email_body_otp() {
        return __("Hello,\n\nYour login code is: {OTP}\n\nThis code will expire in 5 minutes.\n\nRequest details:\n- Time: {TIME}\n- IP Address: {IP}\n- Browser: {BROWSER}\n\nIf you didn't request this code, please ignore this email.\n\nThank you,\n{SITE_NAME}", 'zero-friction-login');
    }

    private function get_default_email_body_magic_link() {
        return __("Hello,\n\nClick the link below to log in instantly:\n\n{MAGIC_LINK}\n\nThis link will expire in 5 minutes.\n\nRequest details:\n- Time: {TIME}\n- IP Address: {IP}\n- Browser: {BROWSER}\n\nIf you didn't request this link, please ignore this email.\n\nThank you,\n{SITE_NAME}", 'zero-friction-login');
    }

    private function has_valid_rest_nonce($request) {
        $nonce = '';

        if ($request instanceof WP_REST_Request) {
            $nonce = (string) $request->get_header('X-WP-Nonce');
        }

        if ($nonce === '') {
            $nonce = isset($_SERVER['HTTP_X_WP_NONCE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_NONCE'])) : '';
        }

        return $nonce !== '' && wp_verify_nonce($nonce, 'wp_rest');
    }

    private function is_same_origin_request($request) {
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if (empty($site_host)) {
            return false;
        }

        $origin = '';
        if ($request instanceof WP_REST_Request) {
            $origin = (string) $request->get_header('Origin');
        }
        if ($origin === '' && isset($_SERVER['HTTP_ORIGIN'])) {
            $origin = sanitize_text_field((string) wp_unslash($_SERVER['HTTP_ORIGIN']));
        }

        if ($origin !== '') {
            $origin_host = wp_parse_url($origin, PHP_URL_HOST);
            if (!empty($origin_host) && strtolower($origin_host) === strtolower($site_host)) {
                return true;
            }
        }

        $referer = '';
        if ($request instanceof WP_REST_Request) {
            $referer = (string) $request->get_header('Referer');
        }
        if ($referer === '' && isset($_SERVER['HTTP_REFERER'])) {
            $referer = sanitize_text_field((string) wp_unslash($_SERVER['HTTP_REFERER']));
        }

        if ($referer !== '') {
            $referer_host = wp_parse_url($referer, PHP_URL_HOST);
            if (!empty($referer_host) && strtolower($referer_host) === strtolower($site_host)) {
                return true;
            }
        }

        return false;
    }
}
