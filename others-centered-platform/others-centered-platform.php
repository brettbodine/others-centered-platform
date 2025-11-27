<?php
/**
 * Plugin Name: Others Centered Platform
 * Plugin URI:  https://www.otherscentered.com
 * Description: Needs, helpers, geocoding, Gravity Forms, and UX features for the Others Centered platform.
 * Version:     1.0.0
 * Author:      Others Centered
 * Text Domain: others-centered-platform
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ---------------------------------------------------------
 * CONSTANTS
 * ---------------------------------------------------------
 */
define( 'OCP_VERSION', '1.0.0' );
define( 'OCP_PLUGIN_FILE', __FILE__ );
define( 'OCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * ---------------------------------------------------------
 * COMPOSER AUTOLOAD
 * ---------------------------------------------------------
 */
$autoload = OCP_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
} else {
    add_action( 'admin_notices', function () {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Others Centered Platform: Composer autoloader not found. Run "composer install" in the plugin directory.',
                'others-centered-platform'
            );
            echo '</p></div>';
        }
    });
    return;
}

/**
 * ---------------------------------------------------------
 * BOOTSTRAP PLUGIN
 * ---------------------------------------------------------
 */
add_action( 'plugins_loaded', function () {
    \OthersCentered\Platform\Plugin::init();
});

/**
 * ---------------------------------------------------------
 * ACTIVATION
 * Only register CPTs + flush rewrites
 * ---------------------------------------------------------
 */
register_activation_hook( __FILE__, function () {
    \OthersCentered\Platform\PostTypes\NeedPostType::register();
    flush_rewrite_rules();
});

/**
 * ---------------------------------------------------------
 * DEACTIVATION
 * ---------------------------------------------------------
 */
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
});
