<?php
/**
 * Plugin Name: Login with Supabase
 * Description: Authenticate WordPress users through any Supabase OAuth provider and sync them into WordPress.
 * Version: 1.0.0
 * Author: Zhe Xu
 * License: MIT
 * Text Domain: login-with-supabase
 */

if (!defined('ABSPATH')) {
    exit;
}

define('LWS_VERSION', '1.0.0');
define('LWS_PATH', plugin_dir_path(__FILE__));
define('LWS_URL', plugin_dir_url(__FILE__));
define('LWS_SLUG', 'login-with-supabase');
define('LWS_OPTION_GROUP', 'lws_settings');
define('LWS_OPTION_NAME', 'lws_options');
define('LWS_DEFAULT_REDIRECT', home_url('/'));

require_once LWS_PATH . 'includes/class-lws-admin.php';
require_once LWS_PATH . 'includes/class-lws-frontend.php';

final class Login_With_Supabase_Plugin {
    public function init() {
        $admin = new LWS_Admin();
        $admin->init();

        $frontend = new LWS_Frontend();
        $frontend->init();
    }
}
 
function lws_run() {
    static $plugin = null;

    if (null === $plugin) {
        $plugin = new Login_With_Supabase_Plugin();
        $plugin->init();
    }

    return $plugin;
}

lws_run();