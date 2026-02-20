<?php

class LWS_Frontend {
    private $script_handle = 'lws-frontend';
    private $assets_enqueued = false;
    private $frontend_payload = null;
    private $asset_version = null;
    private $fallback_printed = false;

    public function init() {
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('login_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('login_with_supabase', array($this, 'render_login_shortcode'));
        add_shortcode('supabase_login', array($this, 'render_login_shortcode'));
        add_shortcode('lws', array($this, 'render_icon_shortcode'));
        add_shortcode('lws_debug', array($this, 'render_debug_info'));

        // Enable shortcodes in navigation menus (both classic and FSE)
        add_filter('wp_nav_menu_items', 'do_shortcode');
        add_filter('walker_nav_menu_start_el', array($this, 'enable_shortcodes_in_menu_items'), 10, 4);
        add_filter('render_block', array($this, 'process_shortcodes_in_navigation_block'), 10, 2);
        
        // Add custom nav menu meta box (for classic menus)
        add_action('admin_init', array($this, 'add_nav_menu_meta_boxes'));
        add_action('wp_ajax_lws_add_menu_item', array($this, 'ajax_add_menu_item'));

        if ((int) LWS_Admin::get_option('show_on_wp_login', 1) === 1) {
            add_action('login_footer', array($this, 'render_login_buttons_on_wp_login'));
        }

        $show_on_sensei = (int) LWS_Admin::get_option('show_on_sensei_forms', 0);
        if ($show_on_sensei === 1) {
            add_action('sensei_login_form_inside_after', array($this, 'render_login_buttons_on_sensei_login'), 10);
            add_action('sensei_register_form_end', array($this, 'render_login_buttons_on_sensei_register'), 10);
        }

        $show_on_woocommerce = (int) LWS_Admin::get_option('show_on_woocommerce_forms', 0);
        if ($show_on_woocommerce === 1) {
            add_action('woocommerce_login_form_end', array($this, 'render_login_buttons_on_woocommerce_login'), 10);
            add_action('woocommerce_register_form_end', array($this, 'render_login_buttons_on_woocommerce_register'), 10);
        }
    }

    public function register_rest_routes() {
        register_rest_route(
            'login-with-supabase/v1',
            '/session',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'handle_session_login'),
                'permission_callback' => array($this, 'validate_rest_request'),
            )
        );
    }

    public function validate_rest_request($request) {
        $nonce = $request->get_header('X-WP-Nonce');
        if (!$nonce) {
            return false;
        }

        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    public function enqueue_assets() {
        $is_login_screen = isset($GLOBALS['pagenow']) && 'wp-login.php' === $GLOBALS['pagenow'];

        if (is_admin() && !$is_login_screen) {
            return;
        }

        $options = LWS_Admin::get_options();
        $providers = LWS_Admin::get_enabled_providers();

        if (empty($options['supabase_url']) || empty($options['supabase_anon_key']) || empty($providers)) {
            return;
        }

        $version = $this->get_asset_version();

        // Register and enqueue Supabase JS library
        if (!wp_script_is('supabase-js', 'registered')) {
            wp_register_script('supabase-js', 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js', array(), '2.42.3', true);
        }
        if (!wp_script_is('supabase-js', 'enqueued')) {
            wp_enqueue_script('supabase-js');
        }

        // Register and enqueue plugin styles
        if (!wp_style_is($this->script_handle, 'registered')) {
            wp_register_style($this->script_handle, LWS_URL . 'assets/css/front.css', array(), $version);
        }
        if (!wp_style_is($this->script_handle, 'enqueued')) {
            wp_enqueue_style($this->script_handle);
        }

        // Register and enqueue plugin scripts
        if (!wp_script_is($this->script_handle, 'registered')) {
            wp_register_script($this->script_handle, LWS_URL . 'assets/js/front.js', array('supabase-js'), $version, true);
        }
        if (!wp_script_is($this->script_handle, 'enqueued')) {
            wp_enqueue_script($this->script_handle);
        }

        // Always set the localized data, even if already enqueued
        if (!$this->assets_enqueued) {
            $payload = $this->get_frontend_payload($options, $providers);
            wp_localize_script($this->script_handle, 'lwsAuth', $payload);
            $this->assets_enqueued = true;
        }
    }

    public function render_login_shortcode($atts = array()) {
        if (is_user_logged_in()) {
            return '<div class="lws-message lws-success">' . esc_html__('You are already signed in.', 'login-with-supabase') . '</div>';
        }

        $providers = LWS_Admin::get_enabled_providers();
        if (empty($providers)) {
            return '<div class="lws-message lws-error">' . esc_html__('Supabase providers are not configured.', 'login-with-supabase') . '</div>';
        }

        $atts = shortcode_atts(
            array(
                'layout' => 'vertical',
                'wrapper_class' => '',
            ),
            $atts,
            'login_with_supabase'
        );

        $layout_class = ('horizontal' === strtolower($atts['layout'])) ? 'lws-buttons--horizontal' : 'lws-buttons--vertical';
        $wrapper_classes = array('lws-login-wrapper');

        if (!empty($atts['wrapper_class'])) {
            $wrapper_classes[] = sanitize_html_class($atts['wrapper_class']);
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <div class="lws-buttons <?php echo esc_attr($layout_class); ?>">
                <?php foreach ($providers as $provider) : ?>
                    <button type="button" class="lws-button js-lws-login" data-provider="<?php echo esc_attr($provider); ?>">
                        <span class="lws-button-text"><?php echo esc_html($this->pretty_provider_label($provider)); ?></span>
                        <span class="lws-spinner" aria-hidden="true"></span>
                    </button>
                <?php endforeach; ?>
            </div>
            <div class="lws-feedback" role="alert" hidden></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public function render_icon_shortcode($atts = array()) {
        if (is_user_logged_in()) {
            return '';
        }

        $atts = shortcode_atts(
            array(
                'provider' => '',
                'size' => 'medium',
                'title' => '',
            ),
            $atts,
            'lws'
        );

        $provider = sanitize_key(strtolower($atts['provider']));
        
        if (empty($provider)) {
            return '<span class="lws-error" style="color: #c53030; font-size: 12px;">' . esc_html__('[lws] shortcode requires a provider attribute, e.g., [lws provider="azure"]', 'login-with-supabase') . '</span>';
        }

        $enabled_providers = LWS_Admin::get_enabled_providers();
        if (!in_array($provider, $enabled_providers, true)) {
            return '<span class="lws-error" style="color: #c53030; font-size: 12px;">' . esc_html(sprintf(__('Provider "%s" is not enabled or configured.', 'login-with-supabase'), $provider)) . '</span>';
        }

        $size_class = in_array($atts['size'], array('small', 'medium', 'large'), true) 
            ? 'lws-icon-button--' . $atts['size'] 
            : 'lws-icon-button--medium';

        $title_text = !empty($atts['title']) 
            ? esc_attr($atts['title']) 
            : esc_attr($this->pretty_provider_label($provider));

        $provider_icon = $this->get_provider_icon($provider);
        $unique_id = 'lws-icon-' . $provider . '-' . wp_rand(1000, 9999);

        ob_start();
        ?>
        <span class="lws-login-wrapper lws-icon-button-wrapper" id="<?php echo esc_attr($unique_id); ?>">
            <button type="button" 
                    class="lws-button lws-icon-button <?php echo esc_attr($size_class); ?> js-lws-login" 
                    data-provider="<?php echo esc_attr($provider); ?>"
                    data-icon-injected="1"
                    title="<?php echo $title_text; ?>"
                    aria-label="<?php echo $title_text; ?>">
                <?php echo $provider_icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <span class="lws-spinner" aria-hidden="true"></span>
            </button>
            <div class="lws-feedback" role="alert" hidden></div>
        </span>
        <script>
        (function() {
            var wrapper = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!wrapper) return;
            
            var button = wrapper.querySelector('.js-lws-login');
            if (!button) return;
            
            // Find if this button is inside a navigation link
            var navLink = wrapper.closest('.wp-block-navigation-item__content, .menu-item a');
            if (navLink) {
                // Prevent the link from navigating
                navLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Trigger button click manually
                    if (button && !button.disabled) {
                        button.click();
                    }
                    return false;
                });
            }
            
            // Ensure the button gets initialized by front.js
            function ensureButtonReady() {
                if (typeof window.lwsInstallButtons === 'function') {
                    window.lwsInstallButtons();
                } else {
                    // Wait for scripts to load
                    var checkCount = 0;
                    var checkInterval = setInterval(function() {
                        if (typeof window.lwsInstallButtons === 'function') {
                            clearInterval(checkInterval);
                            window.lwsInstallButtons();
                        } else if (++checkCount > 50) {
                            clearInterval(checkInterval);
                        }
                    }, 100);
                }
            }
            
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', ensureButtonReady);
            } else {
                ensureButtonReady();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function get_provider_icon($provider) {
        $icons = array(
            'azure' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23"><path fill="#f3f3f3" d="M0 0h23v23H0z"/><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>',
            'google' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>',
            'github' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.17 6.839 9.49.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.463-1.11-1.463-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.578 9.578 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.167 22 16.418 22 12c0-5.523-4.477-10-10-10z"/></svg>',
            'gitlab' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21.42l3.684-11.333H8.316L12 21.42z" fill="#E24329"/><path d="M12 21.42l-3.684-11.333H2.05L12 21.42z" fill="#FC6D26"/><path d="M2.05 10.087L.762 13.88a.83.83 0 00.301.928L12 21.42 2.05 10.087z" fill="#FCA326"/><path d="M2.05 10.087h6.266L5.948 2.313c-.16-.49-.85-.49-1.01 0L2.05 10.087z" fill="#E24329"/><path d="M12 21.42l3.684-11.333h6.266L12 21.42z" fill="#FC6D26"/><path d="M21.95 10.087l1.288 3.793a.83.83 0 01-.301.928L12 21.42l9.95-11.333z" fill="#FCA326"/><path d="M21.95 10.087h-6.266l2.368-7.774c.16-.49.85-.49 1.01 0l2.888 7.774z" fill="#E24329"/></svg>',
            'facebook' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047v-2.66c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.235 2.686.235v2.971h-1.514c-1.491 0-1.955.93-1.955 1.886v2.265h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073z"/></svg>',
            'apple' => '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09l.01-.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>',
        );

        if (isset($icons[$provider])) {
            return $icons[$provider];
        }

        // Fallback icon for unknown providers
        return '<svg class="lws-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
    }

    public function render_login_buttons_on_wp_login() {
        if (is_user_logged_in()) {
            return;
        }

        $this->enqueue_assets();

        $markup = $this->render_login_shortcode(array('wrapper_class' => 'lws-login-wrapper--wp-login'));

        ?>
        <div id="lws-login-wp-template" style="display:none;">
            <?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
        <?php
        // Use wp_add_inline_script for better compatibility
        $inline_script = "(function() {
            var retryCount = 0;
            var maxRetries = 50;
            
            function mountLoginButtons() {
                var template = document.getElementById('lws-login-wp-template');
                if (!template) {
                    return false;
                }

                var target = document.querySelector('#loginform p.submit');
                if (!target) {
                    if (retryCount < maxRetries) {
                        retryCount++;
                        setTimeout(mountLoginButtons, 100);
                        return false;
                    }
                    template.remove();
                    return false;
                }

                var block = template.querySelector('.lws-login-wrapper');
                if (!block) {
                    template.remove();
                    return false;
                }

                target.insertAdjacentElement('afterend', block);
                block.style.removeProperty('display');
                template.remove();

                // Wait for scripts to be ready
                var installRetries = 0;
                var maxInstallRetries = 50;
                
                function tryInstall() {
                    if (typeof window.lwsInstallButtons === 'function') {
                        window.lwsInstallButtons();
                        return true;
                    }
                    
                    if (installRetries < maxInstallRetries) {
                        installRetries++;
                        setTimeout(tryInstall, 100);
                    }
                    return false;
                }
                
                if (typeof window.lwsInstallButtons === 'function') {
                    window.lwsInstallButtons();
                } else {
                    var once = function () {
                        document.removeEventListener('lws-scripts-ready', once);
                        tryInstall();
                    };
                    document.addEventListener('lws-scripts-ready', once);
                    tryInstall();
                }
                
                return true;
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', mountLoginButtons);
            } else {
                setTimeout(mountLoginButtons, 10);
            }
        })();";
        
        wp_add_inline_script($this->script_handle, $inline_script, 'after');
        
        $this->render_login_asset_bootstrap();
    }

    public function render_login_buttons_on_sensei_login() {
        if (is_user_logged_in()) {
            return;
        }

        $this->enqueue_assets();

        $markup = $this->render_login_shortcode(array(
            'wrapper_class' => 'lws-login-wrapper--sensei-login',
            'layout' => 'vertical'
        ));

        echo '<div class="lws-sensei-login-buttons" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        $this->render_login_asset_bootstrap();
    }

    public function render_login_buttons_on_sensei_register() {
        if (is_user_logged_in()) {
            return;
        }

        $this->enqueue_assets();

        $markup = $this->render_login_shortcode(array(
            'wrapper_class' => 'lws-login-wrapper--sensei-register',
            'layout' => 'vertical'
        ));

        echo '<div class="lws-sensei-register-buttons" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
        echo '<p style="text-align: center; color: #666; margin-bottom: 15px;">' . esc_html__('Or sign up with:', 'login-with-supabase') . '</p>';
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';

        $this->render_login_asset_bootstrap();
    }

    public function render_login_buttons_on_woocommerce_login() {
        if (is_user_logged_in()) {
            return;
        }

        $this->enqueue_assets();

        $markup = $this->render_login_shortcode(array(
            'wrapper_class' => 'lws-login-wrapper--woocommerce-login',
            'layout' => 'vertical'
        ));

        echo '<div class="lws-woocommerce-login-buttons" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        
        $options = LWS_Admin::get_options();
        $providers = LWS_Admin::get_enabled_providers();
        $payload = $this->get_frontend_payload($options, $providers);
        $version = $this->get_asset_version();
        
        // Inline script loader and initializer
        ?>
        <script>
        (function() {
            if (!window.lwsAuth) {
                window.lwsAuth = <?php echo wp_json_encode($payload); ?>;
            }
            
            function loadScript(src, callback) {
                var scriptName = src.split('/').pop().split('?')[0];
                var existing = document.querySelector('script[src*="' + scriptName + '"]');
                
                if (scriptName === 'front.js' && existing) {
                    if (window.lwsInstallButtons && window.lwsInstallButtons.toString().indexOf('stub') === -1) {
                        if (callback) setTimeout(callback, 10);
                        return;
                    }
                    existing.parentNode.removeChild(existing);
                    existing = null;
                }
                
                if (existing && scriptName !== 'front.js') {
                    if (callback) setTimeout(callback, 10);
                    return;
                }
                
                var script = document.createElement('script');
                script.src = src;
                script.onload = function() {
                    if (callback) callback();
                };
                document.head.appendChild(script);
            }
            
            function initButtons() {
                if (typeof window.lwsInstallButtons === 'function') {
                    window.lwsInstallButtons();
                } else {
                    var retries = 0;
                    var interval = setInterval(function() {
                        if (typeof window.lwsInstallButtons === 'function') {
                            clearInterval(interval);
                            window.lwsInstallButtons();
                        } else if (++retries > 50) {
                            clearInterval(interval);
                        }
                    }, 100);
                }
            }
            
            if (typeof window.supabase === 'undefined') {
                loadScript('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js', function() {
                    loadScript('<?php echo esc_url(LWS_URL . 'assets/js/front.js?ver=' . $version); ?>', initButtons);
                });
            } else if (typeof window.lwsInstallButtons === 'undefined') {
                loadScript('<?php echo esc_url(LWS_URL . 'assets/js/front.js?ver=' . $version); ?>', initButtons);
            } else {
                initButtons();
            }
        })();
        </script>
        <?php
    }

    public function render_login_buttons_on_woocommerce_register() {
        if (is_user_logged_in()) {
            return;
        }

        $this->enqueue_assets();

        $markup = $this->render_login_shortcode(array(
            'wrapper_class' => 'lws-login-wrapper--woocommerce-register',
            'layout' => 'vertical'
        ));

        echo '<div class="lws-woocommerce-register-buttons" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">';
        echo '<p style="text-align: center; color: #666; margin-bottom: 15px;">' . esc_html__('Or sign up with:', 'login-with-supabase') . '</p>';
        echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</div>';
        
        $options = LWS_Admin::get_options();
        $providers = LWS_Admin::get_enabled_providers();
        $payload = $this->get_frontend_payload($options, $providers);
        $version = $this->get_asset_version();
        
        // Inline script loader and initializer
        ?>
        <script>
        (function() {
            if (!window.lwsAuth) {
                window.lwsAuth = <?php echo wp_json_encode($payload); ?>;
            }
            
            function loadScript(src, callback) {
                var scriptName = src.split('/').pop().split('?')[0];
                var existing = document.querySelector('script[src*="' + scriptName + '"]');
                
                if (scriptName === 'front.js' && existing) {
                    if (window.lwsInstallButtons && window.lwsInstallButtons.toString().indexOf('stub') === -1) {
                        if (callback) setTimeout(callback, 10);
                        return;
                    }
                    existing.parentNode.removeChild(existing);
                    existing = null;
                }
                
                if (existing && scriptName !== 'front.js') {
                    if (callback) setTimeout(callback, 10);
                    return;
                }
                
                var script = document.createElement('script');
                script.src = src;
                script.onload = function() {
                    if (callback) callback();
                };
                document.head.appendChild(script);
            }
            
            function initButtons() {
                if (typeof window.lwsInstallButtons === 'function') {
                    window.lwsInstallButtons();
                } else {
                    var retries = 0;
                    var interval = setInterval(function() {
                        if (typeof window.lwsInstallButtons === 'function') {
                            clearInterval(interval);
                            window.lwsInstallButtons();
                        } else if (++retries > 50) {
                            clearInterval(interval);
                        }
                    }, 100);
                }
            }
            
            if (typeof window.supabase === 'undefined') {
                loadScript('https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js', function() {
                    loadScript('<?php echo esc_url(LWS_URL . 'assets/js/front.js?ver=' . $version); ?>', initButtons);
                });
            } else if (typeof window.lwsInstallButtons === 'undefined') {
                loadScript('<?php echo esc_url(LWS_URL . 'assets/js/front.js?ver=' . $version); ?>', initButtons);
            } else {
                initButtons();
            }
        })();
        </script>
        <?php
    }

    public function render_debug_info() {
        $options = LWS_Admin::get_options();
        $providers = LWS_Admin::get_enabled_providers();
        
        ob_start();
        ?>
        <div style="background: #f5f5f5; border: 2px solid #333; padding: 20px; margin: 20px 0; font-family: monospace;">
            <h3 style="margin-top: 0;">Login with Supabase - Debug Info</h3>
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Logged In:</td>
                    <td style="padding: 8px;"><?php echo is_user_logged_in() ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Supabase URL:</td>
                    <td style="padding: 8px;"><?php echo !empty($options['supabase_url']) ? '✅ Set' : '❌ Not Set'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Supabase Anon Key:</td>
                    <td style="padding: 8px;"><?php echo !empty($options['supabase_anon_key']) ? '✅ Set' : '❌ Not Set'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Enabled Providers:</td>
                    <td style="padding: 8px;"><?php echo !empty($providers) ? implode(', ', $providers) : '❌ None'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Show on wp-login.php:</td>
                    <td style="padding: 8px;"><?php echo $options['show_on_wp_login'] ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Show on Sensei forms:</td>
                    <td style="padding: 8px;"><?php echo $options['show_on_sensei_forms'] ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Show on WooCommerce forms:</td>
                    <td style="padding: 8px;"><?php echo $options['show_on_woocommerce_forms'] ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Sensei LMS Active:</td>
                    <td style="padding: 8px;"><?php echo class_exists('Sensei_Main') ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">WooCommerce Active:</td>
                    <td style="padding: 8px;"><?php echo class_exists('WooCommerce') ? '✅ Yes' : '❌ No'; ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Hooks Registered:</td>
                    <td style="padding: 8px;">
                        <?php 
                        $sensei_login_hook = has_action('sensei_login_form_inside_after', array($this, 'render_login_buttons_on_sensei_login'));
                        $sensei_register_hook = has_action('sensei_register_form_end', array($this, 'render_login_buttons_on_sensei_register'));
                        $wc_login_hook = has_action('woocommerce_login_form_end', array($this, 'render_login_buttons_on_woocommerce_login'));
                        $wc_register_hook = has_action('woocommerce_register_form_end', array($this, 'render_login_buttons_on_woocommerce_register'));
                        ?>
                        <strong>Sensei:</strong><br>
                        Login: <?php echo $sensei_login_hook !== false ? '✅ Yes' : '❌ No'; ?><br>
                        Register: <?php echo $sensei_register_hook !== false ? '✅ Yes' : '❌ No'; ?><br>
                        <strong>WooCommerce:</strong><br>
                        Login: <?php echo $wc_login_hook !== false ? '✅ Yes' : '❌ No'; ?><br>
                        Register: <?php echo $wc_register_hook !== false ? '✅ Yes' : '❌ No'; ?>
                    </td>
                </tr>
                <tr style="border-bottom: 1px solid #ccc;">
                    <td style="padding: 8px; font-weight: bold;">Actual Hooks Used:</td>
                    <td style="padding: 8px;">
                        <small>
                        Login: <code>sensei_login_form_inside_after</code><br>
                        Register: <code>sensei_register_form_end</code>
                        </small>
                    </td>
                </tr>
            </table>
            <p style="margin-bottom: 0;"><small>Use shortcode: [lws_debug] to display this info</small></p>
        </div>
        <?php
        return ob_get_clean();
    }

    public function handle_session_login($request) {
        $access_token = sanitize_text_field($request->get_param('access_token'));
        if (empty($access_token)) {
            return new WP_Error('lws_missing_token', __('Missing access token.', 'login-with-supabase'), array('status' => 400));
        }

        $options = LWS_Admin::get_options();
        $supabase_url = isset($options['supabase_url']) ? $options['supabase_url'] : '';
        $anon_key = isset($options['supabase_anon_key']) ? $options['supabase_anon_key'] : '';

        if (!$supabase_url || !$anon_key) {
            return new WP_Error('lws_missing_config', __('Supabase credentials are not configured.', 'login-with-supabase'), array('status' => 500));
        }

        $user_data = $this->fetch_supabase_user($supabase_url, $anon_key, $access_token);
        if (is_wp_error($user_data)) {
            return $user_data;
        }

        $email = isset($user_data['email']) ? sanitize_email($user_data['email']) : '';
        if (!$email) {
            return new WP_Error('lws_missing_email', __('No email address returned from Supabase.', 'login-with-supabase'), array('status' => 403));
        }

        $wp_user_id = $this->sync_user($email, $user_data);
        if (is_wp_error($wp_user_id)) {
            return $wp_user_id;
        }

        wp_set_current_user($wp_user_id);
        wp_set_auth_cookie($wp_user_id, true);

        do_action('lws_user_authenticated', $wp_user_id, $user_data);

        return array(
            'success' => true,
            'redirect' => !empty($options['redirect_url']) ? $options['redirect_url'] : LWS_DEFAULT_REDIRECT,
        );
    }

    private function fetch_supabase_user($supabase_url, $anon_key, $access_token) {
        $endpoint = trailingslashit($supabase_url) . 'auth/v1/user';

        $response = wp_remote_get(
            $endpoint,
            array(
                'headers' => array(
                    'apikey' => $anon_key,
                    'Authorization' => 'Bearer ' . $access_token,
                    'Accept' => 'application/json',
                ),
                'timeout' => 15,
            )
        );

        if (is_wp_error($response)) {
            return new WP_Error('lws_request_failed', __('Could not reach Supabase.', 'login-with-supabase'), array('status' => 500));
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if (200 !== $status_code) {
            return new WP_Error('lws_invalid_token', __('Invalid Supabase access token.', 'login-with-supabase'), array(
                'status' => 403,
                'data' => array('response' => $body),
            ));
        }

        $user = json_decode($body, true);

        if (empty($user) || !is_array($user)) {
            return new WP_Error('lws_invalid_response', __('Unexpected response from Supabase.', 'login-with-supabase'), array('status' => 500));
        }

        return $user;
    }

    private function sync_user($email, $supabase_user) {
        $user = get_user_by('email', $email);

        if ($user) {
            update_user_meta($user->ID, 'lws_supabase_uuid', $supabase_user['id']);
            update_user_meta($user->ID, 'lws_supabase_provider', $this->resolve_provider($supabase_user));

            $display_name = $this->extract_display_name($supabase_user);
            $name_parts = $this->extract_name_parts($supabase_user, $display_name ? explode(' ', $display_name) : array());

            $update_args = array('ID' => $user->ID);

            if ($display_name && $display_name !== $user->display_name) {
                $update_args['display_name'] = $display_name;
                $update_args['nickname'] = $display_name;
            }

            if (!empty($name_parts['first_name']) && $name_parts['first_name'] !== get_user_meta($user->ID, 'first_name', true)) {
                $update_args['first_name'] = $name_parts['first_name'];
            }

            if (!empty($name_parts['last_name']) && $name_parts['last_name'] !== get_user_meta($user->ID, 'last_name', true)) {
                $update_args['last_name'] = $name_parts['last_name'];
            }

            if (count($update_args) > 1) {
                wp_update_user($update_args);
            }

            return $user->ID;
        }

        $username_base = sanitize_user(current(explode('@', $email)), true);

        if (!$username_base) {
            $username_base = 'supabase_' . wp_generate_password(6, false);
        }

        $username = $username_base;
        $counter = 1;
        while (username_exists($username)) {
            $username = $username_base . '_' . $counter;
            $counter++;
        }

        $password = wp_generate_password(32, true, true);
        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            return new WP_Error('lws_user_creation_failed', __('Failed to create WordPress user.', 'login-with-supabase'), array('status' => 500));
        }

        $display_name = $this->extract_display_name($supabase_user);
        $name_parts = $this->extract_name_parts($supabase_user, $display_name ? explode(' ', $display_name) : array());

        $update_args = array('ID' => $user_id);
        if ($display_name) {
            $update_args['display_name'] = $display_name;
            $update_args['nickname'] = $display_name;
        }
        if (!empty($name_parts['first_name'])) {
            $update_args['first_name'] = $name_parts['first_name'];
        }
        if (!empty($name_parts['last_name'])) {
            $update_args['last_name'] = $name_parts['last_name'];
        }

        if (count($update_args) > 1) { // ID plus at least one more field
            wp_update_user($update_args);
        }

        update_user_meta($user_id, 'lws_supabase_uuid', $supabase_user['id']);
        update_user_meta($user_id, 'lws_supabase_provider', $this->resolve_provider($supabase_user));

        do_action('lws_new_user_created', $user_id, $supabase_user);

        return $user_id;
    }

    private function extract_display_name($supabase_user) {
        if (!empty($supabase_user['user_metadata']['full_name'])) {
            return sanitize_text_field($supabase_user['user_metadata']['full_name']);
        }

        if (!empty($supabase_user['user_metadata']['name'])) {
            return sanitize_text_field($supabase_user['user_metadata']['name']);
        }

        if (!empty($supabase_user['user_metadata']['preferred_username'])) {
            return sanitize_text_field($supabase_user['user_metadata']['preferred_username']);
        }

        $identity_data = $this->get_identity_data($supabase_user);

        $identity_display_keys = array('displayName', 'display_name', 'name', 'full_name');
        foreach ($identity_display_keys as $key) {
            if (!empty($identity_data[$key])) {
                return sanitize_text_field($identity_data[$key]);
            }
        }

        if (!empty($identity_data['given_name']) && !empty($identity_data['family_name'])) {
            return sanitize_text_field($identity_data['given_name'] . ' ' . $identity_data['family_name']);
        }

        if (!empty($identity_data['givenName']) && !empty($identity_data['surname'])) {
            return sanitize_text_field($identity_data['givenName'] . ' ' . $identity_data['surname']);
        }

        return '';
    }

    private function extract_name_parts($supabase_user, $fallback_parts = array()) {
        $first = '';
        $last = '';

        if (!empty($supabase_user['user_metadata']['first_name'])) {
            $first = sanitize_text_field($supabase_user['user_metadata']['first_name']);
        }

        if (!empty($supabase_user['user_metadata']['last_name'])) {
            $last = sanitize_text_field($supabase_user['user_metadata']['last_name']);
        }

        if (!$first && !$last && !empty($supabase_user['user_metadata']['full_name'])) {
            $parts = preg_split('/\s+/', trim($supabase_user['user_metadata']['full_name']));
            if ($parts) {
                $first = sanitize_text_field(array_shift($parts));
                $last = sanitize_text_field(implode(' ', $parts));
            }
        }

        if (!$first && !$last && !empty($fallback_parts)) {
            $first = sanitize_text_field(array_shift($fallback_parts));
            $last = sanitize_text_field(implode(' ', $fallback_parts));
        }

        if (!$first && !empty($supabase_user['user_metadata']['given_name'])) {
            $first = sanitize_text_field($supabase_user['user_metadata']['given_name']);
        }

        if (!$last && !empty($supabase_user['user_metadata']['family_name'])) {
            $last = sanitize_text_field($supabase_user['user_metadata']['family_name']);
        }

        $identity_data = $this->get_identity_data($supabase_user);

        $first_keys = array('given_name', 'givenName', 'first_name', 'firstName');
        foreach ($first_keys as $key) {
            if (!$first && !empty($identity_data[$key])) {
                $first = sanitize_text_field($identity_data[$key]);
                break;
            }
        }

        $last_keys = array('family_name', 'familyName', 'surname', 'last_name', 'lastName');
        foreach ($last_keys as $key) {
            if (!$last && !empty($identity_data[$key])) {
                $last = sanitize_text_field($identity_data[$key]);
                break;
            }
        }

        return array(
            'first_name' => $first,
            'last_name' => $last,
        );
    }

    private function get_identity_data($supabase_user) {
        $data = array();

        if (empty($supabase_user['identities']) || !is_array($supabase_user['identities'])) {
            return $data;
        }

        foreach ($supabase_user['identities'] as $identity) {
            if (empty($identity['identity_data']) || !is_array($identity['identity_data'])) {
                continue;
            }

            foreach ($identity['identity_data'] as $key => $value) {
                if (is_string($value) && $value !== '') {
                    $data[$key] = $value;
                }
            }
        }

        return $data;
    }

    private function resolve_provider($supabase_user) {
        if (!empty($supabase_user['app_metadata']['provider'])) {
            return sanitize_key(strtolower($supabase_user['app_metadata']['provider']));
        }

        if (!empty($supabase_user['identities'][0]['provider'])) {
            return sanitize_key(strtolower($supabase_user['identities'][0]['provider']));
        }

        return 'unknown';
    }

    private function pretty_provider_label($provider) {
        $provider = str_replace(array('_', '-'), ' ', $provider);
        $provider = ucwords($provider);
        return sprintf(/* translators: %s: provider name */ __('Login with %s', 'login-with-supabase'), $provider);
    }

    private function get_asset_version() {
        if (null !== $this->asset_version) {
            return $this->asset_version;
        }

        $this->asset_version = (defined('WP_DEBUG') && WP_DEBUG) ? time() : LWS_VERSION;

        return $this->asset_version;
    }

    private function get_frontend_payload($options, $providers) {
        if (null !== $this->frontend_payload) {
            return $this->frontend_payload;
        }

        $provider_payload = array();
        foreach ($providers as $provider) {
            $provider_payload[] = array(
                'slug' => $provider,
                'label' => $this->pretty_provider_label($provider),
            );
        }

        $this->frontend_payload = array(
            'supabaseUrl' => $options['supabase_url'],
            'supabaseAnonKey' => $options['supabase_anon_key'],
            'restUrl' => esc_url_raw(rest_url('login-with-supabase/v1/session')),
            'nonce' => wp_create_nonce('wp_rest'),
            'redirectUrl' => $options['redirect_url'],
            'isLoggedIn' => is_user_logged_in() ? 1 : 0,
            'debugMode' => (int) $options['enable_debug_mode'],
            'providers' => $provider_payload,
            'labels' => array(
                'working' => __('Completing sign-in…', 'login-with-supabase'),
                'error' => __('Authentication failed. Please try again.', 'login-with-supabase'),
                'genericButton' => __('Continue with Supabase', 'login-with-supabase'),
            ),
        );

        return $this->frontend_payload;
    }

    private function render_login_asset_bootstrap() {
        if ($this->fallback_printed) {
            return;
        }

        $options = LWS_Admin::get_options();
        $providers = LWS_Admin::get_enabled_providers();

        if (empty($options['supabase_url']) || empty($options['supabase_anon_key']) || empty($providers)) {
            return;
        }

        $payload = $this->get_frontend_payload($options, $providers);
        $version = $this->get_asset_version();
        $frontend_src = add_query_arg(array('ver' => $version), LWS_URL . 'assets/js/front.js');
        $style_href = add_query_arg(array('ver' => $version), LWS_URL . 'assets/css/front.css');
        $supabase_src = 'https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.42.3/dist/umd/supabase.min.js';

        $this->fallback_printed = true;
        ?>
        <script>
        (function() {
            // Ensure lwsAuth is available for fallback scenarios
            if (!window.lwsAuth) {
                window.lwsAuth = <?php echo wp_json_encode($payload); ?>;
            }

            // Fallback loader - only runs if normal enqueue fails
            var checkInterval = setInterval(function() {
                // If scripts loaded normally, clear interval
                if (typeof window.supabase !== 'undefined' && document.querySelector('.js-lws-login[data-lws-bound="1"]')) {
                    clearInterval(checkInterval);
                    return;
                }
                
                // After 2 seconds, try fallback loading
                clearInterval(checkInterval);
                
                var head = document.head || document.getElementsByTagName('head')[0];
                
                // Check and inject stylesheet
                if (head) {
                    var styleBase = '<?php echo esc_js(LWS_URL . 'assets/css/front.css'); ?>';
                    if (!document.querySelector('link[href*="' + styleBase + '"]')) {
                        var styleTag = document.createElement('link');
                        styleTag.rel = 'stylesheet';
                        styleTag.href = '<?php echo esc_url($style_href); ?>';
                        head.appendChild(styleTag);
                    }
                }

                function injectScript(src, callback) {
                    var base = src.split('?')[0];
                    var existing = document.querySelector('script[src*="' + base.split('/').pop() + '"]');
                    if (existing) {
                        if (callback) {
                            if (src.indexOf('supabase') > -1 && typeof window.supabase !== 'undefined') {
                                setTimeout(callback, 10);
                            } else if (existing.readyState === 'complete' || existing.readyState === 'loaded') {
                                setTimeout(callback, 10);
                            } else {
                                existing.addEventListener('load', callback);
                                existing.addEventListener('error', callback);
                            }
                        }
                        return existing;
                    }
                    var el = document.createElement('script');
                    el.src = src;
                    el.type = 'text/javascript';
                    if (callback) {
                        el.addEventListener('load', callback);
                        el.addEventListener('error', function() {
                            console.error('LWS: Failed to load script:', src);
                        });
                    }
                    (document.head || document.documentElement).appendChild(el);
                    return el;
                }

                function ensureFrontend() {
                    if (typeof window.supabase === 'undefined') {
                        console.error('LWS: Supabase library not loaded');
                        return;
                    }
                    injectScript('<?php echo esc_url($frontend_src); ?>');
                }

                if (typeof window.supabase === 'undefined') {
                    injectScript('<?php echo esc_url($supabase_src); ?>', ensureFrontend);
                } else {
                    ensureFrontend();
                }
            }, 2000);
        })();
        </script>
        <?php
    }

    public function enable_shortcodes_in_menu_items($item_output, $item, $depth, $args) {
        // Process shortcodes in menu item title and description
        return do_shortcode($item_output);
    }

    public function process_shortcodes_in_navigation_block($block_content, $block) {
        // Process shortcodes in navigation blocks (FSE/Site Editor)
        if ($block['blockName'] === 'core/navigation-link' || 
            $block['blockName'] === 'core/navigation' ||
            $block['blockName'] === 'core/navigation-submenu') {
            return do_shortcode($block_content);
        }
        return $block_content;
    }

    public function add_nav_menu_meta_boxes() {
        add_meta_box(
            'lws-nav-menu',
            __('Login with Supabase', 'login-with-supabase'),
            array($this, 'render_nav_menu_meta_box'),
            'nav-menus',
            'side',
            'default'
        );
    }

    public function render_nav_menu_meta_box() {
        global $nav_menu_selected_id;
        $providers = LWS_Admin::get_enabled_providers();
        
        if (empty($providers)) {
            echo '<p>' . esc_html__('No providers are enabled. Please configure providers in the plugin settings.', 'login-with-supabase') . '</p>';
            return;
        }
        ?>
        <div id="lws-menu-items" class="posttypediv">
            <div id="tabs-panel-lws-login" class="tabs-panel tabs-panel-active">
                <ul id="lws-menu-checklist" class="categorychecklist form-no-clear">
                    <?php foreach ($providers as $provider) : 
                        $provider_label = $this->pretty_provider_label($provider);
                    ?>
                        <li>
                            <label class="menu-item-title">
                                <input type="checkbox" class="menu-item-checkbox" name="menu-item[<?php echo esc_attr($provider); ?>][menu-item-object-id]" value="-1" data-provider="<?php echo esc_attr($provider); ?>" />
                                <?php echo esc_html($provider_label); ?> <?php esc_html_e('(Icon)', 'login-with-supabase'); ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <p class="button-controls">
                <span class="add-to-menu">
                    <button type="button" class="button submit-add-to-menu right" id="lws-submit-menu-item" name="add-lws-menu-item">
                        <?php esc_html_e('Add to Menu', 'login-with-supabase'); ?>
                    </button>
                    <span class="spinner"></span>
                </span>
            </p>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#lws-submit-menu-item').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var spinner = button.next('.spinner');
                
                var selectedProviders = [];
                $('#lws-menu-checklist input:checked').each(function() {
                    selectedProviders.push($(this).data('provider'));
                });
                
                if (selectedProviders.length === 0) {
                    return;
                }
                
                spinner.addClass('is-active');
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'lws_add_menu_item',
                    'menu-settings-column-nonce': $('#menu-settings-column-nonce').val(),
                    menu: <?php echo (int) $nav_menu_selected_id; ?>,
                    providers: selectedProviders
                }, function(response) {
                    if (response.success) {
                        // Add items to the menu
                        $.each(response.data.items, function(index, item) {
                            var $menuList = $('#menu-to-edit');
                            $menuList.append(item);
                        });
                        
                        // Trigger WordPress menu update
                        if (typeof wpNavMenu !== 'undefined') {
                            wpNavMenu.refreshAdvancedAccessibilityOfItem();
                        }
                        
                        // Uncheck all checkboxes
                        $('#lws-menu-checklist input:checked').prop('checked', false);
                    }
                    
                    spinner.removeClass('is-active');
                    button.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    public function ajax_add_menu_item() {
        check_ajax_referer('add-menu_item', 'menu-settings-column-nonce');
        
        if (!current_user_can('edit_theme_options')) {
            wp_send_json_error();
        }
        
        $menu_id = isset($_POST['menu']) ? (int) $_POST['menu'] : 0;
        $providers = isset($_POST['providers']) ? (array) $_POST['providers'] : array();
        
        if (!$menu_id || empty($providers)) {
            wp_send_json_error();
        }
        
        $items_markup = array();
        
        foreach ($providers as $provider) {
            $provider = sanitize_key($provider);
            $enabled_providers = LWS_Admin::get_enabled_providers();
            
            if (!in_array($provider, $enabled_providers, true)) {
                continue;
            }
            
            $shortcode = '[lws provider="' . $provider . '"]';
            $provider_label = $this->pretty_provider_label($provider);
            
            $menu_item_db_id = wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title' => $shortcode,
                'menu-item-url' => '#lws-' . $provider,
                'menu-item-status' => 'publish',
                'menu-item-type' => 'custom',
                'menu-item-description' => $provider_label . ' ' . __('Login Icon', 'login-with-supabase'),
            ));
            
            if (!is_wp_error($menu_item_db_id)) {
                // Get the menu item for display
                $menu_item = wp_setup_nav_menu_item(get_post($menu_item_db_id));
                ob_start();
                require ABSPATH . 'wp-admin/includes/nav-menu.php';
                walk_nav_menu_tree(
                    array($menu_item),
                    0,
                    (object) array('walker' => new Walker_Nav_Menu_Edit())
                );
                $items_markup[] = ob_get_clean();
            }
        }
        
        wp_send_json_success(array('items' => $items_markup));
    }
}
