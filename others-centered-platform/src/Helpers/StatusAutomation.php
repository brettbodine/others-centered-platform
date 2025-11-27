<?php

namespace OthersCentered\Platform\Helpers;

use OthersCentered\Platform\Emails\Templates;

class StatusAutomation
{
    /**
     * Register hooks.
     */
    public static function register(): void
    {
        // When status changes (draft → publish)
        add_action('transition_post_status', [self::class, 'on_publish'], 10, 3);

        // Cron event for auto-promotion
        add_action('oc_promote_need_to_active', [self::class, 'promote'], 10, 1);
    }

    /**
     * Handle post being published (draft → publish).
     * Auto-assign statuses & timestamps.
     */
    public static function on_publish(string $new_status, string $old_status, \WP_Post $post): void
    {
        if ($post->post_type !== 'need') {
            return;
        }
    
        // Only run when post is or becomes published
        if ($new_status !== 'publish') {
            return;
        }
    
        // If already has a status assigned, skip automation
        $existing = wp_get_object_terms($post->ID, 'need_status', ['fields' => 'names']);
        if (! empty($existing) && ! is_wp_error($existing)) {
            return;
        }
    
        // Force "In Review" (optional)
        $manual_status = get_post_meta($post->ID, 'oc_force_status', true);
        if ($manual_status === 'In Review') {
            wp_set_object_terms($post->ID, 'In Review', 'need_status', false);
            return;
        }
    
        // Default → ACTIVE
        wp_set_object_terms($post->ID, 'Active', 'need_status', false);
        update_post_meta($post->ID, 'go_live_date', current_time('mysql'));
    
        self::send_need_live_email($post->ID);
    
        // Optional cron promotion (kept for legacy)
        if (! wp_next_scheduled('oc_promote_need_to_active', [$post->ID])) {
            wp_schedule_single_event(time() + MINUTE_IN_SECONDS, 'oc_promote_need_to_active', [$post->ID]);
        }
    }

    /**
     * Promote a need to active (cron safe).
     * This handles:
     * - In Review  → Active
     */
    public static function promote(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'need') {
            return;
        }

        $current = wp_get_object_terms($post_id, 'need_status', ['fields' => 'names']);
        $status  = strtolower($current[0] ?? '');

        // Already active → nothing to do
        if ($status === 'active') {
            return;
        }

        // Promote In Review → Active
        if ($status === 'in review') {
            wp_set_object_terms($post_id, 'Active', 'need_status', false);
            update_post_meta($post_id, 'go_live_date', current_time('mysql'));

            // Send "need goes live" email to member
            self::send_need_live_email($post_id);
        }
    }

    /**
     * Send "need_live_member" email to the member associated with a need.
     */
    protected static function send_need_live_email(int $post_id): void
    {
        $post = get_post($post_id);
        if (! $post || $post->post_type !== 'need') {
            return;
        }

        // Prefer the post author as the member
        $member_email = '';
        $author_id    = (int) $post->post_author;

        if ($author_id) {
            $user = get_userdata($author_id);
            if ($user && ! empty($user->user_email)) {
                $member_email = $user->user_email;
            }
        }

        // Fallback to a custom meta field if you ever store it
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
