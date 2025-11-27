<?php

namespace OthersCentered\Platform\Admin;

class Settings
{
    public static function register_menu(): void
    {
        add_menu_page(
            'OC Settings',
            'OC Settings',
            'manage_options',
            'oc-settings',
            [self::class, 'render_page'],
            'dashicons-admin-generic',
            81
        );
    }

    public static function register_settings(): void
    {
        register_setting('oc_settings_group', 'oc_google_maps_api_key');

        add_settings_section(
            'oc_api_section',
            'API Keys',
            '__return_false',
            'oc-settings'
        );

        add_settings_field(
            'oc_google_maps_api_key',
            'Google Maps API Key',
            [self::class, 'api_key_field'],
            'oc-settings',
            'oc_api_section'
        );
    }

    public static function api_key_field(): void
    {
        $value = esc_attr(get_option('oc_google_maps_api_key', ''));
        echo '<input type="text" name="oc_google_maps_api_key" value="' . $value . '" class="regular-text">';
    }

    public static function render_page(): void
    {
        ?>
        <div class="wrap">
            <h1>Others Centered Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('oc_settings_group');
                do_settings_sections('oc-settings');
                submit_button('Save Settings');
                ?>
            </form>
        </div>
        <?php
    }
}
