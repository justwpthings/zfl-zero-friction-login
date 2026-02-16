<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Login_Redirector {

    public function __construct() {
        add_action('init', array($this, 'handle_login_redirect'), 1);
        add_action('template_redirect', array($this, 'handle_template_redirect'), 1);
        add_filter('logout_redirect', array($this, 'handle_logout_redirect'), 10, 3);
    }

    public function handle_login_redirect() {
        if (!get_option('zfl_force_custom_login', false)) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        global $pagenow;
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

        if ($pagenow === 'wp-login.php' && $action === '') {
            $redirect_to = isset($_REQUEST['redirect_to']) ? (string) wp_unslash($_REQUEST['redirect_to']) : '';
            $this->redirect_to_custom_login($redirect_to);
        }

        if ($pagenow === 'wp-login.php' && $action === 'logout') {
            return;
        }
    }

    public function handle_template_redirect() {
        if (is_user_logged_in()) {
            return;
        }

        if (!get_option('zfl_force_custom_login', false)) {
            return;
        }

        if (is_admin() && !wp_doing_ajax()) {
            $redirect_to = admin_url();
            $this->redirect_to_custom_login($redirect_to);
        }

        if (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
            if ($request_uri !== '') {
                $current_url = home_url(add_query_arg(array(), $request_uri));
                $this->redirect_to_custom_login($current_url);
            }
        }

        if (get_option('zfl_force_login_checkout', false)) {
            if (function_exists('is_checkout') && is_checkout() && !is_user_logged_in()) {
                $checkout_url = wc_get_checkout_url();
                $this->set_redirect_cookie($checkout_url, time() + 3600);
                $this->redirect_to_custom_login($checkout_url);
            }
        }
    }

    private function redirect_to_custom_login($redirect_to = '') {
        $custom_url = esc_url_raw((string) get_option('zfl_custom_login_page_url', ''));
        $custom_page_id = get_option('zfl_custom_login_page', 0);

        if (!empty($custom_url)) {
            $login_url = $custom_url;
        } elseif ($custom_page_id) {
            $login_url = get_permalink($custom_page_id);
        } else {
            return;
        }

        $safe_redirect = self::sanitize_internal_redirect($redirect_to);
        if (!empty($safe_redirect)) {
            $login_url = add_query_arg('redirect_to', $safe_redirect, $login_url);
        }

        wp_safe_redirect($login_url);
        exit;
    }

    public static function get_redirect_url() {
        if (isset($_GET['redirect_to'])) {
            $redirect = self::sanitize_internal_redirect(wp_unslash($_GET['redirect_to']));
            if (!empty($redirect)) {
                return $redirect;
            }
        }

        if (isset($_COOKIE['zfl_redirect_after_login'])) {
            $redirect = self::sanitize_internal_redirect(wp_unslash($_COOKIE['zfl_redirect_after_login']));
            self::clear_redirect_cookie();
            if (!empty($redirect)) {
                return $redirect;
            }
        }

        return '';
    }

    private function set_redirect_cookie($redirect_url, $expires) {
        $safe_redirect = self::sanitize_internal_redirect($redirect_url);
        if (empty($safe_redirect) || headers_sent()) {
            return;
        }

        setcookie('zfl_redirect_after_login', $safe_redirect, self::get_cookie_options($expires));
    }

    private static function clear_redirect_cookie() {
        if (headers_sent()) {
            return;
        }

        setcookie('zfl_redirect_after_login', '', self::get_cookie_options(time() - HOUR_IN_SECONDS));
    }

    private static function get_cookie_options($expires) {
        return array(
            'expires' => intval($expires),
            'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined('COOKIE_DOMAIN') && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        );
    }

    private static function sanitize_internal_redirect($url) {
        if (!is_string($url) || $url === '') {
            return '';
        }

        $validated = wp_validate_redirect(trim($url), '');
        if (empty($validated)) {
            return '';
        }

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);
        $target_host = wp_parse_url($validated, PHP_URL_HOST);

        if (!empty($target_host) && !empty($home_host) && strtolower($target_host) !== strtolower($home_host)) {
            return '';
        }

        return $validated;
    }

    public function handle_logout_redirect($redirect_to, $requested_redirect_to, $user) {
        unset($user);

        $redirect_setting = get_option('zfl_redirect_after_logout', 'same_page');
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
            default:
                $safe_requested = self::sanitize_internal_redirect((string) $requested_redirect_to);
                if (!empty($safe_requested)) {
                    return $safe_requested;
                }

                $safe_default = self::sanitize_internal_redirect((string) $redirect_to);
                if (!empty($safe_default)) {
                    return $safe_default;
                }

                return home_url();
        }
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
}
