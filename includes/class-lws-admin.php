<?php

class LWS_Admin {
    public function init() {
        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public static function get_options() {
        $defaults = self::get_default_options();
        $options = get_option(LWS_OPTION_NAME, array());

        if (!is_array($options)) {
            $options = array();
        }

        return wp_parse_args($options, $defaults);
    }

    public static function get_option($key, $default = '') {
        $options = self::get_options();
        return isset($options[$key]) ? $options[$key] : $default;
    }

    public static function get_default_options() {
        return array(
            'supabase_url' => '',
            'supabase_anon_key' => '',
            'supabase_service_role_key' => '',
            'redirect_url' => LWS_DEFAULT_REDIRECT,
            'providers' => "azure\ngoogle",
            'show_on_wp_login' => 1,
            'show_on_sensei_forms' => 0,
            'show_on_woocommerce_forms' => 0,
            'enable_debug_mode' => 0,
        );
    }

    public static function get_enabled_providers() {
        $raw = self::get_option('providers', '');
        $lines = preg_split('/\r?\n/', $raw);
        $providers = array();

        foreach ($lines as $line) {
            $provider = sanitize_key(trim($line));
            if (!empty($provider)) {
                $providers[] = $provider;
            }
        }

        return array_values(array_unique($providers));
    }

    public function register_menu() {
        add_options_page(
            __('Login with Supabase', 'login-with-supabase'),
            __('Login with Supabase', 'login-with-supabase'),
            'manage_options',
            LWS_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(LWS_OPTION_GROUP, LWS_OPTION_NAME, array($this, 'sanitize_options'));

        add_settings_section(
            'lws_section_credentials',
            __('Supabase Credentials', 'login-with-supabase'),
            '__return_null',
            LWS_SLUG
        );

        add_settings_field(
            'supabase_url',
            __('Supabase Project URL', 'login-with-supabase'),
            array($this, 'render_supabase_url_field'),
            LWS_SLUG,
            'lws_section_credentials'
        );

        add_settings_field(
            'supabase_anon_key',
            __('Supabase Anon Key', 'login-with-supabase'),
            array($this, 'render_supabase_anon_field'),
            LWS_SLUG,
            'lws_section_credentials'
        );

        add_settings_field(
            'supabase_service_role_key',
            __('Supabase Service Role Key (optional)', 'login-with-supabase'),
            array($this, 'render_supabase_service_field'),
            LWS_SLUG,
            'lws_section_credentials'
        );

        add_settings_section(
            'lws_section_behavior',
            __('Behavior', 'login-with-supabase'),
            '__return_null',
            LWS_SLUG
        );

        add_settings_field(
            'providers',
            __('Enabled OAuth Providers', 'login-with-supabase'),
            array($this, 'render_providers_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );

        add_settings_field(
            'redirect_url',
            __('Post-login Redirect URL', 'login-with-supabase'),
            array($this, 'render_redirect_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );

        add_settings_field(
            'show_on_wp_login',
            __('Add buttons to wp-login.php', 'login-with-supabase'),
            array($this, 'render_show_on_wp_login_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );

        add_settings_field(
            'show_on_sensei_forms',
            __('Add buttons to Sensei LMS forms', 'login-with-supabase'),
            array($this, 'render_show_on_sensei_forms_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );

        add_settings_field(
            'show_on_woocommerce_forms',
            __('Add buttons to WooCommerce forms', 'login-with-supabase'),
            array($this, 'render_show_on_woocommerce_forms_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );

        add_settings_field(
            'enable_debug_mode',
            __('Enable debug mode', 'login-with-supabase'),
            array($this, 'render_enable_debug_mode_field'),
            LWS_SLUG,
            'lws_section_behavior'
        );
    }

    public function sanitize_options($input) {
        $defaults = self::get_default_options();
        $sanitized = array();

        $sanitized['supabase_url'] = isset($input['supabase_url']) ? esc_url_raw(trim($input['supabase_url'])) : '';
        $sanitized['supabase_anon_key'] = isset($input['supabase_anon_key']) ? sanitize_textarea_field($input['supabase_anon_key']) : '';
        $sanitized['supabase_service_role_key'] = isset($input['supabase_service_role_key']) ? sanitize_textarea_field($input['supabase_service_role_key']) : '';
        $sanitized['redirect_url'] = isset($input['redirect_url']) ? esc_url_raw(trim($input['redirect_url'])) : $defaults['redirect_url'];

        $providers = isset($input['providers']) ? $input['providers'] : '';
        $lines = preg_split('/\r?\n/', $providers);
        $clean_lines = array();
        foreach ($lines as $line) {
            $provider = sanitize_key(trim($line));
            if (!empty($provider)) {
                $clean_lines[] = $provider;
            }
        }
        $sanitized['providers'] = implode("\n", array_unique($clean_lines));

        $sanitized['show_on_wp_login'] = isset($input['show_on_wp_login']) ? 1 : 0;
        $sanitized['show_on_sensei_forms'] = isset($input['show_on_sensei_forms']) ? 1 : 0;
        $sanitized['show_on_woocommerce_forms'] = isset($input['show_on_woocommerce_forms']) ? 1 : 0;
        $sanitized['enable_debug_mode'] = isset($input['enable_debug_mode']) ? 1 : 0;

        return wp_parse_args($sanitized, $defaults);
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Login with Supabase', 'login-with-supabase'); ?></h1>
            <form action="options.php" method="post">
                <?php
                    settings_fields(LWS_OPTION_GROUP);
                    do_settings_sections(LWS_SLUG);
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_supabase_url_field() {
        $options = self::get_options();
        ?>
        <input type="url" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[supabase_url]" value="<?php echo esc_attr($options['supabase_url']); ?>" class="regular-text" placeholder="https://project.supabase.co" />
        <?php
    }

    public function render_supabase_anon_field() {
        $options = self::get_options();
        ?>
        <textarea name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[supabase_anon_key]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Paste your anon public key', 'login-with-supabase'); ?>"><?php echo esc_textarea($options['supabase_anon_key']); ?></textarea>
        <?php
    }

    public function render_supabase_service_field() {
        $options = self::get_options();
        ?>
        <textarea name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[supabase_service_role_key]" rows="3" class="large-text" placeholder="<?php esc_attr_e('Optional: service role key, needed for provider checks.', 'login-with-supabase'); ?>"><?php echo esc_textarea($options['supabase_service_role_key']); ?></textarea>
        <p class="description"><?php esc_html_e('Store securely. Only required if you plan to resolve provider metadata.', 'login-with-supabase'); ?></p>
        <?php
    }

    public function render_providers_field() {
        $options = self::get_options();
        ?>
        <textarea name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[providers]" rows="6" class="large-text" placeholder="azure&#10;google&#10;github"><?php echo esc_textarea($options['providers']); ?></textarea>
        <p class="description"><?php esc_html_e('One provider slug per line (matching Supabase identifiers). Example: azure, google, github, gitlab, facebook, apple.', 'login-with-supabase'); ?></p>
        <?php
    }

    public function render_redirect_field() {
        $options = self::get_options();
        ?>
        <input type="url" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[redirect_url]" value="<?php echo esc_attr($options['redirect_url']); ?>" class="regular-text" placeholder="<?php echo esc_url(home_url('/')); ?>" />
        <p class="description"><?php esc_html_e('Users land here after a successful Supabase sign-in.', 'login-with-supabase'); ?></p>
        <?php
    }

    public function render_show_on_wp_login_field() {
        $options = self::get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[show_on_wp_login]" value="1" <?php checked($options['show_on_wp_login'], 1); ?> />
            <?php esc_html_e('Display Supabase buttons beneath the standard wp-login.php form.', 'login-with-supabase'); ?>
        </label>
        <?php
    }

    public function render_show_on_sensei_forms_field() {
        $options = self::get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[show_on_sensei_forms]" value="1" <?php checked($options['show_on_sensei_forms'], 1); ?> />
            <?php esc_html_e('Display Supabase buttons on Sensei LMS login and register forms.', 'login-with-supabase'); ?>
        </label>
        <p class="description"><?php esc_html_e('Requires Sensei LMS plugin to be active.', 'login-with-supabase'); ?></p>
        <?php
    }

    public function render_show_on_woocommerce_forms_field() {
        $options = self::get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[show_on_woocommerce_forms]" value="1" <?php checked($options['show_on_woocommerce_forms'], 1); ?> />
            <?php esc_html_e('Display Supabase buttons on WooCommerce login and register forms.', 'login-with-supabase'); ?>
        </label>
        <p class="description"><?php esc_html_e('Requires WooCommerce plugin to be active.', 'login-with-supabase'); ?></p>
        <?php
    }

    public function render_enable_debug_mode_field() {
        $options = self::get_options();
        ?>
        <label>
            <input type="checkbox" name="<?php echo esc_attr(LWS_OPTION_NAME); ?>[enable_debug_mode]" value="1" <?php checked($options['enable_debug_mode'], 1); ?> />
            <?php esc_html_e('Show debug messages in browser console.', 'login-with-supabase'); ?>
        </label>
        <p class="description"><?php esc_html_e('Enable this to see detailed logging for troubleshooting. You can also add ?lws_debug=1 to any URL.', 'login-with-supabase'); ?></p>
        <?php
    }
}
