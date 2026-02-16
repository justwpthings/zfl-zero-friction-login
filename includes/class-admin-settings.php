<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Admin_Settings {

    private $options_group = 'zfl_settings';
    private $page_slug = 'zero-friction-login';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Zero Friction Login Settings', 'zero-friction-login'),
            __('Zero Friction Login', 'zero-friction-login'),
            'manage_options',
            $this->page_slug,
            array($this, 'render_settings_page'),
            'dashicons-shield-alt',
            56
        );
    }

    public function enqueue_admin_scripts($hook) {
        $allowed_hooks = array(
            'settings_page_' . $this->page_slug,
            'toplevel_page_' . $this->page_slug,
        );

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        wp_enqueue_style(
            'zfl-admin-settings',
            ZFL_PLUGIN_URL . 'assets/css/admin-settings.css',
            array(),
            ZFL_VERSION
        );

        wp_enqueue_script(
            'zfl-admin-settings',
            ZFL_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery', 'wp-color-picker'),
            ZFL_VERSION,
            true
        );

        wp_localize_script(
            'zfl-admin-settings',
            'zflAdminI18n',
            array(
                'choose_logo_title' => __('Choose Logo', 'zero-friction-login'),
                'use_image' => __('Use this image', 'zero-friction-login'),
                'change_logo' => __('Change Logo', 'zero-friction-login'),
                'upload_logo' => __('Upload Logo', 'zero-friction-login'),
                'remove_logo' => __('Remove Logo', 'zero-friction-login'),
            )
        );
    }

    public function register_settings() {
        register_setting($this->options_group, 'zfl_login_method', array(
            'type' => 'string',
            'default' => '6_digit_numeric',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_otp_expiry', array(
            'type' => 'integer',
            'default' => 5,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_redirect_after_login', array(
            'type' => 'string',
            'default' => 'same_page',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_redirect_login_page_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_custom_login_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting($this->options_group, 'zfl_redirect_after_logout', array(
            'type' => 'string',
            'default' => 'same_page',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_redirect_logout_page_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_custom_logout_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting($this->options_group, 'zfl_enable_smtp', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_smtp_host', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_smtp_port', array(
            'type' => 'integer',
            'default' => 587,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_smtp_username', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_smtp_password', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => array($this, 'sanitize_password')
        ));

        register_setting($this->options_group, 'zfl_smtp_encryption', array(
            'type' => 'string',
            'default' => 'tls',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_smtp_from_email', array(
            'type' => 'string',
            'default' => get_option('admin_email'),
            'sanitize_callback' => 'sanitize_email'
        ));

        register_setting($this->options_group, 'zfl_smtp_from_name', array(
            'type' => 'string',
            'default' => get_option('blogname'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_email_subject_otp', array(
            'type' => 'string',
            'default' => __('Your login code for {SITE_NAME}', 'zero-friction-login'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_email_body_otp', array(
            'type' => 'string',
            'default' => $this->get_default_email_body_otp(),
            'sanitize_callback' => 'wp_kses_post'
        ));

        register_setting($this->options_group, 'zfl_email_subject_magic_link', array(
            'type' => 'string',
            'default' => __('Your magic login link for {SITE_NAME}', 'zero-friction-login'),
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_email_body_magic_link', array(
            'type' => 'string',
            'default' => $this->get_default_email_body_magic_link(),
            'sanitize_callback' => 'wp_kses_post'
        ));

        register_setting($this->options_group, 'zfl_logo_id', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_logo_width', array(
            'type' => 'integer',
            'default' => 150,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_card_background', array(
            'type' => 'string',
            'default' => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_overlay_background', array(
            'type' => 'string',
            'default' => '#f0f0f0',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_primary_color', array(
            'type' => 'string',
            'default' => '#0073aa',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_button_background', array(
            'type' => 'string',
            'default' => '#0073aa',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_button_text_color', array(
            'type' => 'string',
            'default' => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_secondary_button_background', array(
            'type' => 'string',
            'default' => '#f3f4f6',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_secondary_button_text_color', array(
            'type' => 'string',
            'default' => '#374151',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_tab_background', array(
            'type' => 'string',
            'default' => '#ffffff',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_tab_text_color', array(
            'type' => 'string',
            'default' => '#6b7280',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_active_tab_background', array(
            'type' => 'string',
            'default' => '#f9fafb',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_active_tab_text_color', array(
            'type' => 'string',
            'default' => '#111827',
            'sanitize_callback' => 'sanitize_hex_color'
        ));

        register_setting($this->options_group, 'zfl_heading_font', array(
            'type' => 'string',
            'default' => 'system_default',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        register_setting($this->options_group, 'zfl_body_font', array(
            'type' => 'string',
            'default' => 'system_default',
            'sanitize_callback' => 'sanitize_text_field'
        ));

        $design_color_fields = array(
            'zfl_text_primary_color' => '#111827',
            'zfl_text_secondary_color' => '#6b7280',
            'zfl_text_muted_color' => '#4b5563',
            'zfl_text_label_color' => '#374151',
            'zfl_text_inverse_color' => '#ffffff',
            'zfl_input_background' => '#ffffff',
            'zfl_input_text_color' => '#111827',
            'zfl_input_placeholder_color' => '#9ca3af',
            'zfl_input_border_color' => '#d1d5db',
            'zfl_input_border_color_focus' => '#9ca3af',
            'zfl_input_focus_ring_color' => '#b2d5e5',
            'zfl_input_disabled_bg' => '#f3f4f6',
            'zfl_input_disabled_text' => '#9ca3af',
            'zfl_input_error_border' => '#ef4444',
            'zfl_otp_box_bg' => '#ffffff',
            'zfl_otp_box_text_color' => '#111827',
            'zfl_otp_box_border_color' => '#d1d5db',
            'zfl_otp_box_border_color_filled' => '#9ca3af',
            'zfl_otp_box_border_color_active' => '#0073aa',
            'zfl_otp_box_border_color_error' => '#ef4444',
            'zfl_otp_box_disabled_bg' => '#f3f4f6',
            'zfl_button_hover_background' => '#006799',
            'zfl_button_active_background' => '#006190',
            'zfl_button_disabled_background' => '#d1d5db',
            'zfl_button_disabled_text' => '#6b7280',
            'zfl_secondary_button_hover_background' => '#dadbdd',
            'zfl_secondary_button_active_background' => '#cecfd1',
            'zfl_destructive_button_background' => '#dc2626',
            'zfl_destructive_button_text' => '#ffffff',
            'zfl_destructive_button_hover_background' => '#dc2626',
            'zfl_destructive_button_active_background' => '#dc2626',
            'zfl_destructive_button_disabled_background' => '#d1d5db',
            'zfl_destructive_button_disabled_text' => '#6b7280',
            'zfl_link_color' => '#2563eb',
            'zfl_link_hover_color' => '#1d4ed8',
            'zfl_tab_border_color_active' => '#0073aa',
            'zfl_notice_error_bg' => '#fef2f2',
            'zfl_notice_error_border' => '#fecaca',
            'zfl_notice_error_text' => '#991b1b',
            'zfl_notice_success_bg' => '#f0fdf4',
            'zfl_notice_success_border' => '#bbf7d0',
            'zfl_notice_success_text' => '#166534',
            'zfl_notice_info_bg' => '#eff6ff',
            'zfl_notice_info_border' => '#bfdbfe',
            'zfl_notice_info_text' => '#1e40af',
            'zfl_toast_success_bg' => '#16a34a',
            'zfl_toast_error_bg' => '#dc2626',
            'zfl_toast_info_bg' => '#2563eb',
            'zfl_toast_text_color' => '#ffffff',
            'zfl_toast_close_color' => '#ffffff',
            'zfl_toast_close_hover_color' => '#e5e7eb',
            'zfl_magic_icon_bg' => '#dbeafe',
            'zfl_magic_icon_color' => '#2563eb',
            'zfl_success_icon_bg' => '#dcfce7',
            'zfl_success_icon_color' => '#16a34a',
            'zfl_logged_in_icon_bg' => '#dcfce7',
            'zfl_logged_in_icon_color' => '#16a34a',
            'zfl_modal_icon_bg' => '#fee2e2',
            'zfl_modal_icon_color' => '#dc2626',
            'zfl_modal_background' => '#ffffff',
            'zfl_modal_text_color' => '#111827',
            'zfl_modal_overlay_color' => '#000000',
            'zfl_spinner_color' => '#2563eb',
            'zfl_spinner_success_color' => '#0073aa',
            'zfl_footer_text_color' => '#6b7280'
        );

        foreach ($design_color_fields as $field => $default) {
            register_setting($this->options_group, $field, array(
                'type' => 'string',
                'default' => $default,
                'sanitize_callback' => 'sanitize_hex_color'
            ));
        }

        register_setting($this->options_group, 'zfl_toast_close_bg', array(
            'type' => 'string',
            'default' => 'transparent',
            'sanitize_callback' => array($this, 'sanitize_color_or_transparent')
        ));

        register_setting($this->options_group, 'zfl_toast_close_hover_bg', array(
            'type' => 'string',
            'default' => 'transparent',
            'sanitize_callback' => array($this, 'sanitize_color_or_transparent')
        ));

        register_setting($this->options_group, 'zfl_tab_border_color_inactive', array(
            'type' => 'string',
            'default' => 'transparent',
            'sanitize_callback' => array($this, 'sanitize_color_or_transparent')
        ));

        $design_text_fields = array(
            'zfl_font_size_base' => '16px',
            'zfl_font_size_sm' => '14px',
            'zfl_font_size_xs' => '12px',
            'zfl_heading_size_h1' => '24px',
            'zfl_heading_size_h2' => '20px',
            'zfl_line_height_base' => '1.5',
            'zfl_card_radius' => '8px',
            'zfl_card_shadow' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            'zfl_card_padding' => '20px',
            'zfl_logged_in_card_padding' => '32px',
            'zfl_modal_padding' => '24px',
            'zfl_form_gap' => '16px',
            'zfl_section_gap' => '16px',
            'zfl_input_border_width' => '1px',
            'zfl_input_radius' => '8px',
            'zfl_input_focus_ring_width' => '3px',
            'zfl_input_padding_x' => '12px',
            'zfl_input_padding_y' => '10px',
            'zfl_otp_box_border_width' => '2px',
            'zfl_otp_box_radius' => '8px',
            'zfl_otp_box_size_6_w' => '44px',
            'zfl_otp_box_size_6_h' => '48px',
            'zfl_otp_box_size_8_w' => '48px',
            'zfl_otp_box_size_8_h' => '56px',
            'zfl_otp_box_font_size' => '20px',
            'zfl_otp_box_gap' => '8px',
            'zfl_button_radius' => '8px',
            'zfl_button_padding_x' => '16px',
            'zfl_button_padding_y' => '10px',
            'zfl_tab_radius' => '8px',
            'zfl_tab_padding_x' => '16px',
            'zfl_tab_padding_y' => '10px',
            'zfl_tab_border_width' => '2px',
            'zfl_notice_radius' => '8px',
            'zfl_notice_padding' => '12px',
            'zfl_notice_border_width' => '1px',
            'zfl_toast_radius' => '8px',
            'zfl_toast_shadow' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
            'zfl_toast_padding_x' => '24px',
            'zfl_toast_padding_y' => '16px',
            'zfl_toast_max_width' => '448px',
            'zfl_icon_circle_size_sm' => '64px',
            'zfl_icon_circle_size_md' => '80px',
            'zfl_icon_size_sm' => '32px',
            'zfl_icon_size_md' => '40px',
            'zfl_modal_radius' => '8px',
            'zfl_modal_shadow' => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
            'zfl_spinner_size_lg' => '48px',
            'zfl_spinner_size_sm' => '32px',
            'zfl_spinner_border_width' => '2px',
            'zfl_footer_font_size' => '12px',
            'zfl_animation_slide_in_duration' => '300ms',
            'zfl_animation_scale_in_duration' => '200ms',
            'zfl_transition_duration' => '200ms'
        );

        foreach ($design_text_fields as $field => $default) {
            register_setting($this->options_group, $field, array(
                'type' => 'string',
                'default' => $default,
                'sanitize_callback' => 'sanitize_text_field'
            ));
        }

        register_setting($this->options_group, 'zfl_modal_overlay_opacity', array(
            'type' => 'string',
            'default' => '0.5',
            'sanitize_callback' => array($this, 'sanitize_opacity')
        ));

        register_setting($this->options_group, 'zfl_link_decoration', array(
            'type' => 'string',
            'default' => 'underline',
            'sanitize_callback' => array($this, 'sanitize_decoration')
        ));

        register_setting($this->options_group, 'zfl_link_hover_decoration', array(
            'type' => 'string',
            'default' => 'underline',
            'sanitize_callback' => array($this, 'sanitize_decoration')
        ));

        register_setting($this->options_group, 'zfl_animation_enabled', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_terms_page', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_privacy_page', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_show_policy_links', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_hide_footer_credit', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_allow_registration', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_test_mode', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_force_custom_login', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));

        register_setting($this->options_group, 'zfl_custom_login_page', array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => 'absint'
        ));

        register_setting($this->options_group, 'zfl_custom_login_page_url', array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw'
        ));

        register_setting($this->options_group, 'zfl_force_login_checkout', array(
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => array($this, 'sanitize_checkbox')
        ));
    }

    public function save_settings() {
        $nonce = isset($_POST['zfl_settings_nonce']) ? sanitize_text_field(wp_unslash($_POST['zfl_settings_nonce'])) : '';
        if ($nonce === '' || !wp_verify_nonce($nonce, 'zfl_save_settings')) {
            wp_die(esc_html__('Security check failed.', 'zero-friction-login'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized access.', 'zero-friction-login'));
        }

        $active_tab = isset($_POST['zfl_active_tab']) ? sanitize_key(wp_unslash($_POST['zfl_active_tab'])) : 'general';

        if ('design' === $active_tab) {
            if (isset($_POST['zfl_reset_design_styles'])) {
                $this->reset_design_styles();
                $this->redirect_to_settings_page($active_tab, array('zfl_design_reset' => '1'));
            }

            if (isset($_POST['zfl_import_design_styles'])) {
                $import_json = isset($_POST['zfl_design_import_json']) ? wp_unslash((string) $_POST['zfl_design_import_json']) : '';
                $import_status = $this->import_design_styles($import_json);
                $this->redirect_to_settings_page($active_tab, array('zfl_design_import' => $import_status));
            }
        }

        $fields_by_tab = array(
            'general' => array(
                'zfl_login_method' => 'sanitize_text_field',
                'zfl_otp_expiry' => 'absint',
                'zfl_redirect_after_login' => 'sanitize_text_field',
                'zfl_redirect_login_page_id' => 'absint',
                'zfl_custom_login_url' => 'esc_url_raw',
                'zfl_redirect_after_logout' => 'sanitize_text_field',
                'zfl_redirect_logout_page_id' => 'absint',
                'zfl_custom_logout_url' => 'esc_url_raw',
                'zfl_terms_page' => 'absint',
                'zfl_privacy_page' => 'absint',
                'zfl_show_policy_links' => array($this, 'sanitize_checkbox'),
                'zfl_hide_footer_credit' => array($this, 'sanitize_checkbox'),
                'zfl_allow_registration' => array($this, 'sanitize_checkbox'),
                'zfl_test_mode' => array($this, 'sanitize_checkbox'),
                'zfl_force_custom_login' => array($this, 'sanitize_checkbox'),
                'zfl_custom_login_page' => 'absint',
                'zfl_custom_login_page_url' => 'esc_url_raw',
                'zfl_force_login_checkout' => array($this, 'sanitize_checkbox')
            ),
            'smtp' => array(
                'zfl_enable_smtp' => array($this, 'sanitize_checkbox'),
                'zfl_smtp_host' => 'sanitize_text_field',
                'zfl_smtp_port' => 'absint',
                'zfl_smtp_username' => 'sanitize_text_field',
                'zfl_smtp_password' => array($this, 'sanitize_password'),
                'zfl_smtp_encryption' => 'sanitize_text_field',
                'zfl_smtp_from_email' => 'sanitize_email',
                'zfl_smtp_from_name' => 'sanitize_text_field'
            ),
            'email' => array(
                'zfl_email_subject_otp' => 'sanitize_text_field',
                'zfl_email_body_otp' => 'wp_kses_post',
                'zfl_email_subject_magic_link' => 'sanitize_text_field',
                'zfl_email_body_magic_link' => 'wp_kses_post'
            ),
            'design' => $this->get_design_sanitize_callbacks()
        );

        $fields = isset($fields_by_tab[$active_tab]) ? $fields_by_tab[$active_tab] : array();

        foreach ($fields as $field => $sanitize_callback) {
            $is_checkbox = is_array($sanitize_callback) && $sanitize_callback[1] === 'sanitize_checkbox';

            if ($is_checkbox) {
                $value = isset($_POST[$field]) ? wp_unslash($_POST[$field]) : 0;
                $value = call_user_func($sanitize_callback, $value);
                update_option($field, $value);
            } elseif (isset($_POST[$field])) {
                $value = wp_unslash($_POST[$field]);

                if (is_callable($sanitize_callback)) {
                    $value = call_user_func($sanitize_callback, $value);
                }

                update_option($field, $value);
            }
        }

        $this->redirect_to_settings_page($active_tab, array('settings-updated' => 'true'));
    }

    private function redirect_to_settings_page($active_tab, $extra_args = array()) {
        $args = array_merge(
            array(
                'page' => $this->page_slug,
                'tab' => $active_tab,
            ),
            $extra_args
        );

        $redirect_url = add_query_arg($args, admin_url('admin.php'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    private function get_design_sanitize_callbacks() {
        return array(
            'zfl_logo_id' => 'absint',
            'zfl_logo_width' => 'absint',
            'zfl_card_background' => 'sanitize_hex_color',
            'zfl_overlay_background' => 'sanitize_hex_color',
            'zfl_primary_color' => 'sanitize_hex_color',
            'zfl_button_background' => 'sanitize_hex_color',
            'zfl_button_text_color' => 'sanitize_hex_color',
            'zfl_secondary_button_background' => 'sanitize_hex_color',
            'zfl_secondary_button_text_color' => 'sanitize_hex_color',
            'zfl_tab_background' => 'sanitize_hex_color',
            'zfl_tab_text_color' => 'sanitize_hex_color',
            'zfl_active_tab_background' => 'sanitize_hex_color',
            'zfl_active_tab_text_color' => 'sanitize_hex_color',
            'zfl_heading_font' => 'sanitize_text_field',
            'zfl_body_font' => 'sanitize_text_field',
            'zfl_text_primary_color' => 'sanitize_hex_color',
            'zfl_text_secondary_color' => 'sanitize_hex_color',
            'zfl_text_muted_color' => 'sanitize_hex_color',
            'zfl_text_label_color' => 'sanitize_hex_color',
            'zfl_text_inverse_color' => 'sanitize_hex_color',
            'zfl_font_size_base' => 'sanitize_text_field',
            'zfl_font_size_sm' => 'sanitize_text_field',
            'zfl_font_size_xs' => 'sanitize_text_field',
            'zfl_heading_size_h1' => 'sanitize_text_field',
            'zfl_heading_size_h2' => 'sanitize_text_field',
            'zfl_line_height_base' => 'sanitize_text_field',
            'zfl_card_radius' => 'sanitize_text_field',
            'zfl_card_shadow' => 'sanitize_text_field',
            'zfl_card_padding' => 'sanitize_text_field',
            'zfl_logged_in_card_padding' => 'sanitize_text_field',
            'zfl_modal_padding' => 'sanitize_text_field',
            'zfl_form_gap' => 'sanitize_text_field',
            'zfl_section_gap' => 'sanitize_text_field',
            'zfl_input_background' => 'sanitize_hex_color',
            'zfl_input_text_color' => 'sanitize_hex_color',
            'zfl_input_placeholder_color' => 'sanitize_hex_color',
            'zfl_input_border_color' => 'sanitize_hex_color',
            'zfl_input_border_color_focus' => 'sanitize_hex_color',
            'zfl_input_border_width' => 'sanitize_text_field',
            'zfl_input_radius' => 'sanitize_text_field',
            'zfl_input_focus_ring_color' => 'sanitize_hex_color',
            'zfl_input_focus_ring_width' => 'sanitize_text_field',
            'zfl_input_disabled_bg' => 'sanitize_hex_color',
            'zfl_input_disabled_text' => 'sanitize_hex_color',
            'zfl_input_error_border' => 'sanitize_hex_color',
            'zfl_input_padding_x' => 'sanitize_text_field',
            'zfl_input_padding_y' => 'sanitize_text_field',
            'zfl_otp_box_bg' => 'sanitize_hex_color',
            'zfl_otp_box_text_color' => 'sanitize_hex_color',
            'zfl_otp_box_border_color' => 'sanitize_hex_color',
            'zfl_otp_box_border_color_filled' => 'sanitize_hex_color',
            'zfl_otp_box_border_color_active' => 'sanitize_hex_color',
            'zfl_otp_box_border_color_error' => 'sanitize_hex_color',
            'zfl_otp_box_border_width' => 'sanitize_text_field',
            'zfl_otp_box_radius' => 'sanitize_text_field',
            'zfl_otp_box_size_6_w' => 'sanitize_text_field',
            'zfl_otp_box_size_6_h' => 'sanitize_text_field',
            'zfl_otp_box_size_8_w' => 'sanitize_text_field',
            'zfl_otp_box_size_8_h' => 'sanitize_text_field',
            'zfl_otp_box_font_size' => 'sanitize_text_field',
            'zfl_otp_box_gap' => 'sanitize_text_field',
            'zfl_otp_box_disabled_bg' => 'sanitize_hex_color',
            'zfl_button_radius' => 'sanitize_text_field',
            'zfl_button_padding_x' => 'sanitize_text_field',
            'zfl_button_padding_y' => 'sanitize_text_field',
            'zfl_button_hover_background' => 'sanitize_hex_color',
            'zfl_button_active_background' => 'sanitize_hex_color',
            'zfl_button_disabled_background' => 'sanitize_hex_color',
            'zfl_button_disabled_text' => 'sanitize_hex_color',
            'zfl_secondary_button_hover_background' => 'sanitize_hex_color',
            'zfl_secondary_button_active_background' => 'sanitize_hex_color',
            'zfl_destructive_button_background' => 'sanitize_hex_color',
            'zfl_destructive_button_text' => 'sanitize_hex_color',
            'zfl_destructive_button_hover_background' => 'sanitize_hex_color',
            'zfl_destructive_button_active_background' => 'sanitize_hex_color',
            'zfl_destructive_button_disabled_background' => 'sanitize_hex_color',
            'zfl_destructive_button_disabled_text' => 'sanitize_hex_color',
            'zfl_link_color' => 'sanitize_hex_color',
            'zfl_link_hover_color' => 'sanitize_hex_color',
            'zfl_link_decoration' => array($this, 'sanitize_decoration'),
            'zfl_link_hover_decoration' => array($this, 'sanitize_decoration'),
            'zfl_tab_radius' => 'sanitize_text_field',
            'zfl_tab_padding_x' => 'sanitize_text_field',
            'zfl_tab_padding_y' => 'sanitize_text_field',
            'zfl_tab_border_width' => 'sanitize_text_field',
            'zfl_tab_border_color_inactive' => array($this, 'sanitize_color_or_transparent'),
            'zfl_tab_border_color_active' => 'sanitize_hex_color',
            'zfl_notice_radius' => 'sanitize_text_field',
            'zfl_notice_padding' => 'sanitize_text_field',
            'zfl_notice_border_width' => 'sanitize_text_field',
            'zfl_notice_error_bg' => 'sanitize_hex_color',
            'zfl_notice_error_border' => 'sanitize_hex_color',
            'zfl_notice_error_text' => 'sanitize_hex_color',
            'zfl_notice_success_bg' => 'sanitize_hex_color',
            'zfl_notice_success_border' => 'sanitize_hex_color',
            'zfl_notice_success_text' => 'sanitize_hex_color',
            'zfl_notice_info_bg' => 'sanitize_hex_color',
            'zfl_notice_info_border' => 'sanitize_hex_color',
            'zfl_notice_info_text' => 'sanitize_hex_color',
            'zfl_toast_success_bg' => 'sanitize_hex_color',
            'zfl_toast_error_bg' => 'sanitize_hex_color',
            'zfl_toast_info_bg' => 'sanitize_hex_color',
            'zfl_toast_text_color' => 'sanitize_hex_color',
            'zfl_toast_radius' => 'sanitize_text_field',
            'zfl_toast_shadow' => 'sanitize_text_field',
            'zfl_toast_padding_x' => 'sanitize_text_field',
            'zfl_toast_padding_y' => 'sanitize_text_field',
            'zfl_toast_max_width' => 'sanitize_text_field',
            'zfl_toast_close_color' => 'sanitize_hex_color',
            'zfl_toast_close_hover_color' => 'sanitize_hex_color',
            'zfl_toast_close_bg' => array($this, 'sanitize_color_or_transparent'),
            'zfl_toast_close_hover_bg' => array($this, 'sanitize_color_or_transparent'),
            'zfl_magic_icon_bg' => 'sanitize_hex_color',
            'zfl_magic_icon_color' => 'sanitize_hex_color',
            'zfl_success_icon_bg' => 'sanitize_hex_color',
            'zfl_success_icon_color' => 'sanitize_hex_color',
            'zfl_logged_in_icon_bg' => 'sanitize_hex_color',
            'zfl_logged_in_icon_color' => 'sanitize_hex_color',
            'zfl_modal_icon_bg' => 'sanitize_hex_color',
            'zfl_modal_icon_color' => 'sanitize_hex_color',
            'zfl_icon_circle_size_sm' => 'sanitize_text_field',
            'zfl_icon_circle_size_md' => 'sanitize_text_field',
            'zfl_icon_size_sm' => 'sanitize_text_field',
            'zfl_icon_size_md' => 'sanitize_text_field',
            'zfl_modal_background' => 'sanitize_hex_color',
            'zfl_modal_text_color' => 'sanitize_hex_color',
            'zfl_modal_radius' => 'sanitize_text_field',
            'zfl_modal_shadow' => 'sanitize_text_field',
            'zfl_modal_overlay_color' => 'sanitize_hex_color',
            'zfl_modal_overlay_opacity' => array($this, 'sanitize_opacity'),
            'zfl_spinner_color' => 'sanitize_hex_color',
            'zfl_spinner_success_color' => 'sanitize_hex_color',
            'zfl_spinner_size_lg' => 'sanitize_text_field',
            'zfl_spinner_size_sm' => 'sanitize_text_field',
            'zfl_spinner_border_width' => 'sanitize_text_field',
            'zfl_footer_text_color' => 'sanitize_hex_color',
            'zfl_footer_font_size' => 'sanitize_text_field',
            'zfl_animation_enabled' => array($this, 'sanitize_checkbox'),
            'zfl_animation_slide_in_duration' => 'sanitize_text_field',
            'zfl_animation_scale_in_duration' => 'sanitize_text_field',
            'zfl_transition_duration' => 'sanitize_text_field'
        );
    }

    private function get_design_transfer_sanitize_callbacks() {
        $callbacks = $this->get_design_sanitize_callbacks();
        unset($callbacks['zfl_logo_id']);
        return $callbacks;
    }

    private function import_design_styles($json_payload) {
        $json_payload = trim((string) $json_payload);
        if ($json_payload === '') {
            return 'empty';
        }

        $decoded = json_decode($json_payload, true);
        if (!is_array($decoded)) {
            return 'invalid';
        }

        $styles = (isset($decoded['styles']) && is_array($decoded['styles'])) ? $decoded['styles'] : $decoded;
        if (empty($styles)) {
            return 'empty';
        }

        $callbacks = $this->get_design_transfer_sanitize_callbacks();
        $updated = 0;

        foreach ($callbacks as $field => $sanitize_callback) {
            if (!array_key_exists($field, $styles)) {
                continue;
            }

            $raw_value = $styles[$field];
            if (!is_scalar($raw_value) && $raw_value !== null) {
                continue;
            }

            $value = $raw_value;
            if (is_callable($sanitize_callback)) {
                $value = call_user_func($sanitize_callback, $raw_value);
            }

            if ($value === null || $value === false) {
                continue;
            }

            update_option($field, $value);
            $updated++;
        }

        return $updated > 0 ? 'success' : 'no_valid_fields';
    }

    private function reset_design_styles() {
        $fields = array_keys($this->get_design_transfer_sanitize_callbacks());

        foreach ($fields as $field) {
            delete_option($field);
        }
    }

    private function get_design_styles_export_json() {
        $styles = array();
        $fields = array_keys($this->get_design_transfer_sanitize_callbacks());

        foreach ($fields as $field) {
            $value = get_option($field, null);
            if ($value !== null && $value !== false) {
                $styles[$field] = $value;
            }
        }

        $payload = array(
            'schema' => 'zero-friction-login-design-v1',
            'exported_at' => gmdate('c'),
            'styles' => $styles,
        );

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return $json ? $json : '{}';
    }

    public function sanitize_checkbox($value) {
        return $value ? 1 : 0;
    }

    public function sanitize_password($value) {
        return $value;
    }

    public function sanitize_opacity($value) {
        $value = is_numeric($value) ? floatval($value) : 0.5;
        $value = max(0, min(1, $value));
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function sanitize_decoration($value) {
        $value = sanitize_text_field($value);
        $allowed = array('none', 'underline');
        return in_array($value, $allowed, true) ? $value : 'underline';
    }

    public function sanitize_color_or_transparent($value) {
        $value = sanitize_text_field($value);
        if ($value === 'transparent') {
            return $value;
        }
        return sanitize_hex_color($value);
    }

    private function t($text) {
        return (string) $text;
    }

    private function get_default_email_body_otp() {
        return __("Hello,\n\nYour login code is: {OTP}\n\nThis code will expire in 5 minutes.\n\nRequest details:\n- Time: {TIME}\n- IP Address: {IP}\n- Browser: {BROWSER}\n\nIf you didn't request this code, please ignore this email.\n\nThank you,\n{SITE_NAME}", 'zero-friction-login');
    }

    private function get_default_email_body_magic_link() {
        return __("Hello,\n\nClick the link below to log in instantly:\n\n{MAGIC_LINK}\n\nThis link will expire in 5 minutes.\n\nRequest details:\n- Time: {TIME}\n- IP Address: {IP}\n- Browser: {BROWSER}\n\nIf you didn't request this link, please ignore this email.\n\nThank you,\n{SITE_NAME}", 'zero-friction-login');
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_POST['zfl_settings_submit'])) {
            $this->save_settings();
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        $tabs = array(
            'general' => __('General Settings', 'zero-friction-login'),
            'smtp' => __('SMTP Settings', 'zero-friction-login'),
            'email' => __('Email Template', 'zero-friction-login'),
            'design' => __('Design & Branding', 'zero-friction-login'),
            'about' => __('About & Documentation', 'zero-friction-login'),
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__('Settings saved successfully.', 'zero-friction-login'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['zfl_design_reset']) && sanitize_key(wp_unslash((string) $_GET['zfl_design_reset'])) === '1'): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html__('Design styles have been reset to defaults.', 'zero-friction-login'); ?></p>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['zfl_design_import'])): ?>
                <?php
                $import_status = sanitize_key(wp_unslash((string) $_GET['zfl_design_import']));
                $import_message = '';
                $import_notice_class = 'notice-success';

                switch ($import_status) {
                    case 'success':
                        $import_message = __('Design styles imported successfully.', 'zero-friction-login');
                        break;
                    case 'empty':
                        $import_message = __('No design styles were found in the import payload.', 'zero-friction-login');
                        $import_notice_class = 'notice-warning';
                        break;
                    case 'no_valid_fields':
                        $import_message = __('No valid design style fields were found to import.', 'zero-friction-login');
                        $import_notice_class = 'notice-warning';
                        break;
                    default:
                        $import_message = __('Invalid design JSON. Please check and try again.', 'zero-friction-login');
                        $import_notice_class = 'notice-error';
                        break;
                }
                ?>
                <div class="notice <?php echo esc_attr($import_notice_class); ?> is-dismissible">
                    <p><?php echo esc_html($import_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ((bool) get_option('zfl_test_mode', false)): ?>
                <div class="notice notice-warning">
                    <p>
                        <strong><?php echo esc_html__('Warning:', 'zero-friction-login'); ?></strong>
                        <?php echo esc_html__('Test Mode is enabled. Disable this on live/production sites.', 'zero-friction-login'); ?>
                    </p>
                </div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_label): ?>
                    <?php
                    $tab_url = add_query_arg(
                        array(
                            'page' => $this->page_slug,
                            'tab' => $tab_key,
                        ),
                        admin_url('admin.php')
                    );
                    $tab_class = 'nav-tab' . ($active_tab === $tab_key ? ' nav-tab-active' : '');
                    ?>
                    <a href="<?php echo esc_url($tab_url); ?>" class="<?php echo esc_attr($tab_class); ?>">
                        <?php echo esc_html($tab_label); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <?php if ($active_tab === 'about'): ?>
                <?php $this->render_about_tab(); ?>
            <?php else: ?>
                <form method="post" action="">
                    <?php wp_nonce_field('zfl_save_settings', 'zfl_settings_nonce'); ?>
                    <input type="hidden" name="zfl_settings_submit" value="1">
                    <input type="hidden" name="zfl_active_tab" value="<?php echo esc_attr($active_tab); ?>">

                    <?php
                    switch ($active_tab) {
                        case 'smtp':
                            $this->render_smtp_tab();
                            break;
                        case 'email':
                            $this->render_email_tab();
                            break;
                        case 'design':
                            $this->render_design_tab();
                            break;
                        case 'general':
                        default:
                            $this->render_general_tab();
                            break;
                    }

                    submit_button();
                    ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_general_tab() {
        $login_method = get_option('zfl_login_method', '6_digit_numeric');
        $otp_expiry = get_option('zfl_otp_expiry', 5);
        $redirect_after_login = get_option('zfl_redirect_after_login', 'same_page');
        $custom_login_url = get_option('zfl_custom_login_url', '');
        $redirect_after_logout = get_option('zfl_redirect_after_logout', 'same_page');
        $custom_logout_url = get_option('zfl_custom_logout_url', '');
        $redirect_login_page_id = get_option('zfl_redirect_login_page_id', 0);
        $redirect_logout_page_id = get_option('zfl_redirect_logout_page_id', 0);
        $pages = get_pages(array('sort_column' => 'post_title', 'sort_order' => 'asc'));

        $redirect_after_login_value = $redirect_after_login;
        if ($redirect_after_login === 'page' && $redirect_login_page_id) {
            $redirect_after_login_value = 'page:' . $redirect_login_page_id;
        }

        $redirect_after_logout_value = $redirect_after_logout;
        if ($redirect_after_logout === 'page' && $redirect_logout_page_id) {
            $redirect_after_logout_value = 'page:' . $redirect_logout_page_id;
        }
        $terms_page = get_option('zfl_terms_page', 0);
        $privacy_page = get_option('zfl_privacy_page', 0);
        $show_policy_links = get_option('zfl_show_policy_links', true);
        $test_mode = get_option('zfl_test_mode', false);
        $force_custom_login = get_option('zfl_force_custom_login', false);
        $custom_login_page = get_option('zfl_custom_login_page', 0);
        $custom_login_page_url = get_option('zfl_custom_login_page_url', '');
        $force_login_checkout = get_option('zfl_force_login_checkout', false);
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Login Method', 'zero-friction-login'); ?></th>
                <td>
                    <fieldset>
                        <legend class="screen-reader-text"><span><?php echo esc_html__('Login Method', 'zero-friction-login'); ?></span></legend>
                        <label>
                            <input type="radio" name="zfl_login_method" value="6_digit_numeric"
                                   <?php checked($login_method, '6_digit_numeric'); ?>>
                            <?php echo esc_html__('6-digit numeric OTP (e.g., 123456)', 'zero-friction-login'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="zfl_login_method" value="6_char_alphanumeric"
                                   <?php checked($login_method, '6_char_alphanumeric'); ?>>
                            <?php echo esc_html__('6-character alphanumeric OTP (e.g., A3B7K9)', 'zero-friction-login'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="zfl_login_method" value="8_digit_numeric"
                                   <?php checked($login_method, '8_digit_numeric'); ?>>
                            <?php echo esc_html__('8-digit numeric OTP (e.g., 12345678)', 'zero-friction-login'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="zfl_login_method" value="8_char_alphanumeric"
                                   <?php checked($login_method, '8_char_alphanumeric'); ?>>
                            <?php echo esc_html__('8-character alphanumeric OTP (e.g., A3B7K9M2)', 'zero-friction-login'); ?>
                        </label><br>
                        <label>
                            <input type="radio" name="zfl_login_method" value="magic_link"
                                   <?php checked($login_method, 'magic_link'); ?>>
                            <?php echo esc_html__('Magic Link only (no OTP code)', 'zero-friction-login'); ?>
                        </label>
                        <p class="description"><?php echo esc_html__('This setting controls which login method users will see. Users cannot choose their own method.', 'zero-friction-login'); ?></p>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_otp_expiry"><?php echo esc_html__('OTP Expiry (minutes)', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="number" id="zfl_otp_expiry" name="zfl_otp_expiry"
                           value="<?php echo esc_attr($otp_expiry); ?>"
                           min="1" max="60" class="small-text">
                    <p class="description"><?php echo esc_html__('How long the OTP code remains valid (1-60 minutes).', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_redirect_after_login"><?php echo esc_html__('Redirect After Login', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <select id="zfl_redirect_after_login" name="zfl_redirect_after_login">
                        <option value="same_page" <?php selected($redirect_after_login_value, 'same_page'); ?>>
                            <?php echo esc_html__('Same Page', 'zero-friction-login'); ?>
                        </option>
                        <option value="custom_url" <?php selected($redirect_after_login_value, 'custom_url'); ?>>
                            <?php echo esc_html__('Custom URL', 'zero-friction-login'); ?>
                        </option>
                        <optgroup label="<?php echo esc_attr__('Pages', 'zero-friction-login'); ?>">
                            <?php foreach ($pages as $page): ?>
                                <?php $page_value = 'page:' . $page->ID; ?>
                                <option value="<?php echo esc_attr($page_value); ?>" <?php selected($redirect_after_login_value, $page_value); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </td>
            </tr>
            <tr id="custom_login_url_row" style="<?php echo $redirect_after_login_value !== 'custom_url' ? 'display:none;' : ''; ?>">
                <th scope="row">
                    <label for="zfl_custom_login_url"><?php echo esc_html__('Custom Login URL', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="url" id="zfl_custom_login_url" name="zfl_custom_login_url"
                           value="<?php echo esc_attr($custom_login_url); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Enter the full URL where users should be redirected after login.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_redirect_after_logout"><?php echo esc_html__('Redirect After Logout', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <select id="zfl_redirect_after_logout" name="zfl_redirect_after_logout">
                        <option value="same_page" <?php selected($redirect_after_logout_value, 'same_page'); ?>>
                            <?php echo esc_html__('Same Page', 'zero-friction-login'); ?>
                        </option>
                        <option value="custom_url" <?php selected($redirect_after_logout_value, 'custom_url'); ?>>
                            <?php echo esc_html__('Custom URL', 'zero-friction-login'); ?>
                        </option>
                        <optgroup label="<?php echo esc_attr__('Pages', 'zero-friction-login'); ?>">
                            <?php foreach ($pages as $page): ?>
                                <?php $page_value = 'page:' . $page->ID; ?>
                                <option value="<?php echo esc_attr($page_value); ?>" <?php selected($redirect_after_logout_value, $page_value); ?>>
                                    <?php echo esc_html($page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </td>
            </tr>
            <tr id="custom_logout_url_row" style="<?php echo $redirect_after_logout_value !== 'custom_url' ? 'display:none;' : ''; ?>">
                <th scope="row">
                    <label for="zfl_custom_logout_url"><?php echo esc_html__('Custom Logout URL', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="url" id="zfl_custom_logout_url" name="zfl_custom_logout_url"
                           value="<?php echo esc_attr($custom_logout_url); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Enter the full URL where users should be redirected after logout.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Force Custom Login Page', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_force_custom_login" name="zfl_force_custom_login" value="1"
                               <?php checked($force_custom_login, 1); ?>>
                        <?php echo esc_html__('Redirect wp-login.php, wp-admin, and WooCommerce My Account to custom login page', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('When enabled, users trying to access wp-login.php or wp-admin (when logged out) will be redirected to your custom login page.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr id="custom_login_page_row" style="<?php echo !$force_custom_login ? 'display:none;' : ''; ?>">
                <th scope="row">
                    <label for="zfl_custom_login_page"><?php echo esc_html__('Select Login Page', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <?php $this->render_page_dropdown('zfl_custom_login_page', $custom_login_page); ?>
                    <p class="description"><?php echo esc_html__('Select the page that contains your custom login form ([zero_friction_login] shortcode).', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr id="custom_login_page_url_row" style="<?php echo !$force_custom_login ? 'display:none;' : ''; ?>">
                <th scope="row">
                    <label for="zfl_custom_login_page_url"><?php echo esc_html__('Or Enter Custom URL', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="url" id="zfl_custom_login_page_url" name="zfl_custom_login_page_url"
                           value="<?php echo esc_attr($custom_login_page_url); ?>" class="regular-text">
                    <p class="description"><?php echo esc_html__('Alternatively, enter a custom URL to redirect to. This takes priority over the page selection above.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Force Login Before Checkout', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_force_login_checkout" name="zfl_force_login_checkout" value="1"
                               <?php checked($force_login_checkout, 1); ?>>
                        <?php echo esc_html__('Require users to login before accessing checkout', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('When enabled, users must be logged in to access the WooCommerce checkout page. They will be redirected to your custom login page.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_terms_page"><?php echo esc_html__('Terms of Service Page', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <?php $this->render_page_dropdown('zfl_terms_page', $terms_page); ?>
                    <p class="description"><?php echo esc_html__('Select the page that contains your Terms of Service.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_privacy_page"><?php echo esc_html__('Privacy Policy Page', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <?php $this->render_page_dropdown('zfl_privacy_page', $privacy_page); ?>
                    <p class="description"><?php echo esc_html__('Select the page that contains your Privacy Policy.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Show Policy Links', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_show_policy_links" name="zfl_show_policy_links" value="1"
                               <?php checked($show_policy_links, 1); ?>>
                        <?php echo esc_html__('Display Terms and Privacy links on the login form', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('When enabled, links to Terms and Privacy pages will appear below the login form.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Hide Footer Credit', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_hide_footer_credit" name="zfl_hide_footer_credit" value="1"
                               <?php checked(get_option('zfl_hide_footer_credit', false), 1); ?>>
                        <?php echo esc_html__('Hide "Secured by Zero Friction Login" footer credit', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: 1: Donation URL, 2: Creator website URL. */
                                __('When enabled, the footer credit will be hidden. If you find this plugin useful, please consider <a href="%1$s" target="_blank">making a donation</a> to support further development and help make the plugin more sustainable. Created by <a href="%2$s" target="_blank">JustWPThings</a>.', 'zero-friction-login'),
                                esc_url('https://justwpthings.com/donate/'),
                                esc_url('https://justwpthings.com')
                            )
                        );
                    ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Allow Registration', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_allow_registration" name="zfl_allow_registration" value="1"
                               <?php checked(get_option('zfl_allow_registration', true), 1); ?>>
                        <?php echo esc_html__('Allow new user registration', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('When disabled, only the login form will be shown and new account creation will be prevented. Users must have an existing account to log in.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Test Mode (Bypass Rate Limits)', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_test_mode" name="zfl_test_mode" value="1"
                               <?php checked($test_mode, 1); ?>>
                        <?php echo esc_html__('Allow unlimited OTP/magic-link requests while testing', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><strong><?php echo esc_html__('Warning:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Disable Test Mode on live/production sites.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_smtp_tab() {
        $enable_smtp = get_option('zfl_enable_smtp', false);
        $smtp_host = get_option('zfl_smtp_host', '');
        $smtp_port = get_option('zfl_smtp_port', 587);
        $smtp_username = get_option('zfl_smtp_username', '');
        $smtp_password = get_option('zfl_smtp_password', '');
        $smtp_encryption = get_option('zfl_smtp_encryption', 'tls');
        $smtp_from_email = get_option('zfl_smtp_from_email', get_option('admin_email'));
        $smtp_from_name = get_option('zfl_smtp_from_name', get_option('blogname'));
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php echo esc_html__('Enable Plugin SMTP', 'zero-friction-login'); ?></th>
                <td>
                    <label>
                        <input type="checkbox" id="zfl_enable_smtp" name="zfl_enable_smtp" value="1"
                               <?php checked($enable_smtp, 1); ?>>
                        <?php echo esc_html__('Use custom SMTP settings for this plugin', 'zero-friction-login'); ?>
                    </label>
                    <p class="description"><?php echo esc_html__('By default, the plugin uses WordPress default email settings. Enable this to use custom SMTP settings.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
        </table>

        <div id="smtp_settings" style="<?php echo !$enable_smtp ? 'display:none;' : ''; ?>">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_host"><?php echo esc_html__('SMTP Host', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="zfl_smtp_host" name="zfl_smtp_host"
                               value="<?php echo esc_attr($smtp_host); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('e.g., smtp.gmail.com or smtp.sendgrid.net', 'zero-friction-login'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_port"><?php echo esc_html__('SMTP Port', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="zfl_smtp_port" name="zfl_smtp_port"
                               value="<?php echo esc_attr($smtp_port); ?>" class="small-text">
                        <p class="description"><?php echo esc_html__('Common ports: 587 (TLS), 465 (SSL), 25 (no encryption)', 'zero-friction-login'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_encryption"><?php echo esc_html__('SMTP Encryption', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <select id="zfl_smtp_encryption" name="zfl_smtp_encryption">
                            <option value="tls" <?php selected($smtp_encryption, 'tls'); ?>><?php echo esc_html__('TLS', 'zero-friction-login'); ?></option>
                            <option value="ssl" <?php selected($smtp_encryption, 'ssl'); ?>><?php echo esc_html__('SSL', 'zero-friction-login'); ?></option>
                            <option value="none" <?php selected($smtp_encryption, 'none'); ?>><?php echo esc_html__('None', 'zero-friction-login'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_username"><?php echo esc_html__('SMTP Username', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="zfl_smtp_username" name="zfl_smtp_username"
                               value="<?php echo esc_attr($smtp_username); ?>" class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_password"><?php echo esc_html__('SMTP Password', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="zfl_smtp_password" name="zfl_smtp_password"
                               value="<?php echo esc_attr($smtp_password); ?>" class="regular-text" autocomplete="off">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_from_email"><?php echo esc_html__('From Email', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="email" id="zfl_smtp_from_email" name="zfl_smtp_from_email"
                               value="<?php echo esc_attr($smtp_from_email); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('Email address that appears in the "From" field.', 'zero-friction-login'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="zfl_smtp_from_name"><?php echo esc_html__('From Name', 'zero-friction-login'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="zfl_smtp_from_name" name="zfl_smtp_from_name"
                               value="<?php echo esc_attr($smtp_from_name); ?>" class="regular-text">
                        <p class="description"><?php echo esc_html__('Name that appears in the "From" field.', 'zero-friction-login'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    private function render_email_tab() {
        $email_subject_otp = get_option('zfl_email_subject_otp', __('Your login code for {SITE_NAME}', 'zero-friction-login'));
        $email_body_otp = get_option('zfl_email_body_otp', $this->get_default_email_body_otp());
        $email_subject_magic = get_option('zfl_email_subject_magic_link', __('Your magic login link for {SITE_NAME}', 'zero-friction-login'));
        $email_body_magic = get_option('zfl_email_body_magic_link', $this->get_default_email_body_magic_link());
        ?>
        <h3 style="margin-top: 20px;"><?php echo esc_html__('OTP Email Template', 'zero-friction-login'); ?></h3>
        <p><?php echo esc_html__('This template is used when sending one-time password codes.', 'zero-friction-login'); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="zfl_email_subject_otp"><?php echo esc_html__('Email Subject (OTP)', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="text" id="zfl_email_subject_otp" name="zfl_email_subject_otp"
                           value="<?php echo esc_attr($email_subject_otp); ?>" class="large-text">
                    <p class="description"><?php echo esc_html__('Available placeholders: {SITE_NAME}, {OTP}, {TIME}', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_email_body_otp"><?php echo esc_html__('Email Body (OTP)', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <textarea id="zfl_email_body_otp" name="zfl_email_body_otp" rows="12" class="large-text code"><?php echo esc_textarea($email_body_otp); ?></textarea>
                    <p class="description">
                        <strong><?php echo esc_html__('Available placeholders:', 'zero-friction-login'); ?></strong><br>
                        <code>{OTP}</code> - <?php echo esc_html__('The one-time password code', 'zero-friction-login'); ?><br>
                        <code>{SITE_NAME}</code> - <?php echo esc_html__('Your website name', 'zero-friction-login'); ?><br>
                        <code>{IP}</code> - <?php echo esc_html__("User's IP address", 'zero-friction-login'); ?><br>
                        <code>{BROWSER}</code> - <?php echo esc_html__("User's browser", 'zero-friction-login'); ?><br>
                        <code>{DEVICE}</code> - <?php echo esc_html__("User's device type", 'zero-friction-login'); ?><br>
                        <code>{TIME}</code> - <?php echo esc_html__('Current time', 'zero-friction-login'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <hr style="margin: 30px 0; border: 0; border-top: 1px solid #ddd;">

        <h3><?php echo esc_html__('Magic Link Email Template', 'zero-friction-login'); ?></h3>
        <p><?php echo esc_html__('This template is used when sending magic login links.', 'zero-friction-login'); ?></p>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="zfl_email_subject_magic_link"><?php echo esc_html__('Email Subject (Magic Link)', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <input type="text" id="zfl_email_subject_magic_link" name="zfl_email_subject_magic_link"
                           value="<?php echo esc_attr($email_subject_magic); ?>" class="large-text">
                    <p class="description"><?php echo esc_html__('Available placeholders: {SITE_NAME}, {TIME}', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_email_body_magic_link"><?php echo esc_html__('Email Body (Magic Link)', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <textarea id="zfl_email_body_magic_link" name="zfl_email_body_magic_link" rows="12" class="large-text code"><?php echo esc_textarea($email_body_magic); ?></textarea>
                    <p class="description">
                        <strong><?php echo esc_html__('Available placeholders:', 'zero-friction-login'); ?></strong><br>
                        <code>{MAGIC_LINK}</code> - <?php echo esc_html__('The magic link URL', 'zero-friction-login'); ?><br>
                        <code>{SITE_NAME}</code> - <?php echo esc_html__('Your website name', 'zero-friction-login'); ?><br>
                        <code>{IP}</code> - <?php echo esc_html__("User's IP address", 'zero-friction-login'); ?><br>
                        <code>{BROWSER}</code> - <?php echo esc_html__("User's browser", 'zero-friction-login'); ?><br>
                        <code>{DEVICE}</code> - <?php echo esc_html__("User's device type", 'zero-friction-login'); ?><br>
                        <code>{TIME}</code> - <?php echo esc_html__('Current time', 'zero-friction-login'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    private function render_section_header($title) {
        ?>
        <tr class="zfl-section-header">
            <th scope="row" colspan="2">
                <h3><?php echo esc_html($this->t($title)); ?></h3>
            </th>
        </tr>
        <?php
    }

    private function render_setting_row($id, $label, $value, $args = array()) {
        $type = isset($args['type']) ? $args['type'] : 'text';
        $class = isset($args['class']) ? $args['class'] : 'regular-text';
        $description = isset($args['description']) ? $args['description'] : '';
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        $min = isset($args['min']) ? $args['min'] : null;
        $max = isset($args['max']) ? $args['max'] : null;
        $step = isset($args['step']) ? $args['step'] : null;
        $options = isset($args['options']) ? $args['options'] : array();
        ?>
        <tr>
            <th scope="row">
                <label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($this->t($label)); ?></label>
            </th>
            <td>
                <?php if ($type === 'select'): ?>
                    <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>">
                        <?php foreach ($options as $option_value => $option_label): ?>
                            <option value="<?php echo esc_attr($option_value); ?>" <?php selected($value, $option_value); ?>>
                                <?php echo esc_html($this->t($option_label)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($type === 'checkbox'): ?>
                    <input type="checkbox" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($id); ?>" value="1" <?php checked($value, 1); ?>>
                <?php else: ?>
                    <input
                        type="<?php echo esc_attr($type); ?>"
                        id="<?php echo esc_attr($id); ?>"
                        name="<?php echo esc_attr($id); ?>"
                        value="<?php echo esc_attr($value); ?>"
                        class="<?php echo esc_attr($class); ?>"
                        <?php echo $placeholder ? 'placeholder="' . esc_attr($this->t($placeholder)) . '"' : ''; ?>
                        <?php echo $min !== null ? 'min="' . esc_attr($min) . '"' : ''; ?>
                        <?php echo $max !== null ? 'max="' . esc_attr($max) . '"' : ''; ?>
                        <?php echo $step !== null ? 'step="' . esc_attr($step) . '"' : ''; ?>
                    >
                <?php endif; ?>
                <?php if (!empty($description)): ?>
                    <p class="description"><?php echo esc_html($this->t($description)); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    private function render_page_dropdown($name, $selected) {
        $field_name = sanitize_key((string) $name);

        $dropdown_html = wp_dropdown_pages(array(
            'name' => esc_attr($field_name),
            'id' => esc_attr($field_name),
            'selected' => absint($selected),
            'show_option_none' => esc_html__('— Select —', 'zero-friction-login'),
            'option_none_value' => '0',
            'echo' => 0,
        ));

        $allowed_html = array(
            'select' => array(
                'name' => true,
                'id' => true,
                'class' => true,
            ),
            'option' => array(
                'value' => true,
                'selected' => true,
            ),
        );

        echo wp_kses($dropdown_html, $allowed_html);
    }

    private function render_design_tab() {
        $logo_id = get_option('zfl_logo_id', 0);
        $logo_url = '';
        $export_json = $this->get_design_styles_export_json();
        if ($logo_id) {
            $logo_url = wp_get_attachment_image_url($logo_id, 'medium');
        }

        $font_options = array(
            'system_default' => 'System Default',
            'roboto' => 'Roboto',
            'open_sans' => 'Open Sans',
            'lato' => 'Lato',
            'montserrat' => 'Montserrat'
        );

        $sections = array(
            'Base Typography' => array(
                array('id' => 'zfl_text_primary_color', 'label' => 'Primary Text Color', 'type' => 'color', 'default' => '#111827'),
                array('id' => 'zfl_text_secondary_color', 'label' => 'Secondary Text Color', 'type' => 'color', 'default' => '#6b7280'),
                array('id' => 'zfl_text_muted_color', 'label' => 'Muted Text Color', 'type' => 'color', 'default' => '#4b5563'),
                array('id' => 'zfl_text_label_color', 'label' => 'Label Text Color', 'type' => 'color', 'default' => '#374151'),
                array('id' => 'zfl_text_inverse_color', 'label' => 'Inverse Text Color', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_font_size_base', 'label' => 'Base Font Size', 'type' => 'text', 'default' => '16px'),
                array('id' => 'zfl_font_size_sm', 'label' => 'Small Font Size', 'type' => 'text', 'default' => '14px'),
                array('id' => 'zfl_font_size_xs', 'label' => 'Extra Small Font Size', 'type' => 'text', 'default' => '12px'),
                array('id' => 'zfl_heading_size_h1', 'label' => 'Heading Size (H1)', 'type' => 'text', 'default' => '24px'),
                array('id' => 'zfl_heading_size_h2', 'label' => 'Heading Size (H2)', 'type' => 'text', 'default' => '20px'),
                array('id' => 'zfl_line_height_base', 'label' => 'Base Line Height', 'type' => 'text', 'default' => '1.5'),
                array('id' => 'zfl_heading_font', 'label' => 'Heading Font', 'type' => 'select', 'default' => 'system_default', 'options' => $font_options),
                array('id' => 'zfl_body_font', 'label' => 'Body Font', 'type' => 'select', 'default' => 'system_default', 'options' => $font_options)
            ),
            'Card & Layout' => array(
                array('id' => 'zfl_card_background', 'label' => 'Form Card Background', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_overlay_background', 'label' => 'Page Overlay Background', 'type' => 'color', 'default' => '#f0f0f0'),
                array('id' => 'zfl_primary_color', 'label' => 'Primary Brand Color', 'type' => 'color', 'default' => '#0073aa'),
                array('id' => 'zfl_card_radius', 'label' => 'Card Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_card_shadow', 'label' => 'Card Shadow', 'type' => 'text', 'default' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
                array('id' => 'zfl_card_padding', 'label' => 'Card Padding', 'type' => 'text', 'default' => '20px'),
                array('id' => 'zfl_logged_in_card_padding', 'label' => 'Logged-In Card Padding', 'type' => 'text', 'default' => '32px'),
                array('id' => 'zfl_form_gap', 'label' => 'Form Gap', 'type' => 'text', 'default' => '16px'),
                array('id' => 'zfl_section_gap', 'label' => 'Section Gap', 'type' => 'text', 'default' => '16px')
            ),
            'Inputs' => array(
                array('id' => 'zfl_input_background', 'label' => 'Input Background', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_input_text_color', 'label' => 'Input Text Color', 'type' => 'color', 'default' => '#111827'),
                array('id' => 'zfl_input_placeholder_color', 'label' => 'Input Placeholder Color', 'type' => 'color', 'default' => '#9ca3af'),
                array('id' => 'zfl_input_border_color', 'label' => 'Input Border Color', 'type' => 'color', 'default' => '#d1d5db'),
                array('id' => 'zfl_input_border_color_focus', 'label' => 'Input Border Color (Focus)', 'type' => 'color', 'default' => '#9ca3af'),
                array('id' => 'zfl_input_border_width', 'label' => 'Input Border Width', 'type' => 'text', 'default' => '1px'),
                array('id' => 'zfl_input_radius', 'label' => 'Input Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_input_focus_ring_color', 'label' => 'Input Focus Ring Color', 'type' => 'color', 'default' => '#b2d5e5'),
                array('id' => 'zfl_input_focus_ring_width', 'label' => 'Input Focus Ring Width', 'type' => 'text', 'default' => '3px'),
                array('id' => 'zfl_input_disabled_bg', 'label' => 'Input Disabled Background', 'type' => 'color', 'default' => '#f3f4f6'),
                array('id' => 'zfl_input_disabled_text', 'label' => 'Input Disabled Text', 'type' => 'color', 'default' => '#9ca3af'),
                array('id' => 'zfl_input_error_border', 'label' => 'Input Error Border', 'type' => 'color', 'default' => '#ef4444'),
                array('id' => 'zfl_input_padding_x', 'label' => 'Input Padding X', 'type' => 'text', 'default' => '12px'),
                array('id' => 'zfl_input_padding_y', 'label' => 'Input Padding Y', 'type' => 'text', 'default' => '10px')
            ),
            'OTP Boxes' => array(
                array('id' => 'zfl_otp_box_bg', 'label' => 'OTP Box Background', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_otp_box_text_color', 'label' => 'OTP Box Text Color', 'type' => 'color', 'default' => '#111827'),
                array('id' => 'zfl_otp_box_border_color', 'label' => 'OTP Box Border Color', 'type' => 'color', 'default' => '#d1d5db'),
                array('id' => 'zfl_otp_box_border_color_filled', 'label' => 'OTP Box Border Color (Filled)', 'type' => 'color', 'default' => '#9ca3af'),
                array('id' => 'zfl_otp_box_border_color_active', 'label' => 'OTP Box Border Color (Active)', 'type' => 'color', 'default' => '#0073aa'),
                array('id' => 'zfl_otp_box_border_color_error', 'label' => 'OTP Box Border Color (Error)', 'type' => 'color', 'default' => '#ef4444'),
                array('id' => 'zfl_otp_box_border_width', 'label' => 'OTP Box Border Width', 'type' => 'text', 'default' => '2px'),
                array('id' => 'zfl_otp_box_radius', 'label' => 'OTP Box Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_otp_box_size_6_w', 'label' => 'OTP Box Size (6) Width', 'type' => 'text', 'default' => '44px'),
                array('id' => 'zfl_otp_box_size_6_h', 'label' => 'OTP Box Size (6) Height', 'type' => 'text', 'default' => '48px'),
                array('id' => 'zfl_otp_box_size_8_w', 'label' => 'OTP Box Size (8) Width', 'type' => 'text', 'default' => '48px'),
                array('id' => 'zfl_otp_box_size_8_h', 'label' => 'OTP Box Size (8) Height', 'type' => 'text', 'default' => '56px'),
                array('id' => 'zfl_otp_box_font_size', 'label' => 'OTP Box Font Size', 'type' => 'text', 'default' => '20px'),
                array('id' => 'zfl_otp_box_gap', 'label' => 'OTP Box Gap', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_otp_box_disabled_bg', 'label' => 'OTP Box Disabled Background', 'type' => 'color', 'default' => '#f3f4f6')
            ),
            'Buttons' => array(
                array('id' => 'zfl_button_background', 'label' => 'Primary Button Background', 'type' => 'color', 'default' => '#0073aa'),
                array('id' => 'zfl_button_text_color', 'label' => 'Primary Button Text Color', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_button_hover_background', 'label' => 'Primary Button Hover Background', 'type' => 'color', 'default' => '#006799'),
                array('id' => 'zfl_button_active_background', 'label' => 'Primary Button Active Background', 'type' => 'color', 'default' => '#006190'),
                array('id' => 'zfl_button_disabled_background', 'label' => 'Primary Button Disabled Background', 'type' => 'color', 'default' => '#d1d5db'),
                array('id' => 'zfl_button_disabled_text', 'label' => 'Primary Button Disabled Text', 'type' => 'color', 'default' => '#6b7280'),
                array('id' => 'zfl_button_radius', 'label' => 'Button Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_button_padding_x', 'label' => 'Button Padding X', 'type' => 'text', 'default' => '16px'),
                array('id' => 'zfl_button_padding_y', 'label' => 'Button Padding Y', 'type' => 'text', 'default' => '10px'),
                array('id' => 'zfl_secondary_button_background', 'label' => 'Secondary Button Background', 'type' => 'color', 'default' => '#f3f4f6'),
                array('id' => 'zfl_secondary_button_text_color', 'label' => 'Secondary Button Text Color', 'type' => 'color', 'default' => '#374151'),
                array('id' => 'zfl_secondary_button_hover_background', 'label' => 'Secondary Button Hover Background', 'type' => 'color', 'default' => '#dadbdd'),
                array('id' => 'zfl_secondary_button_active_background', 'label' => 'Secondary Button Active Background', 'type' => 'color', 'default' => '#cecfd1'),
                array('id' => 'zfl_destructive_button_background', 'label' => 'Destructive Button Background', 'type' => 'color', 'default' => '#dc2626'),
                array('id' => 'zfl_destructive_button_text', 'label' => 'Destructive Button Text', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_destructive_button_hover_background', 'label' => 'Destructive Button Hover Background', 'type' => 'color', 'default' => '#dc2626'),
                array('id' => 'zfl_destructive_button_active_background', 'label' => 'Destructive Button Active Background', 'type' => 'color', 'default' => '#dc2626'),
                array('id' => 'zfl_destructive_button_disabled_background', 'label' => 'Destructive Button Disabled Background', 'type' => 'color', 'default' => '#d1d5db'),
                array('id' => 'zfl_destructive_button_disabled_text', 'label' => 'Destructive Button Disabled Text', 'type' => 'color', 'default' => '#6b7280')
            ),
            'Links' => array(
                array('id' => 'zfl_link_color', 'label' => 'Link Color', 'type' => 'color', 'default' => '#2563eb'),
                array('id' => 'zfl_link_hover_color', 'label' => 'Link Hover Color', 'type' => 'color', 'default' => '#1d4ed8'),
                array('id' => 'zfl_link_decoration', 'label' => 'Link Decoration', 'type' => 'select', 'default' => 'underline', 'options' => array('underline' => 'Underline', 'none' => 'None')),
                array('id' => 'zfl_link_hover_decoration', 'label' => 'Link Hover Decoration', 'type' => 'select', 'default' => 'underline', 'options' => array('underline' => 'Underline', 'none' => 'None'))
            ),
            'Tabs' => array(
                array('id' => 'zfl_tab_background', 'label' => 'Tab Background (Inactive)', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_tab_text_color', 'label' => 'Tab Text Color (Inactive)', 'type' => 'color', 'default' => '#6b7280'),
                array('id' => 'zfl_active_tab_background', 'label' => 'Tab Background (Active)', 'type' => 'color', 'default' => '#f9fafb'),
                array('id' => 'zfl_active_tab_text_color', 'label' => 'Tab Text Color (Active)', 'type' => 'color', 'default' => '#111827'),
                array('id' => 'zfl_tab_border_width', 'label' => 'Tab Border Width', 'type' => 'text', 'default' => '2px'),
                array('id' => 'zfl_tab_border_color_inactive', 'label' => 'Tab Border Color (Inactive)', 'type' => 'text', 'default' => 'transparent'),
                array('id' => 'zfl_tab_border_color_active', 'label' => 'Tab Border Color (Active)', 'type' => 'color', 'default' => '#0073aa'),
                array('id' => 'zfl_tab_radius', 'label' => 'Tab Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_tab_padding_x', 'label' => 'Tab Padding X', 'type' => 'text', 'default' => '16px'),
                array('id' => 'zfl_tab_padding_y', 'label' => 'Tab Padding Y', 'type' => 'text', 'default' => '10px')
            ),
            'Notices' => array(
                array('id' => 'zfl_notice_radius', 'label' => 'Notice Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_notice_padding', 'label' => 'Notice Padding', 'type' => 'text', 'default' => '12px'),
                array('id' => 'zfl_notice_border_width', 'label' => 'Notice Border Width', 'type' => 'text', 'default' => '1px'),
                array('id' => 'zfl_notice_error_bg', 'label' => 'Error Notice Background', 'type' => 'color', 'default' => '#fef2f2'),
                array('id' => 'zfl_notice_error_border', 'label' => 'Error Notice Border', 'type' => 'color', 'default' => '#fecaca'),
                array('id' => 'zfl_notice_error_text', 'label' => 'Error Notice Text', 'type' => 'color', 'default' => '#991b1b'),
                array('id' => 'zfl_notice_success_bg', 'label' => 'Success Notice Background', 'type' => 'color', 'default' => '#f0fdf4'),
                array('id' => 'zfl_notice_success_border', 'label' => 'Success Notice Border', 'type' => 'color', 'default' => '#bbf7d0'),
                array('id' => 'zfl_notice_success_text', 'label' => 'Success Notice Text', 'type' => 'color', 'default' => '#166534'),
                array('id' => 'zfl_notice_info_bg', 'label' => 'Info Notice Background', 'type' => 'color', 'default' => '#eff6ff'),
                array('id' => 'zfl_notice_info_border', 'label' => 'Info Notice Border', 'type' => 'color', 'default' => '#bfdbfe'),
                array('id' => 'zfl_notice_info_text', 'label' => 'Info Notice Text', 'type' => 'color', 'default' => '#1e40af')
            ),
            'Toasts' => array(
                array('id' => 'zfl_toast_success_bg', 'label' => 'Toast Success Background', 'type' => 'color', 'default' => '#16a34a'),
                array('id' => 'zfl_toast_error_bg', 'label' => 'Toast Error Background', 'type' => 'color', 'default' => '#dc2626'),
                array('id' => 'zfl_toast_info_bg', 'label' => 'Toast Info Background', 'type' => 'color', 'default' => '#2563eb'),
                array('id' => 'zfl_toast_text_color', 'label' => 'Toast Text Color', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_toast_close_color', 'label' => 'Toast Close Icon Color', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_toast_close_hover_color', 'label' => 'Toast Close Hover Color', 'type' => 'color', 'default' => '#e5e7eb'),
                array('id' => 'zfl_toast_close_bg', 'label' => 'Toast Close Background', 'type' => 'text', 'default' => 'transparent', 'description' => 'Use a hex color like #111827 or "transparent".'),
                array('id' => 'zfl_toast_close_hover_bg', 'label' => 'Toast Close Hover Background', 'type' => 'text', 'default' => 'transparent', 'description' => 'Use a hex color like #1f2937 or "transparent".'),
                array('id' => 'zfl_toast_radius', 'label' => 'Toast Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_toast_shadow', 'label' => 'Toast Shadow', 'type' => 'text', 'default' => '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)'),
                array('id' => 'zfl_toast_padding_x', 'label' => 'Toast Padding X', 'type' => 'text', 'default' => '24px'),
                array('id' => 'zfl_toast_padding_y', 'label' => 'Toast Padding Y', 'type' => 'text', 'default' => '16px'),
                array('id' => 'zfl_toast_max_width', 'label' => 'Toast Max Width', 'type' => 'text', 'default' => '448px')
            ),
            'Icons' => array(
                array('id' => 'zfl_magic_icon_bg', 'label' => 'Magic Link Icon Background', 'type' => 'color', 'default' => '#dbeafe'),
                array('id' => 'zfl_magic_icon_color', 'label' => 'Magic Link Icon Color', 'type' => 'color', 'default' => '#2563eb'),
                array('id' => 'zfl_success_icon_bg', 'label' => 'Success Icon Background', 'type' => 'color', 'default' => '#dcfce7'),
                array('id' => 'zfl_success_icon_color', 'label' => 'Success Icon Color', 'type' => 'color', 'default' => '#16a34a'),
                array('id' => 'zfl_logged_in_icon_bg', 'label' => 'Logged-In Icon Background', 'type' => 'color', 'default' => '#dcfce7'),
                array('id' => 'zfl_logged_in_icon_color', 'label' => 'Logged-In Icon Color', 'type' => 'color', 'default' => '#16a34a'),
                array('id' => 'zfl_modal_icon_bg', 'label' => 'Modal Icon Background', 'type' => 'color', 'default' => '#fee2e2'),
                array('id' => 'zfl_modal_icon_color', 'label' => 'Modal Icon Color', 'type' => 'color', 'default' => '#dc2626'),
                array('id' => 'zfl_icon_circle_size_sm', 'label' => 'Icon Circle Size (Small)', 'type' => 'text', 'default' => '64px'),
                array('id' => 'zfl_icon_circle_size_md', 'label' => 'Icon Circle Size (Medium)', 'type' => 'text', 'default' => '80px'),
                array('id' => 'zfl_icon_size_sm', 'label' => 'Icon Size (Small)', 'type' => 'text', 'default' => '32px'),
                array('id' => 'zfl_icon_size_md', 'label' => 'Icon Size (Medium)', 'type' => 'text', 'default' => '40px')
            ),
            'Modal' => array(
                array('id' => 'zfl_modal_background', 'label' => 'Modal Background', 'type' => 'color', 'default' => '#ffffff'),
                array('id' => 'zfl_modal_text_color', 'label' => 'Modal Text Color', 'type' => 'color', 'default' => '#111827'),
                array('id' => 'zfl_modal_radius', 'label' => 'Modal Radius', 'type' => 'text', 'default' => '8px'),
                array('id' => 'zfl_modal_shadow', 'label' => 'Modal Shadow', 'type' => 'text', 'default' => '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)'),
                array('id' => 'zfl_modal_padding', 'label' => 'Modal Padding', 'type' => 'text', 'default' => '24px'),
                array('id' => 'zfl_modal_overlay_color', 'label' => 'Modal Overlay Color', 'type' => 'color', 'default' => '#000000'),
                array('id' => 'zfl_modal_overlay_opacity', 'label' => 'Modal Overlay Opacity', 'type' => 'text', 'default' => '0.5')
            ),
            'Loaders' => array(
                array('id' => 'zfl_spinner_color', 'label' => 'Spinner Color', 'type' => 'color', 'default' => '#2563eb'),
                array('id' => 'zfl_spinner_success_color', 'label' => 'Spinner Success Color', 'type' => 'color', 'default' => '#0073aa'),
                array('id' => 'zfl_spinner_size_lg', 'label' => 'Spinner Size (Large)', 'type' => 'text', 'default' => '48px'),
                array('id' => 'zfl_spinner_size_sm', 'label' => 'Spinner Size (Small)', 'type' => 'text', 'default' => '32px'),
                array('id' => 'zfl_spinner_border_width', 'label' => 'Spinner Border Width', 'type' => 'text', 'default' => '2px')
            ),
            'Footer' => array(
                array('id' => 'zfl_footer_text_color', 'label' => 'Footer Text Color', 'type' => 'color', 'default' => '#6b7280'),
                array('id' => 'zfl_footer_font_size', 'label' => 'Footer Font Size', 'type' => 'text', 'default' => '12px')
            ),
            'Animations' => array(
                array('id' => 'zfl_animation_enabled', 'label' => 'Enable Animations', 'type' => 'checkbox', 'default' => 1),
                array('id' => 'zfl_animation_slide_in_duration', 'label' => 'Toast Slide-In Duration', 'type' => 'text', 'default' => '300ms'),
                array('id' => 'zfl_animation_scale_in_duration', 'label' => 'Modal Scale-In Duration', 'type' => 'text', 'default' => '200ms'),
                array('id' => 'zfl_transition_duration', 'label' => 'Transition Duration', 'type' => 'text', 'default' => '200ms')
            )
        );
        ?>
        <table class="form-table" role="presentation">
            <?php $this->render_section_header('Branding'); ?>
            <tr>
                <th scope="row">
                    <label><?php echo esc_html__('Logo', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <div class="zfl-logo-upload">
                        <input type="hidden" id="zfl_logo_id" name="zfl_logo_id" value="<?php echo esc_attr($logo_id); ?>">
                        <div id="zfl_logo_preview" style="margin-bottom: 10px;">
                            <?php if ($logo_url): ?>
                                <img src="<?php echo esc_url($logo_url); ?>" style="max-width: 200px; height: auto;">
                            <?php endif; ?>
                        </div>
                        <div class="zfl-logo-actions">
                            <button type="button" class="button" id="zfl_upload_logo_button">
                                <?php echo $logo_id ? esc_html__('Change Logo', 'zero-friction-login') : esc_html__('Upload Logo', 'zero-friction-login'); ?>
                            </button>
                            <?php if ($logo_id): ?>
                                <button type="button" class="button" id="zfl_remove_logo_button"><?php echo esc_html__('Remove Logo', 'zero-friction-login'); ?></button>
                            <?php endif; ?>
                        </div>
                        <p class="description"><?php echo esc_html__('Logo displayed on the login form.', 'zero-friction-login'); ?></p>
                    </div>
                </td>
            </tr>
            <?php
            $this->render_setting_row(
                'zfl_logo_width',
                'Logo Width (px)',
                get_option('zfl_logo_width', 150),
                array(
                    'type' => 'number',
                    'min' => 50,
                    'max' => 500,
                    'class' => 'small-text',
                    'description' => 'Width of the logo in pixels (50-500).'
                )
            );
            ?>
            <tr class="zfl-section-header">
                <th scope="row" colspan="2">
                    <h3><?php echo esc_html__('Style Tools', 'zero-friction-login'); ?></h3>
                </th>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_design_export_json"><?php echo esc_html__('Export Styles JSON', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <textarea id="zfl_design_export_json" class="large-text code" rows="8" readonly><?php echo esc_textarea($export_json); ?></textarea>
                    <p class="description"><?php echo esc_html__('Copy this JSON as a backup, or import it on another site using the field below.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="zfl_design_import_json"><?php echo esc_html__('Import Styles JSON', 'zero-friction-login'); ?></label>
                </th>
                <td>
                    <textarea id="zfl_design_import_json" name="zfl_design_import_json" class="large-text code" rows="8" placeholder="<?php echo esc_attr__('Paste exported JSON here.', 'zero-friction-login'); ?>"></textarea>
                    <p class="description"><?php echo esc_html__('Only recognized design style keys are imported. Logo image ID is excluded for portability.', 'zero-friction-login'); ?></p>
                    <p>
                        <button type="submit" class="button button-secondary" name="zfl_import_design_styles" value="1"><?php echo esc_html__('Import Styles', 'zero-friction-login'); ?></button>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php echo esc_html__('Reset Styles', 'zero-friction-login'); ?></th>
                <td>
                    <button
                        type="submit"
                        class="button"
                        name="zfl_reset_design_styles"
                        value="1"
                        onclick="return confirm('<?php echo esc_js(__('Reset all design styles to default values? This cannot be undone.', 'zero-friction-login')); ?>');"
                    >
                        <?php echo esc_html__('Reset Styles to Default', 'zero-friction-login'); ?>
                    </button>
                    <p class="description"><?php echo esc_html__('This resets style tokens to defaults and keeps your uploaded logo image.', 'zero-friction-login'); ?></p>
                </td>
            </tr>
            <?php

            foreach ($sections as $section_title => $fields) {
                $this->render_section_header($section_title);

                foreach ($fields as $field) {
                    $value = get_option($field['id'], $field['default']);
                    $type = isset($field['type']) ? $field['type'] : 'text';
                    $class = $type === 'color' ? 'zfl-color-picker' : 'regular-text';
                    $options = isset($field['options']) ? $field['options'] : array();
                    $description = isset($field['description']) ? $field['description'] : '';

                    $this->render_setting_row(
                        $field['id'],
                        $field['label'],
                        $value,
                        array(
                            'type' => $type === 'color' ? 'text' : $type,
                            'class' => $class,
                            'options' => $options,
                            'description' => $description
                        )
                    );
                }
            }
            ?>
        </table>
        <?php
    }

    private function render_about_tab() {
        ?>
        <div class="zfl-about-wrapper" style="margin-top: 20px;">
            <div class="zfl-about-grid">

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Installation & Usage', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <h3><?php echo esc_html__('Quick Start Guide', 'zero-friction-login'); ?></h3>
                    <ol style="line-height: 1.8;">
                        <li><strong><?php echo esc_html__('Create a Login Page:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Create a new page in WordPress (e.g., "Login")', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Add the Shortcode:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Add the shortcode', 'zero-friction-login'); ?> <code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">[zero_friction_login]</code> <?php echo esc_html__('to your page', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Publish:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Save and publish your page', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Configure Settings:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Customize the login method, email templates, and design in the settings tabs above', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Test:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Visit your login page and test the authentication flow', 'zero-friction-login'); ?></li>
                    </ol>

                    <h3 style="margin-top: 30px;"><?php echo esc_html__('Available Shortcode', 'zero-friction-login'); ?></h3>
                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Shortcode', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Description', 'zero-friction-login'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">[zero_friction_login]</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Displays the login/registration form with OTP or Magic Link authentication', 'zero-friction-login'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Security Features', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <p><?php echo esc_html__('Zero Friction Login includes comprehensive security measures to protect your users and website:', 'zero-friction-login'); ?></p>

                    <h3 style="margin-top: 25px;"><?php echo esc_html__('Rate Limiting & Protection', 'zero-friction-login'); ?></h3>
                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px; width: 40%;"><strong><?php echo esc_html__('Security Measure', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px; width: 30%;"><strong><?php echo esc_html__('Limit', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px; width: 30%;"><strong><?php echo esc_html__('Action', 'zero-friction-login'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('OTP Requests per Email', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><strong><?php echo esc_html__('3 per hour', 'zero-friction-login'); ?></strong></td>
                                <td style="padding: 12px;"><?php echo esc_html__('30-minute lockout after exceeding', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><?php echo esc_html__('Rapid OTP Requests', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><strong><?php echo esc_html__('5 within 30 seconds', 'zero-friction-login'); ?></strong></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Request blocked, user must wait', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('Requests per IP Address', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><strong><?php echo esc_html__('20 per hour', 'zero-friction-login'); ?></strong></td>
                                <td style="padding: 12px;"><?php echo esc_html__('30-minute IP lockout after exceeding', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><?php echo esc_html__('OTP Code Expiration', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><strong><?php echo esc_html__('5 minutes', 'zero-friction-login'); ?></strong> <?php echo esc_html__('(configurable)', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Code becomes invalid after expiry', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('Lockout Duration', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><strong><?php echo esc_html__('30 minutes', 'zero-friction-login'); ?></strong></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Automatic unlock after duration', 'zero-friction-login'); ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <h3 style="margin-top: 30px;"><?php echo esc_html__('What Happens When Limits Are Exceeded?', 'zero-friction-login'); ?></h3>
                    <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-top: 15px;">
                        <h4 style="margin-top: 0;"><?php echo esc_html__('For Users:', 'zero-friction-login'); ?></h4>
                        <ul style="margin-bottom: 0; line-height: 1.8;">
                            <li><strong><?php echo esc_html__('Email Rate Limit (3/hour):', 'zero-friction-login'); ?></strong> <?php echo esc_html__('User sees "Too many requests. Please try again in 30 minutes." They cannot request more OTPs until the lockout expires.', 'zero-friction-login'); ?></li>
                            <li><strong><?php echo esc_html__('Rapid Requests (5/30 sec):', 'zero-friction-login'); ?></strong> <?php echo esc_html__('User sees "Too many requests. Please wait before trying again." They must wait briefly before the next attempt.', 'zero-friction-login'); ?></li>
                            <li><strong><?php echo esc_html__('IP Rate Limit (20/hour):', 'zero-friction-login'); ?></strong> <?php echo esc_html__('All requests from that IP are blocked for 30 minutes with "Too many attempts from this IP."', 'zero-friction-login'); ?></li>
                        </ul>
                    </div>

                    <div style="background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin-top: 20px;">
                        <h4 style="margin-top: 0;"><?php echo esc_html__('For Store Owners:', 'zero-friction-login'); ?></h4>
                        <ul style="margin-bottom: 0; line-height: 1.8;">
                            <li><?php echo esc_html__('Automatic protection against brute force attacks', 'zero-friction-login'); ?></li>
                            <li><?php echo esc_html__('Prevention of email bombing and spam', 'zero-friction-login'); ?></li>
                            <li><?php echo esc_html__('Reduced server load from malicious requests', 'zero-friction-login'); ?></li>
                            <li><?php echo esc_html__('All rate limiting is handled automatically - no configuration needed', 'zero-friction-login'); ?></li>
                        </ul>
                    </div>

                    <h3 style="margin-top: 30px;"><?php echo esc_html__('Additional Security Features', 'zero-friction-login'); ?></h3>
                    <ul style="line-height: 1.8;">
                        <li><strong><?php echo esc_html__('Secure OTP Generation:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Cryptographically secure random codes (numeric or alphanumeric)', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('One-Time Use:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Each OTP code can only be used once and is immediately invalidated after successful login', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Time-Limited Codes:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('All codes expire after a configurable time (default: 5 minutes)', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Magic Link Security:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Unique, single-use login links with expiration times', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('IP Tracking:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Login attempts are logged with IP addresses for security monitoring', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Browser Detection:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('User agent information is captured in email notifications', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('SQL Injection Protection:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('All database queries use prepared statements', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('XSS Prevention:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('All user inputs are sanitized and escaped', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('CSRF Protection:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('WordPress nonce verification on all forms and API requests', 'zero-friction-login'); ?></li>
                        <li><strong><?php echo esc_html__('Data Encryption:', 'zero-friction-login'); ?></strong> <?php echo esc_html__('Sensitive data is hashed using SHA-256', 'zero-friction-login'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Authentication Methods', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <h3><?php echo esc_html__('Supported Login Methods', 'zero-friction-login'); ?></h3>
                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Method', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Format', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Security Level', 'zero-friction-login'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('6-Digit Numeric OTP', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;">123456</td>
                                <td style="padding: 12px;"><?php echo esc_html__('Good - Easy to type', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><?php echo esc_html__('6-Character Alphanumeric OTP', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;">A3B7K9</td>
                                <td style="padding: 12px;"><?php echo esc_html__('Better - More combinations', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('8-Digit Numeric OTP', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;">12345678</td>
                                <td style="padding: 12px;"><?php echo esc_html__('Better - Longer code', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><?php echo esc_html__('8-Character Alphanumeric OTP', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;">A3B7K9M2</td>
                                <td style="padding: 12px;"><?php echo esc_html__('Best - Maximum security', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><?php echo esc_html__('Magic Link Only', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><?php echo esc_html__('One-click email link', 'zero-friction-login'); ?></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Excellent - No code needed', 'zero-friction-login'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin-top: 20px;"><em><?php echo esc_html__('Note: You can configure the login method in the General Settings tab. Users will see only the method you choose.', 'zero-friction-login'); ?></em></p>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Email Configuration', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <h3><?php echo esc_html__('Available Email Placeholders', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Use these placeholders in your email templates to personalize authentication emails:', 'zero-friction-login'); ?></p>

                    <table class="widefat" style="margin-top: 15px;">
                        <thead>
                            <tr>
                                <th style="padding: 12px; width: 25%;"><strong><?php echo esc_html__('Placeholder', 'zero-friction-login'); ?></strong></th>
                                <th style="padding: 12px;"><strong><?php echo esc_html__('Description', 'zero-friction-login'); ?></strong></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{OTP}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__('The one-time password code (for OTP emails)', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{MAGIC_LINK}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__('The magic login link URL (for magic link emails)', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{SITE_NAME}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Your website name', 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{IP}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__("User's IP address", 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{BROWSER}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__("User's browser information", 'zero-friction-login'); ?></td>
                            </tr>
                            <tr style="background: #f9f9f9;">
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{DEVICE}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__("User's device type", 'zero-friction-login'); ?></td>
                            </tr>
                            <tr>
                                <td style="padding: 12px;"><code style="background: #f0f0f0; padding: 3px 8px; border-radius: 3px;">{TIME}</code></td>
                                <td style="padding: 12px;"><?php echo esc_html__('Current date and time', 'zero-friction-login'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Frequently Asked Questions', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <h3 style="margin-top: 0;"><?php echo esc_html__('Q: How does passwordless authentication work?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('When a user enters their email, the system generates a secure one-time code or magic link and sends it via email. The user then enters the code or clicks the link to authenticate. No passwords are stored or required.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__('Q: Is passwordless login secure?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Yes! Passwordless authentication eliminates common password vulnerabilities like weak passwords, password reuse, and phishing. Combined with our rate limiting and security features, it provides excellent protection.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__("Q: What happens if a user doesn't receive the email?", 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Users can request a new code (up to 3 times per hour). Check your SMTP settings and spam folders. Consider configuring custom SMTP in the SMTP Settings tab for better deliverability.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__('Q: Can I customize the look of the login form?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Yes! Use the Design & Branding tab to customize colors, fonts, logo, and overall appearance to match your brand.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__('Q: Does this work with WooCommerce?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Yes! The plugin integrates seamlessly with WooCommerce. You can force users to login before checkout and redirect the default WooCommerce login to your custom page.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__('Q: What if someone tries to spam my login form?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Our built-in rate limiting automatically blocks excessive requests. After 3 OTP requests per email per hour or 20 requests per IP per hour, the system applies a 30-minute lockout.', 'zero-friction-login'); ?></p>

                    <h3><?php echo esc_html__('Q: Can I allow new user registration?', 'zero-friction-login'); ?></h3>
                    <p><?php echo esc_html__('Yes! You can enable or disable new user registration in the General Settings tab. When disabled, only existing users can log in.', 'zero-friction-login'); ?></p>
                </div>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h2 style="margin-top: 0; padding: 15px; background: #f0f0f0; margin: 0;"><?php echo esc_html__('Support & Credits', 'zero-friction-login'); ?></h2>
                <div style="padding: 20px;">
                    <h3 style="margin-top: 0;"><?php echo esc_html__('Created By', 'zero-friction-login'); ?></h3>
                    <p><strong>JustWPThings</strong> - <a href="https://justwpthings.com" target="_blank">https://justwpthings.com</a></p>

                    <h3 style="margin-top: 25px;"><?php echo esc_html__('Support Development', 'zero-friction-login'); ?></h3>
                    <p><?php
                        echo wp_kses_post(
                            sprintf(
                                /* translators: %s: Donation URL. */
                                __('If you find this plugin useful, please consider <a href="%s" target="_blank">making a donation</a> to support further development and help make the plugin more sustainable.', 'zero-friction-login'),
                                esc_url('https://justwpthings.com/donate/')
                            )
                        );
                    ?></p>

                    <h3 style="margin-top: 25px;"><?php echo esc_html__('Plugin Version', 'zero-friction-login'); ?></h3>
                    <p><strong><?php echo esc_html__('Version:', 'zero-friction-login'); ?></strong> <?php echo esc_html(defined('ZFL_VERSION') ? ZFL_VERSION : '1.0.0'); ?></p>

                    <div style="background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin-top: 25px;">
                        <h4 style="margin-top: 0;"><?php echo esc_html__('Need Help?', 'zero-friction-login'); ?></h4>
                        <p style="margin-bottom: 0;"><?php echo esc_html__('For support, questions, or feature requests, visit our website or contact us through the support channels.', 'zero-friction-login'); ?></p>
                    </div>
                </div>
            </div>

            </div>
        </div>
        <?php
    }

    public static function get_option($key, $default = null) {
        $defaults = array(
            'zfl_login_method' => '6_digit_numeric',
            'zfl_otp_expiry' => 5,
            'zfl_redirect_after_login' => 'same_page',
            'zfl_redirect_login_page_id' => 0,
            'zfl_custom_login_url' => '',
            'zfl_redirect_after_logout' => 'same_page',
            'zfl_redirect_logout_page_id' => 0,
            'zfl_custom_logout_url' => '',
            'zfl_enable_smtp' => false,
            'zfl_smtp_host' => '',
            'zfl_smtp_port' => 587,
            'zfl_smtp_username' => '',
            'zfl_smtp_password' => '',
            'zfl_smtp_encryption' => 'tls',
            'zfl_smtp_from_email' => get_option('admin_email'),
            'zfl_smtp_from_name' => get_option('blogname'),
            'zfl_email_subject_otp' => __('Your login code for {SITE_NAME}', 'zero-friction-login'),
            'zfl_email_body_otp' => '',
            'zfl_email_subject_magic_link' => __('Your magic login link for {SITE_NAME}', 'zero-friction-login'),
            'zfl_email_body_magic_link' => '',
            'zfl_logo_id' => 0,
            'zfl_logo_width' => 150,
            'zfl_card_background' => '#ffffff',
            'zfl_overlay_background' => '#f0f0f0',
            'zfl_primary_color' => '#0073aa',
            'zfl_button_background' => '#0073aa',
            'zfl_button_text_color' => '#ffffff',
            'zfl_secondary_button_background' => '#f3f4f6',
            'zfl_secondary_button_text_color' => '#374151',
            'zfl_tab_background' => '#ffffff',
            'zfl_tab_text_color' => '#6b7280',
            'zfl_active_tab_background' => '#f9fafb',
            'zfl_active_tab_text_color' => '#111827',
            'zfl_heading_font' => 'system_default',
            'zfl_body_font' => 'system_default',
            'zfl_terms_page' => 0,
            'zfl_privacy_page' => 0,
            'zfl_show_policy_links' => true,
            'zfl_hide_footer_credit' => false,
            'zfl_force_custom_login' => false,
            'zfl_custom_login_page' => 0,
            'zfl_custom_login_page_url' => '',
            'zfl_force_login_checkout' => false,
            'zfl_test_mode' => false
        );

        $default_value = isset($defaults[$key]) ? $defaults[$key] : $default;
        return get_option($key, $default_value);
    }
}
