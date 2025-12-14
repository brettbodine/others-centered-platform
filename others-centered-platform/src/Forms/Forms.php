<?php

namespace OthersCentered\Platform\Forms;

use OthersCentered\Platform\Emails\Templates;
use OthersCentered\Platform\Geocoding\ZipGeocoder;
use WP_Post;

class Forms
{
    public const FORM_ID_ASK    = 1;
    public const FORM_ID_HELP   = 2;
    public const FORM_ID_VERIFY = 4;

    public static function register(): void
    {
        /**
         * Form 1 (Ask) – after APC creates the Need post
         */
        add_action(
            'gform_advancedpostcreation_post_after_creation',
            [self::class, 'after_need_created'],
            10,
            4
        );

        /**
         * Re-geocode on ANY Need save (admin, dashboard, lifecycle)
         */
        add_action(
            'save_post_need',
            [self::class, 'ensure_need_coordinates'],
            20,
            3
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
     * ------------------------------------------------------
     */
    public static function after_need_created($post_id, $feed, $entry, $form): void
    {
        if ((int) $form['id'] !== self::FORM_ID_ASK || get_post_type($post_id) !== 'need') {
            return;
        }

        /**
         * Force initial status
         */
        wp_set_object_terms($post_id, 'In Review', 'need_status', false);

        /**
         * Auto-title if missing
         */
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
         * Extract ZIP from entry (label-based, ID-safe)
         */
        $zip = self::extract_zip_from_entry($entry, $form);

        if ($zip) {
            update_post_meta($post_id, 'need_zip', $zip);
            self::set_need_coordinates($post_id, $zip, 'APC');
        } else {
            error_log("OC APC Geocode SKIPPED – no ZIP for Need #{$post_id}");
        }

        /**
         * Notify intake team
         */
        $to   = get_option('admin_email');
        $edit = get_edit_post_link($post_id);

        use OthersCentered\Platform\Emails\Templates;

        Templates::send(
            'admin_new_need',
            get_option('admin_email'),
            [
                '{need_title}' => get_the_title($post_id),
                '{edit_link}'  => get_edit_post_link($post_id),
            ]
        );

    }

    /**
     * ------------------------------------------------------
     * ENSURE COORDINATES ON ANY NEED SAVE
     * ------------------------------------------------------
     */
    public static function ensure_need_coordinates(int $post_id, WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($post_id)) return;
        if ($post->post_status === 'auto-draft') return;

        $zip = get_post_meta($post_id, 'need_zip', true);
        if (!$zip) return;

        $lat = get_post_meta($post_id, 'need_lat', true);
        $lng = get_post_meta($post_id, 'need_lng', true);

        if ($lat && $lng) return;

        self::set_need_coordinates($post_id, $zip, 'SAVE_POST');
    }

    /**
     * ------------------------------------------------------
     * CENTRALIZED COORDINATE SETTER
     * ------------------------------------------------------
     */
    protected static function set_need_coordinates(int $post_id, string $zip, string $context): void
    {
        $coords = ZipGeocoder::geocode_zip($zip);

        if (!$coords) {
            error_log("OC {$context} Geocode FAILED for Need #{$post_id} ZIP={$zip}");
            return;
        }

        if (function_exists('update_field')) {
    update_field('need_lat', $coords['lat'], $post_id);
    update_field('need_lng', $coords['lng'], $post_id);
} else {
    update_post_meta($post_id, 'need_lat', $coords['lat']);
    update_post_meta($post_id, 'need_lng', $coords['lng']);
}

        error_log("OC {$context} Geocode SUCCESS for Need #{$post_id} ZIP={$zip}");
    }

    /**
     * ------------------------------------------------------
     * SAFE ZIP EXTRACTION (FIELD-ID IMMUNE)
     * ------------------------------------------------------
     */
    protected static function extract_zip_from_entry(array $entry, array $form): ?string
    {
        foreach ($form['fields'] as $field) {
            $label = strtolower(trim((string) $field->label));

            if (in_array($label, ['zip', 'zip code', 'postal code'], true)) {
                $value = trim((string) rgar($entry, (string) $field->id));
                return $value ?: null;
            }
        }

        return null;
    }

    /**
     * Existing Form 2 & Form 4 logic stays untouched
     */
    public static function handle_help_submission($entry, $form): void {}
    public static function store_helper_user($entry, $form): void {}
    public static function verify_member($entry, $form): void {}
}
