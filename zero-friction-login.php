<?php
/**
 * Plugin Name: Zero Friction Login
 * Plugin URI: https://justwpthings.com
 * Description: Passwordless authentication system with OTP and magic link support
 * Version: 1.0.0
 * Requires at least: 6.0
 * Tested up to: 6.9.1
 * Requires PHP: 8.0
 * Author: JustWPThings
 * Author URI: https://justwpthings.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zero-friction-login
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ZFL_VERSION', '1.0.0');
define('ZFL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZFL_PLUGIN_URL', plugin_dir_url(__FILE__));

if (!function_exists('zfl_log')) {
    /**
     * Debug logger for plugin internals.
     *
     * Logs only when WP_DEBUG is enabled to avoid noisy production logs.
     *
     * @param string $message Log message.
     * @param string $level   Log level.
     * @return void
     */
    function zfl_log($message, $level = 'debug') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $normalized_level = strtoupper(sanitize_key((string) $level));
        $normalized_level = $normalized_level ? $normalized_level : 'DEBUG';
        $normalized_message = sanitize_text_field((string) $message);

        /**
         * Allow development environments to hook custom logging without relying
         * on direct error_log() calls in distributed plugin code.
         */
        do_action('zfl_debug_log', $normalized_message, $normalized_level);
    }
}

class Zero_Friction_Login {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once ZFL_PLUGIN_DIR . 'includes/class-database.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-security.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-rate-limiter.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-auth-handler.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-rest-api.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once ZFL_PLUGIN_DIR . 'includes/class-login-redirector.php';

        if (is_admin()) {
            require_once ZFL_PLUGIN_DIR . 'includes/class-admin-settings.php';
        }
    }

    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));

        if (is_admin()) {
            new ZFL_Admin_Settings();
        }

        new ZFL_REST_API();
        new ZFL_Frontend();
        new ZFL_Login_Redirector();
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'zero-friction-login',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    public static function activate() {
        require_once ZFL_PLUGIN_DIR . 'includes/class-database.php';
        $database = new ZFL_Database();
        $database->create_tables();

        flush_rewrite_rules();

        update_option('zfl_version', ZFL_VERSION);
        update_option('zfl_activated_time', current_time('mysql'));
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public static function uninstall() {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'zfl_otps',
            $wpdb->prefix . 'zfl_rate_limits',
            $wpdb->prefix . 'zfl_audit_log',
            $wpdb->prefix . 'zfl_guest_sessions'
        );

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- Plugin uninstall removes plugin-owned custom tables.
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        $options = array(
            'zfl_version',
            'zfl_activated_time',
            'zfl_db_version',
            'zfl_login_method',
            'zfl_otp_expiry',
            'zfl_redirect_after_login',
            'zfl_custom_login_url',
            'zfl_redirect_after_logout',
            'zfl_custom_logout_url',
            'zfl_enable_smtp',
            'zfl_smtp_host',
            'zfl_smtp_port',
            'zfl_smtp_username',
            'zfl_smtp_password',
            'zfl_smtp_encryption',
            'zfl_smtp_from_email',
            'zfl_smtp_from_name',
            'zfl_email_subject_otp',
            'zfl_email_body_otp',
            'zfl_email_subject_magic_link',
            'zfl_email_body_magic_link',
            'zfl_logo_id',
            'zfl_logo_width',
            'zfl_card_background',
            'zfl_overlay_background',
            'zfl_primary_color',
            'zfl_button_background',
            'zfl_button_text_color',
            'zfl_heading_font',
            'zfl_body_font',
            'zfl_terms_page',
            'zfl_privacy_page',
            'zfl_show_policy_links',
            'zfl_hide_footer_credit',
            'zfl_test_mode',
            'zfl_force_custom_login',
            'zfl_custom_login_page',
            'zfl_custom_login_page_url',
            'zfl_force_login_checkout'
        );

        foreach ($options as $option) {
            delete_option($option);
        }
    }
}

register_activation_hook(__FILE__, array('Zero_Friction_Login', 'activate'));
register_deactivation_hook(__FILE__, array('Zero_Friction_Login', 'deactivate'));
register_uninstall_hook(__FILE__, array('Zero_Friction_Login', 'uninstall'));

function zero_friction_login_init() {
    Zero_Friction_Login::get_instance();
}
add_action('plugins_loaded', 'zero_friction_login_init');
