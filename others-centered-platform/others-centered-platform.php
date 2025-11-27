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
 * COMPOSER AUTOLOAD (optional) + FALLBACK AUTOLOADER
 * ---------------------------------------------------------
 */

// Try Composer autoload if present (e.g. in local dev)
$autoload = OCP_PLUGIN_DIR . 'vendor/autoload.php';

if ( file_exists( $autoload ) ) {
    require_once $autoload;
} else {
    // Show a notice in admin so you know Composer isn't installed,
    // but DO NOT bail out; we'll use our own autoloader instead.
    add_action( 'admin_notices', function () {
        if ( current_user_can( 'activate_plugins' ) ) {
            echo '<div class="notice notice-warning"><p>';
            echo esc_html__(
                'Others Centered Platform: Composer autoloader not found. Using built-in autoloader instead.',
                'others-centered-platform'
            );
            echo '</p></div>';
        }
    } );
}

/**
 * Simple PSR-4-style autoloader for the OthersCentered\Platform namespace.
 */
spl_autoload_register( function ( $class ) {

    $prefix   = 'OthersCentered\\Platform\\';
    $base_dir = OCP_PLUGIN_DIR . 'src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        // Not one of ours
        return;
    }

    $relative_class = substr( $class, $len );

    // Convert namespace separators to directory separators
    $relative_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class ) . '.php';

    $file = $base_dir . $relative_path;

    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

/**
 * ---------------------------------------------------------
 * BOOTSTRAP PLUGIN
 * ---------------------------------------------------------
 */
add_action( 'plugins_loaded', function () {
    if ( class_exists( '\OthersCentered\Platform\Plugin' ) ) {
        \OthersCentered\Platform\Plugin::init();
    }
} );

/**
 * ---------------------------------------------------------
 * ACTIVATION
 * Only register CPTs + flush rewrites
 * ---------------------------------------------------------
 */
register_activation_hook( __FILE__, function () {
    if ( class_exists( '\OthersCentered\Platform\PostTypes\NeedPostType' ) ) {
        \OthersCentered\Platform\PostTypes\NeedPostType::register();
    }
    flush_rewrite_rules();
} );

/**
 * ---------------------------------------------------------
 * DEACTIVATION
 * ---------------------------------------------------------
 */
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
