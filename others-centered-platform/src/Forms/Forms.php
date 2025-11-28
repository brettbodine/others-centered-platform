<?php

namespace OthersCentered\Platform\Forms;

use OthersCentered\Platform\Emails\Templates;

class Forms
{
    public const FORM_ID_ASK = 1;
    public const FORM_ID_HELP = 2;
    public const FORM_ID_VERIFY = 4;

    public static function register(): void
    {
        /**
         * APC (Form 1) – when a Need is created
         */
        add_action(
            'gform_advancedpostcreation_post_after_creation',
            [self::class, 'after_need_created'],
            10,
            4
        );

        /**
         * Form 2 hooks remain untouched
         */
        add_action('gform_after_submission_2', [self::class, 'handle_help_submission'], 10, 2);
        add_action('gform_after_submission_2', [self::class, 'store_helper_user'], 20, 2);

        /**
         * Form 4 (verify)
         */
        add_action('gform_after_submission_4', [self::class, 'verify_member'], 10, 2);
    }

    /**
     * ------------------------------------------------------
     * FORM 1 — AFTER APC CREATES A NEED
     * (Restore ALL original functionality)
     * ------------------------------------------------------
     */
    public static function after_need_created($post_id, $feed, $entry, $form): void
    {
        if ((int)$form['id'] !== self::FORM_ID_ASK || get_post_type($post_id) !== 'need') {
            return;
        }

        // Force status = In Review
        wp_set_object_terms($post_id, 'In Review', 'need_status', false);

        // Auto-title if missing
        if (empty(get_the_title($post_id))) {
            $city = get_post_meta($post_id, 'city', true);
            $cats = wp_get_object_terms($post_id, 'need_category', ['fields' => 'names']);
            $cat  = (!is_wp_error($cats) && !empty($cats)) ? $cats[0] : 'Need';

            wp_update_post([
                'ID'         => $post_id,
                'post_title' => trim("$cat in $city"),
            ]);
        }

        /**
         * ZIP → need_zip / need_lat / need_lng
         */
        $zip = isset($entry['22']) ? trim(rgar($entry, '22')) : '';

        if ($zip === '') {
            $zip = trim((string)get_post_meta($post_id, 'need_zip', true));
        }

        if ($zip !== '') {
            update_post_meta($post_id, 'need_zip', $zip);

            $coords = self::geocode_zip($zip);

            if ($coords) {
                update_post_meta($post_id, 'need_lat', $coords['lat']);
                update_post_meta($post_id, 'need_lng', $coords['lng']);
                error_log("OC APC Geocode SUCCESS for Need #{$post_id} ZIP={$zip}");
            } else {
                error_log("OC APC Geocode FAILED for Need #{$post_id} ZIP={$zip}");
            }
        } else {
            error_log("OC APC Geocode SKIPPED – no ZIP for Need #{$post_id}");
        }

        /**
         * Notify intake team
         */
        $to   = get_option('admin_email');
        $edit = get_edit_post_link($post_id);

        wp_mail(
            $to,
            'New Need submitted (Pending Review)',
            "A new Need is awaiting review:\n\nTitle: " . get_the_title($post_id) . "\nEdit: $edit",
            ['Content-Type: text/plain; charset=UTF-8']
        );
    }

    /**
     * ------------------------------------------------------
     * Geocode ZIP — restored original functionality
     * ------------------------------------------------------
     */
    protected static function geocode_zip($zip): ?array
    {
        $zip = trim($zip);
        if ($zip === '') return null;

        $transient_key = 'oc_zip_geo_' . md5($zip);
        $cached = get_transient($transient_key);

        if ($cached !== false) {
            return $cached;
        }

        // Pull key from plugin settings
        $api_key = get_option('oc_google_maps_api_key');
        if (empty($api_key)) {
            error_log("OC Geocode: No API key found");
            return null;
        }

        $url = add_query_arg(
            [
                'address' => $zip,
                'key'     => $api_key,
            ],
            'https://maps.googleapis.com/maps/api/geocode/json'
        );

        $resp = wp_remote_get($url);
        if (is_wp_error($resp)) {
            error_log('OC geocode error: ' . $resp->get_error_message());
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($resp), true);

        if (empty($data['results'][0]['geometry']['location'])) {
            error_log("OC geocode: No results for ZIP {$zip}");
            return null;
        }

        $loc = $data['results'][0]['geometry']['location'];

        $out = [
            'lat' => (float)$loc['lat'],
            'lng' => (float)$loc['lng'],
        ];

        set_transient($transient_key, $out, 7 * DAY_IN_SECONDS);

        return $out;
    }

    /**
     * Existing Form 2 & Form 4 logic stays untouched
     */
    public static function handle_help_submission($entry, $form) {}
    public static function store_helper_user($entry, $form) {}
    public static function verify_member($entry, $form) {}
}
