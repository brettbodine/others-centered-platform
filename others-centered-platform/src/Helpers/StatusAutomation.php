<?php

namespace OthersCentered\Platform\Helpers;

use OthersCentered\Platform\Emails\Templates;
use OthersCentered\Platform\Geocoding\ZipGeocoder;

class StatusAutomation
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        // Fired when a Need transitions status (admin clicks publish)
        add_action('transition_post_status', [self::class, 'on_publish'], 10, 3);

        // Cron event for auto-promotion New → Active
        add_action('oc_promote_need_to_active', [self::class, 'promote'], 10, 1);
    }

    /**
     * Handle a Need post being published.
     * Converts:
     *   In Review → New
     * Then geocodes the ZIP and schedules promotion to Active.
     */
    public static function on_publish(string $new_status, string $old_status, \WP_Post $post): void
    {
        // Only handle Need posts
        if ($post->post_type !== 'need') {
            return;
        }

        // Only act when post becomes or is published
        if ($new_status !== 'publish') {
            return;
        }

        // Get current status (APC sets "In Review")
        $existing = wp_get_object_terms($post->ID, 'need_status', ['fields' => 'names']);
        $current_status = strtolower($existing[0] ?? '');

        // Only override if current status is "In Review"
        if ($current_status !== 'in review') {
            return;
        }

        // Set to New when published
        wp_set_object_terms($post->ID, 'New', 'need_status', false);
        update_post_meta($post->ID, 'go_live_date', current_time('mysql'));

        /**
         * -------------------------------------------------
         * GEOCODE ZIP & SAVE LAT/LNG
         * -------------------------------------------------
         */
        $zip = get_post_meta($post->ID, 'zip', true);

        if ($zip) {
            $coords = ZipGeocoder::geocode_zip($zip);

            if ($coords) {
                update_post_meta($post->ID, 'need_lat', $coords['lat']);
                update_post_meta($post->ID, 'need_lng', $coords['lng']);
            } else {
                error_log("OC Geocode failed for Need {$post->ID} using ZIP {$zip}");
            }
        }

        // Notify member
        self::send_need_live_email($post->ID);

        /**
         * -------------------------------------------------
         * SCHEDULE NEW → ACTIVE PROMOTION (7 DAYS)
         * -------------------------------------------------
         */
        $seven_days = 7 * DAY_IN_SECONDS;

        if (! wp_next_scheduled('oc_promote_need_to_active', [$post->ID])) {
            wp_schedule_single_event(time() + $seven_days, 'oc_promote_need_to_active', [$post->ID]);
        }
    }

    /**
     * Promote New → Active (cron fired after 7 days).
     */
    public static function promote(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'need') {
            return;
        }

        $current = wp_get_object_terms($post_id, 'need_status', ['fields' => 'names']);
        $status  = strtolower($current[0] ?? '');

        // Only promote if currently "New"
        if ($status !== 'new') {
            return;
        }

        wp_set_object_terms($post_id, 'Active', 'need_status', false);
        update_post_meta($post_id, 'activated_date', current_time('mysql'));

        // Optional: Notify user again
        self::send_need_live_email($post_id);
    }

    /**
     * Send "need_live_member" email to the member associated with a Need.
     */
    protected static function send_need_live_email(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'need') {
            return;
        }

        $member_email = '';
        $author_id = (int) $post->post_author;

        if ($author_id) {
            $user = get_userdata($author_id);
            if ($user && ! empty($user->user_email)) {
                $member_email = $user->user_email;
            }
        }

        if (! $member_email) {
            $member_email = (string) get_post_meta($post_id, 'member_email', true);
        }

        if (! $member_email) {
            return;
        }

        $replacements = [
            '{need_title}' => get_the_title($post_id),
            '{need_link}'  => get_permalink($post_id),
        ];

        Templates::send('need_live_member', $member_email, $replacements);
    }
}
