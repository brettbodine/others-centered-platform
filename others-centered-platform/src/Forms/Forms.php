<?php

namespace OthersCentered\Platform\Forms;

use OthersCentered\Platform\Geocoding\ZipGeocoder;
use OthersCentered\Platform\Emails\Templates;
use OthersCentered\Platform\Helpers\UserPreferences; // Safe even if not yet implemented

class Forms
{
    public const FORM_ID_ASK    = 1;
    public const FORM_ID_HELP   = 2;
    public const FORM_ID_VERIFY = 4;

    public static function register(): void
    {
        /**
         * -----------------------------------------------------
         * FORM 1 – Need Creation (APC)
         * Register AFTER Gravity Forms + APC are loaded.
         * -----------------------------------------------------
         */
        add_action('gform_loaded', function () {
            add_action(
                'gform_advancedpostcreation_post_after_creation',
                [self::class, 'after_need_created'],
                10,
                4
            );
        });

        /**
         * -----------------------------------------------------
         * FORM 2 – Help Submission
         * -----------------------------------------------------
         */
        add_action('gform_after_submission_2', [self::class, 'handle_help_submission'], 10, 2);
        add_action('gform_after_submission_2', [self::class, 'store_helper_user'],      20, 2);

        /**
         * -----------------------------------------------------
         * FORM 4 – Verification
         * -----------------------------------------------------
         */
        add_action('gform_after_submission_4', [self::class, 'handle_verification_submission'], 10, 2);

        /**
         * -----------------------------------------------------
         * Dynamic population – need_id
         * -----------------------------------------------------
         */
        add_filter('gform_field_value_need_id', [self::class, 'populate_need_id']);
    }

    /**
     * -----------------------------------------------------
     * FORM 1 – Need Creation (APC)
     * -----------------------------------------------------
     */
    public static function after_need_created($post_id, $feed, $entry, $form): void
    {
        if ((int) $form['id'] !== self::FORM_ID_ASK || get_post_type($post_id) !== 'need') {
            return;
        }

        // Status: In Review
        wp_set_object_terms($post_id, 'In Review', 'need_status', false);

        // Auto-title if missing
        if (empty(get_the_title($post_id))) {
            $city = get_post_meta($post_id, 'city', true);
            $cats = wp_get_object_terms($post_id, 'need_category', ['fields' => 'names']);
            $cat  = (! is_wp_error($cats) && ! empty($cats)) ? $cats[0] : 'Need';

            wp_update_post([
                'ID'         => $post_id,
                'post_title' => trim("$cat in $city"),
            ]);
        }

        // ZIP and coords (GF field 22 → need_zip / need_lat / need_lng)
        $zip = isset($entry['22']) ? trim(rgar($entry, '22')) : '';
        if ($zip === '') {
            $zip = trim((string) get_post_meta($post_id, 'need_zip', true));
        }

        if ($zip !== '') {
            update_post_meta($post_id, 'need_zip', $zip);
            $coords = ZipGeocoder::geocode_zip($zip);

            if ($coords) {
                update_post_meta($post_id, 'need_lat', $coords['lat']);
                update_post_meta($post_id, 'need_lng', $coords['lng']);
            }
        }

        // Intake email to admin (simple)
        $to   = get_option('admin_email');
        $edit = get_edit_post_link($post_id);

        if ($to && is_email($to)) {
            wp_mail(
                $to,
                'New Need submitted (Pending Review)',
                "A new need is awaiting review:\n\nTitle: " . get_the_title($post_id) . "\nEdit: $edit",
                ['Content-Type: text/plain; charset=UTF-8']
            );
        }

        // Assign author
        $user_id = ! empty($entry['created_by']) ? (int) $entry['created_by'] : get_current_user_id();
        if ($user_id) {
            wp_update_post([
                'ID'          => $post_id,
                'post_author' => $user_id,
            ]);
        }
    }

    /**
     * -----------------------------------------------------
     * FORM 2 – Help Submission
     * -----------------------------------------------------
     */
    public static function handle_help_submission($entry, $form): void
    {
        $FID_NEED_ID = 1;
        $FID_NAME    = 3;
        $FID_EMAIL   = 4;
        $FID_COVER   = 5;
        $FID_AMOUNT  = 6;
        $FID_NOTE    = 7;

        $need_id = absint(rgar($entry, (string) $FID_NEED_ID));
        if (! $need_id || get_post_type($need_id) !== 'need') {
            error_log('Form 2: INVALID need_id: ' . $need_id);
            return;
        }

        $amount_given = (float) rgar($entry, (string) $FID_AMOUNT);
        $helper_name  = sanitize_text_field(rgar($entry, (string) $FID_NAME));
        $helper_email = sanitize_email(rgar($entry, (string) $FID_EMAIL));
        $cover_type   = sanitize_text_field(rgar($entry, (string) $FID_COVER));
        $note         = wp_kses_post(rgar($entry, (string) $FID_NOTE));

        update_post_meta($need_id, 'helper_name',  $helper_name);
        update_post_meta($need_id, 'helper_email', $helper_email);
        update_post_meta($need_id, 'cover_type',   $cover_type);
        update_post_meta($need_id, 'helper_note',  $note);

        // Log helper contact
        $log = get_post_meta($need_id, 'helper_contacts', true);
        if (! is_array($log)) {
            $log = [];
        }

        $log[] = [
            'time'         => current_time('mysql'),
            'helper_name'  => $helper_name,
            'helper_email' => $helper_email,
            'cover_type'   => $cover_type,
            'amount_given' => $amount_given,
            'note'         => $note,
        ];

        update_post_meta($need_id, 'helper_contacts', $log);

        // ACF amount_granted
        if (function_exists('update_field')) {
            update_field('field_6917a9968ddea', $amount_given, $need_id);
        } else {
            update_post_meta($need_id, 'amount_granted', $amount_given);
        }

        // Status change logic
        $requested = (float) get_post_meta($need_id, 'amount_requested', true);

        if ($requested > 0 && $amount_given >= $requested) {
            wp_set_object_terms($need_id, 'Met', 'need_status', false);
            update_post_meta($need_id, 'fulfillment_date', current_time('Y-m-d'));
        } else {
            wp_set_object_terms($need_id, 'Matched', 'need_status', false);
        }

        /**
         * -------------------------------------------------
         * EMAILS FOR FORM 2
         * -------------------------------------------------
         */
        $need_title = get_the_title($need_id);
        $need_link  = get_permalink($need_id);

        // Member (need owner)
        $member_email = '';
        $author_id    = (int) get_post_field('post_author', $need_id);

        if ($author_id) {
            $user = get_userdata($author_id);
            if ($user && ! empty($user->user_email)) {
                $member_email = $user->user_email;
            }
        }

        if (! $member_email) {
            $member_email = (string) get_post_meta($need_id, 'member_email', true);
        }

        if ($member_email) {
            Templates::send('matched_member', $member_email, [
                '{need_title}' => $need_title,
                '{need_link}'  => $need_link,
            ]);
        }

        // Helper thanks
        if ($helper_email) {
            $helper_name_optional = $helper_name ? ' ' . $helper_name : '';
            Templates::send('helper_thanks', $helper_email, [
                '{helper_name_optional}' => $helper_name_optional,
                '{need_title}'           => $need_title,
            ]);
        }

        // Admin notice
        $admin_email = get_option('admin_email');
        if ($admin_email && is_email($admin_email)) {
            Templates::send('admin_matched', $admin_email, [
                '{need_id}'    => (string) $need_id,
                '{need_title}' => $need_title,
                '{edit_link}'  => get_edit_post_link($need_id),
            ]);
        }
    }

    public static function store_helper_user($entry, $form): void
    {
        $FID_NEED_ID = 1;

        $need_id = absint(rgar($entry, (string) $FID_NEED_ID));
        if (! $need_id || get_post_type($need_id) !== 'need') {
            return;
        }

        $user_id = get_current_user_id();
        if ($user_id) {
            update_post_meta($need_id, 'helper_user_id', $user_id);
        }
    }

    /**
     * -----------------------------------------------------
     * FORM 4 – Verification
     * -----------------------------------------------------
     */
    public static function handle_verification_submission($entry, $form): void
    {
        $FID_NEED_ID    = 1;
        $FID_AMOUNT     = 6;
        $FID_PROOF_FILE = 3;
        $FID_NOTE       = 4;
        $ACF_PROOF_FILE_FIELD_KEY = 'field_692295b7e7fe2';

        $need_id = absint(rgar($entry, (string) $FID_NEED_ID));
        if (! $need_id || get_post_type($need_id) !== 'need') {
            return;
        }

        $amount   = (float) rgar($entry, (string) $FID_AMOUNT);
        $note     = wp_kses_post(rgar($entry, (string) $FID_NOTE));
        $file_url = rgar($entry, (string) $FID_PROOF_FILE);

        $attachment_id = 0;

        if ($file_url) {
            $attachment_id = attachment_url_to_postid($file_url);

            if ($attachment_id && function_exists('update_field')) {
                update_field($ACF_PROOF_FILE_FIELD_KEY, $attachment_id, $need_id);
            } elseif (! $attachment_id) {
                update_post_meta($need_id, 'proof_file_url', esc_url_raw($file_url));
            }
        }

        // Completion log
        $log = get_post_meta($need_id, 'completion_log', true);
        if (! is_array($log)) {
            $log = [];
        }

        $log[] = [
            'time'     => current_time('mysql'),
            'amount'   => $amount,
            'file_url' => $file_url,
            'file_id'  => $attachment_id,
            'note'     => $note,
            'entry_id' => $entry['id'] ?? 0,
        ];

        update_post_meta($need_id, 'completion_log', $log);

        if ($amount > 0) {
            update_post_meta($need_id, 'amount_confirmed', $amount);
        }
        if ($attachment_id) {
            update_post_meta($need_id, 'proof_file_id', $attachment_id);
        }
        if ($note) {
            update_post_meta($need_id, 'completion_note', $note);
        }

        wp_set_object_terms($need_id, 'Fulfilled', 'need_status', false);
        update_post_meta($need_id, 'fulfillment_date', current_time('Y-m-d'));

        /**
         * -------------------------------------------------
         * EMAILS FOR FORM 4
         * -------------------------------------------------
         */
        $need_title = get_the_title($need_id);
        $need_link  = get_permalink($need_id);
        $amount_str = number_format($amount, 2);

        // Member
        $member_email = '';
        $author_id    = (int) get_post_field('post_author', $need_id);

        if ($author_id) {
            $user = get_userdata($author_id);
            if ($user && ! empty($user->user_email)) {
                $member_email = $user->user_email;
            }
        }

        if (! $member_email) {
            $member_email = (string) get_post_meta($need_id, 'member_email', true);
        }

        if ($member_email) {
            Templates::send('fulfilled_member', $member_email, [
                '{need_title}' => $need_title,
                '{amount}'     => $amount_str,
                '{need_link}'  => $need_link,
            ]);
        }

        // Admin
        $admin_email = get_option('admin_email');
        if ($admin_email && is_email($admin_email)) {
            Templates::send('admin_fulfilled', $admin_email, [
                '{need_id}'    => (string) $need_id,
                '{need_title}' => $need_title,
                '{edit_link}'  => get_edit_post_link($need_id),
            ]);
        }
    }

    /**
     * Dynamic population (need_id)
     */
    public static function populate_need_id($value)
    {
        global $post;

        if ($post && $post->post_type === 'need') {
            return $post->ID;
        }

        if (isset($_GET['need_id'])) {
            return absint($_GET['need_id']);
        }

        return $value;
    }
}
