<?php

namespace OthersCentered\Platform\Helpers;

use OthersCentered\Platform\Emails\Templates;
use WP_Post;

class StatusAutomation
{
    public static function register(): void
    {
        // When post is published
        add_action('transition_post_status', [self::class, 'on_publish'], 10, 3);

        // When need_status taxonomy changes
        add_action('set_object_terms', [self::class, 'on_status_change'], 10, 6);

        // Cron: New → Active (no email)
        add_action('oc_promote_new_need', [self::class, 'promote_new_to_active']);
    }

    /**
     * -----------------------------------------------------
     * NEED PUBLISHED → GOES LIVE
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

        // Prevent duplicate sends
        if (get_post_meta($post->ID, 'go_live_date', true)) {
            return;
        }

        // Normalize taxonomy
        $terms   = wp_get_object_terms($post->ID, 'need_status', ['fields' => 'names']);
        $current = strtolower($terms[0] ?? '');

        if ($current === 'in review') {
            wp_set_object_terms($post->ID, 'New', 'need_status', false);
        }

        self::send_need_live_email($post->ID);

        update_post_meta($post->ID, 'go_live_date', current_time('mysql'));

        if (!wp_next_scheduled('oc_promote_new_need', [$post->ID])) {
            wp_schedule_single_event(time() + (7 * DAY_IN_SECONDS), 'oc_promote_new_need', [$post->ID]);
        }
    }

    /**
     * -----------------------------------------------------
     * NEED STATUS CHANGES
     * -----------------------------------------------------
     */
    public static function on_status_change(
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

        $new_terms = wp_get_object_terms($object_id, 'need_status', ['fields' => 'names']);
        $new = strtolower($new_terms[0] ?? '');

        // MATCHED / CLAIMED
        if (in_array($new, ['matched', 'claimed'], true)) {
            self::handle_matched($object_id);
        }

        // MET / FULFILLED
        if (in_array($new, ['met', 'fulfilled'], true)) {
            self::handle_fulfilled($object_id);
        }
    }

    /**
     * -----------------------------------------------------
     * MATCHED HANDLER
     * -----------------------------------------------------
     */
    protected static function handle_matched(int $post_id): void
    {
        if (get_post_meta($post_id, 'email_matched_sent', true)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) return;

        // Member email
        $member = get_userdata($post->post_author);
        if ($member && $member->user_email) {
            Templates::send('matched_member', $member->user_email, [
                '{need_title}' => $post->post_title,
                '{need_link}'  => get_permalink($post_id),
                '{need_id}'    => $post_id,
            ]);
        }

        // Admin email
        Templates::send('admin_matched', get_option('admin_email'), [
            '{need_title}' => $post->post_title,
            '{edit_link}'  => get_edit_post_link($post_id),
            '{need_id}'    => $post_id,
        ]);

        update_post_meta($post_id, 'email_matched_sent', 1);
    }

    /**
     * -----------------------------------------------------
     * FULFILLED / MET HANDLER
     * -----------------------------------------------------
     */
    protected static function handle_fulfilled(int $post_id): void
    {
        if (get_post_meta($post_id, 'email_fulfilled_sent', true)) {
            return;
        }

        $post = get_post($post_id);
        if (!$post) return;

        // Admin fulfilled email
        Templates::send('admin_fulfilled', get_option('admin_email'), [
            '{need_title}' => $post->post_title,
            '{edit_link}'  => get_edit_post_link($post_id),
            '{need_id}'    => $post_id,
        ]);

        // Helper thank-you email (NEW)
        $helper_id = (int) get_post_meta($post_id, 'helper_user_id', true);
        if ($helper_id) {
            $helper = get_userdata($helper_id);
            if ($helper && $helper->user_email) {
                Templates::send('helper_thanks', $helper->user_email, [
                    '{need_title}' => $post->post_title,
                    '{need_link}'  => get_permalink($post_id),
                    '{need_id}'    => $post_id,
                ]);
            }
        }

        update_post_meta($post_id, 'email_fulfilled_sent', 1);
    }

    /**
     * -----------------------------------------------------
     * CRON: NEW → ACTIVE
     * -----------------------------------------------------
     */
    public static function promote_new_to_active(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'need') {
            return;
        }

        $terms   = wp_get_object_terms($post_id, 'need_status', ['fields' => 'names']);
        $current = strtolower($terms[0] ?? '');

        if ($current === 'new') {
            wp_set_object_terms($post_id, 'Active', 'need_status', false);
        }
    }

    /**
     * -----------------------------------------------------
     * NEED LIVE EMAIL
     * -----------------------------------------------------
     */
    protected static function send_need_live_email(int $post_id): void
    {
        $post = get_post($post_id);
        if (!$post) return;

        $user = get_userdata($post->post_author);
        if (!$user || empty($user->user_email)) return;

        Templates::send('need_live_member', $user->user_email, [
            '{need_title}' => $post->post_title,
            '{need_link}'  => get_permalink($post_id),
            '{need_id}'    => $post_id,
        ]);
    }
}
