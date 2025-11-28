<?php

namespace OthersCentered\Platform\Helpers;

use OthersCentered\Platform\Emails\Templates;

class StatusAutomation
{
    public static function register(): void
    {
        add_action('transition_post_status', [self::class, 'on_publish'], 10, 3);

        // Cron promotion: New → Active
        add_action('oc_promote_new_need', [self::class, 'promote_new_to_active']);
    }

    /**
     * When a Need is published:
     * In Review → New
     */
    public static function on_publish(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type !== 'need') {
            return;
        }

        // Only when going to published state
        if ($new_status !== 'publish') {
            return;
        }

        // Current taxonomy status
        $existing = wp_get_object_terms($post->ID, 'need_status', ['fields' => 'names']);
        $current  = strtolower($existing[0] ?? '');

        // Already assigned a status → leave it alone
        if (!empty($existing) && !is_wp_error($existing)) {
            // Only update In Review → New
            if ($current === 'in review') {
                wp_set_object_terms($post->ID, 'New', 'need_status', false);

                // Schedule promotion to Active
                if (!wp_next_scheduled('oc_promote_new_need', [$post->ID])) {
                    wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'oc_promote_new_need', [$post->ID]);
                }
            }

            return;
        }

        // Fallback: if missing status entirely
        wp_set_object_terms($post->ID, 'New', 'need_status', false);

        // Schedule promotion
        if (!wp_next_scheduled('oc_promote_new_need', [$post->ID])) {
            wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'oc_promote_new_need', [$post->ID]);
        }
    }

    /**
     * Cron promotion:
     * New → Active after 7 days
     */
    public static function promote_new_to_active(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'need') {
            return;
        }

        $status = wp_get_object_terms($post_id, 'need_status', ['fields' => 'names']);
        $current = strtolower($status[0] ?? '');

        if ($current === 'new') {
            wp_set_object_terms($post_id, 'Active', 'need_status', false);

            // Fire "need live" email
            self::send_need_live_email($post_id);

            update_post_meta($post_id, 'go_live_date', current_time('mysql'));
        }
    }

    /**
     * Email sent when Need becomes Active
     */
    protected static function send_need_live_email(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post) return;

        $user = get_userdata($post->post_author);
        if (!$user || empty($user->user_email)) return;

        $replacements = [
            '{need_title}' => $post->post_title,
            '{need_link}'  => get_permalink($post_id),
        ];

        Templates::send('need_live_member', $user->user_email, $replacements);
    }
}
