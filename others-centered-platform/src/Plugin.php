<?php
/**
 * Plugin Name: Others Centered Platform
 * Description: Core functionality for the Others Centered platform.
 * Version: 1.0.0
 * Author: Others Centered
 */

namespace OthersCentered\Platform;

use OthersCentered\Platform\PostTypes\NeedPostType;
use OthersCentered\Platform\Queries\NeedsGridQuery;
use OthersCentered\Platform\Shortcodes\Shortcodes;
use OthersCentered\Platform\Forms\Forms;
use OthersCentered\Platform\Admin\MetaBoxes;
use OthersCentered\Platform\Admin\EmailSettings;
use OthersCentered\Platform\Admin\Backfill;
use OthersCentered\Platform\Dashboard\MyNeeds;
use OthersCentered\Platform\Dashboard\CloseNeed;
use OthersCentered\Platform\Dashboard\AccountSettings;
use OthersCentered\Platform\Helpers\StatusAutomation;
use OthersCentered\Platform\Helpers\BodyClass;

// Autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class Plugin
{
    public static function init(): void
    {
        /**
         * CPT + status automation
         */
        add_action('init', [NeedPostType::class, 'register']);
        add_action('init', [StatusAutomation::class, 'register']);

        /**
         * Elementor query
         */
        add_action('elementor/query/needs_grid', [NeedsGridQuery::class, 'modify_query']);

        /**
         * Gravity Forms
         */
        add_action('plugins_loaded', function () {
            Forms::register();
        });

        /**
         * Shortcodes
         */
        add_action('init', function () {
            Shortcodes::register();
        });

        /**
         * Dashboard components
         */
        add_action('init', function () {
            MyNeeds::register();
            CloseNeed::register();
            AccountSettings::register();
        });

        /**
         * Helpers
         */
        BodyClass::register();

        /**
         * Admin-only features
         */
        if (is_admin()) {

            // Register admin menus properly
            add_action('admin_menu', [EmailSettings::class, 'register_menu']);

            // Meta boxes + backfill still belong to init
            add_action('init', function () {
                MetaBoxes::register();
                Backfill::register();
            });
        }
    }
}

Plugin::init();
