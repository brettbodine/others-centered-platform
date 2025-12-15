<?php

namespace OthersCentered\Platform\Helpers;

use OthersCentered\Platform\Emails\Templates;
use WP_Post;

class StatusAutomation
{
    public static function register(): void
    {
        // Need published
        add_action('transition_post_status', [self::class, 'on_publish'], 10, 3);

        // Taxonomy status changes
        add_action('set_object_terms', [self::class, 'on_status_set'], 10, 6);

        // Cron: New → Active
        add_action('oc_promote_new_need', [self::class, 'promote_new_to_active']);
    }

    /**
     * -----------------------------------------------------
     * When Need is published → goes live
     * -----------------------------------------------------
     */
    public static function on_publish(string $new_status, string $old_status, WP_Post $post): void
    {
        if ($post->post_type !== 'need') {
            return;
        }

        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        // Prevent duplicate "live" email
        if (get_post_meta($post->ID, 'go_live_date', true)) {
            return;
        }

        // Normalize initial status
        $terms = wp_get_object_terms($post->ID, 'need_status', ['fields' => 'names']);
        $current = strtolower($terms[0] ?? '');

        if ($current === 'in review') {
            wp_set_object_terms($post->ID, 'New', 'need_status', false);
        }

        // Send live email
        self::send_need_live_email($post->ID);

        update_post_meta($post->ID, 'go_live_date', current_time('mysql'));

        // Schedule promotion
        if (!wp_next_scheduled('oc_promote_new_need', [$post->ID])) {
            wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'oc_promote_new_need', [$post->ID]);
        }
    }

    /**
     * -----------------------------------------------------
     * When need_status taxonomy is set
     * -----------------------------------------------------
     */
    public static function on_status_set(
        int $object_id,
        array $terms,
        array $tt_ids,
        string $taxonomy,
        bool $append,
        array $old_tt_ids
    ): void {
        if ($taxonomy !== 'need_status') {
            return;
        }

        $post = get_post($object_id);
        if (!$post || $post->post_type !== 'need') {
            return;
        }

        // Normalize new status
        $new_terms = wp_get_object_terms($object_id, 'need_status', ['fields' => 'names']);
        $status = strtolower($new_terms[0] ?? '');

        /**
         * CLAIMED  → MATCHED EMAILS
         */
        if ($status === 'claimed') {
            self::handle_claimed($object_id);
            return;
        }

        /**
         * MET → FULFILLED EMAILS
         */
        if ($status === 'met') {
            self::handle_met($object_id);
            return;
        }
    }

    /**
     * -----------------------------------------------------
     * CLAIMED HANDLER (replaces "matched")
     * -----------------------------------------------------
     */
    protected static function handle_claimed(int $post_id): void
    {
        if (get_post_meta($post_id, 'email_claimed_sent', true)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) return;

        $member = get_userdata($post->post_author);

        if ($member && $member->user_email) {
            Templates::send('matched_member', $member->user_email, [
                '{need_title}' => $post->post_title,
                '{need_link}'  => get_permalink($post_id),
                '{need_id}'    => $post_id,
            ]);
        }

        Templates::send('admin_matched', get_option('admin_email'), [
            '{need_title}' => $post->post_title,
            '{edit_link}'  => get_edit_post_link($post_id),
            '{need_id}'    => $post_id,
        ]);

        update_post_meta($post_id, 'email_claimed_sent', 1);
    }

    /**
     * -----------------------------------------------------
     * MET HANDLER (replaces "fulfilled")
     * -----------------------------------------------------
