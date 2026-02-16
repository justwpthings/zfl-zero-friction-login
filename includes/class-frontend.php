<?php

if (!defined('ABSPATH')) {
    exit;
}

class ZFL_Frontend {

    private $assets_enqueued = false;

    public function __construct() {
        add_shortcode('zero_friction_login', array($this, 'render_login_form'));
        add_action('wp_footer', array($this, 'enqueue_assets_footer'));
    }

    private function is_elementor_editor() {
        if (isset($_GET['elementor-preview'])) {
            return true;
        }

        if (!did_action('elementor/loaded')) {
            return false;
        }

        if (isset(\Elementor\Plugin::$instance->editor) && \Elementor\Plugin::$instance->editor->is_edit_mode()) {
            return true;
        }

        if (isset(\Elementor\Plugin::$instance->preview) && \Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }

    public function render_login_form($atts) {
        $atts = shortcode_atts(array(
            'redirect' => '',
        ), $atts);

        if ($this->is_elementor_editor()) {
            return '<div style="padding: 20px; text-align: center; border: 2px dashed #ccc; background: #f9f9f9; border-radius: 4px; color: #666;">
                <strong>' . esc_html__('Zero Friction Login Form', 'zero-friction-login') . '</strong><br>
                <small style="color: #999;">' . esc_html__('Preview not available in editor mode', 'zero-friction-login') . '</small>
            </div>';
        }

        $this->enqueue_assets();

        ob_start();
        ?>
        <div id="zfl-login-root" data-redirect="<?php echo esc_attr($atts['redirect']); ?>"></div>
        <?php
        return ob_get_clean();
    }

    public function enqueue_assets_footer() {
        if (!$this->assets_enqueued) {
            return;
        }
    }

    private function enqueue_assets() {
        if ($this->assets_enqueued) {
            return;
        }

        $this->assets_enqueued = true;

        $manifest_path = ZFL_PLUGIN_DIR . 'assets/dist/manifest.json';

        if (!file_exists($manifest_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                echo '<div style="background: #ffebee; color: #c62828; padding: 15px; margin: 10px 0; border-left: 4px solid #c62828;">';
                echo '<strong>' . esc_html__('Zero Friction Login Error:', 'zero-friction-login') . '</strong> ' .
                    esc_html__('Frontend assets not found. Please run', 'zero-friction-login') . ' <code>npm run build</code> ' .
                    esc_html__('to generate the production build.', 'zero-friction-login');
                echo '</div>';
            }
            return;
        }

        $manifest = array();
        if (function_exists('wp_json_file_decode')) {
            $manifest = wp_json_file_decode($manifest_path, array('associative' => true));
        } else {
            $manifest_raw = file_get_contents($manifest_path);
            $manifest = $manifest_raw ? json_decode($manifest_raw, true) : array();
        }

        if (!is_array($manifest)) {
            return;
        }

        if (isset($manifest['src/frontend/index.tsx'])) {
            $entry = $manifest['src/frontend/index.tsx'];

            if (isset($entry['file'])) {
                wp_enqueue_script('wp-element');

                wp_add_inline_script('wp-element',
                    'window.React = window.wp.element; window.ReactDOM = window.wp.element;',
                    'after'
                );

                wp_enqueue_script(
                    'zfl-frontend-script',
                    ZFL_PLUGIN_URL . 'assets/dist/' . $entry['file'],
                    array('wp-element'),
                    ZFL_VERSION,
                    true
                );

                wp_localize_script('zfl-frontend-script', 'zflData', array(
                    'nonce' => wp_create_nonce('wp_rest'),
                    'apiUrl' => rest_url('zfl/v1'),
                ));
            }
        }
    }
}
