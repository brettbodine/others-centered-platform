<?php
/**
 * Plugin Name: Others Centered Platform
 * Description: Core functionality for the Others Centered platform.
 * Version: 1.0.0
 * Author: Others Centered
 */

namespace OthersCentered\Platform;

// Core modules
use OthersCentered\Platform\PostTypes\NeedPostType;
use OthersCentered\Platform\Queries\NeedsGridQuery;
use OthersCentered\Platform\Shortcodes\Shortcodes;
use OthersCentered\Platform\Forms\Forms;

// Dashboard
use OthersCentered\Platform\Dashboard\MyNeeds;
use OthersCentered\Platform\Dashboard\CloseNeed;
use OthersCentered\Platform\Dashboard\AccountSettings;

// Admin / backend
use OthersCentered\Platform\Admin\MetaBoxes;
use OthersCentered\Platform\Admin\EmailSettings;
use OthersCentered\Platform\Admin\Backfill;
use OthersCentered\Platform\Admin\Settings; // ⭐ NEW SETTINGS PAGE

// Helpers
use OthersCentered\Platform\Helpers\StatusAutomation;
use OthersCentered\Platform\Helpers\BodyClass;


// -----------------------------------------------------
// Autoloader (Composer or internal autoloaders)
// -----------------------------------------------------
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}


class Plugin
{
    public static function init(): void
    {
        /**
         * -----------------------------------------------------
         * Post Types + Status Automation
         * -----------------------------------------------------
         */
        add_action('init', [NeedPostType::class, 'register']);
        add_action('init', [StatusAutomation::class, 'register']);

        /**
         * -----------------------------------------------------
         * Elementor Query Integration
         * -----------------------------------------------------
         */
        add_action('elementor/query/needs_grid', [NeedsGridQuery::class, 'modify_query']);

        /**
         * -----------------------------------------------------
         * Gravity Forms Integration
         * -----------------------------------------------------
         */
        Forms::register();

        /**
         * -----------------------------------------------------
         * Shortcodes
         * -----------------------------------------------------
         */
        add_action('init', function () {
            Shortcodes::register();
        });

        /**
         * -----------------------------------------------------
         * Dashboard Components
         * -----------------------------------------------------
         */
        add_action('init', function () {
            MyNeeds::register();
            CloseNeed::register();
            AccountSettings::register();
        });

        /**
         * -----------------------------------------------------
         * Frontend Helpers
         * -----------------------------------------------------
         */
        BodyClass::register();

        /**
         * -----------------------------------------------------
         * Admin-only functionality
         * -----------------------------------------------------
         */
        if (is_admin()) {

            // OC Email template manager
            add_action('admin_menu', [EmailSettings::class, 'register_menu']);

            // Meta boxes + backfill migration tools
            add_action('init', function () {
                MetaBoxes::register();
                Backfill::register();
            });

            // ⭐ NEW SETTINGS PAGE — Google Maps API Key
            add_action('admin_menu', [Settings::class, 'register_menu']);
            add_action('admin_init', [Settings::class, 'register_settings']);
        }
    }
}


// Boot the plugin
add_action('plugins_loaded', [Plugin::class, 'init']);
