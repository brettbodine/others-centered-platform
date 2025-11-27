<?php

namespace OthersCentered\Platform\Admin;

use WP_Query;
use OthersCentered\Platform\Geocoding\ZipGeocoder;

class Backfill
{
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'maybe_run_backfill']);
        add_action('admin_notices', [self::class, 'maybe_show_notice']);
    }

    /**
     * Trigger backfill when visiting:
     *   /wp-admin/edit.php?post_type=need&oc_backfill_coords=1
     */
    public static function maybe_run_backfill(): void
    {
        if (!isset($_GET['oc_backfill_coords'])) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        self::run_backfill();

        wp_safe_redirect(admin_url('edit.php?post_type=need&oc_backfill_done=1'));
        exit;
    }

    /**
     * Show admin notice after backfill completes.
     */
    public static function maybe_show_notice(): void
    {
        if (!isset($_GET['oc_backfill_done'])) {
            return;
        }

        echo '<div class="notice notice-success is-dismissible"><p>Need coordinates have been backfilled successfully.</p></div>';
    }

    /**
     * Perform actual geocoding backfill.
     */
    protected static function run_backfill(): void
    {
        $query = new WP_Query([
            'post_type'      => 'need',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        foreach ($query->posts as $post_id) {

            // Correct meta keys
            $zip = get_post_meta($post_id, 'need_zip', true);

            if (empty($zip)) {
                continue;
            }

            // Skip if already geocoded
            if (
                get_post_meta($post_id, 'need_lat', true) &&
                get_post_meta($post_id, 'need_lng', true)
            ) {
                continue;
            }

            $coords = ZipGeocoder::geocode_zip($zip);

            if ($coords && isset($coords['lat'], $coords['lng'])) {
                update_post_meta($post_id, 'need_lat', $coords['lat']);
                update_post_meta($post_id, 'need_lng', $coords['lng']);
            }
        }
    }
}
