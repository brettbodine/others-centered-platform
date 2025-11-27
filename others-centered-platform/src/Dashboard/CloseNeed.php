<?php

namespace OthersCentered\Platform\Dashboard;

class CloseNeed
{
    /**
     * Register hook.
     */
    public static function register(): void
    {
        add_action('init', [self::class, 'maybe_close_need']);
    }

    /**
     * Handles ?oc_close_need={ID}&_wpnonce={nonce}&redirect_to=...
     */
    public static function maybe_close_need(): void
    {
        if (empty($_GET['oc_close_need']) || empty($_GET['_wpnonce'])) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $post_id = absint($_GET['oc_close_need']);
        if (!$post_id || get_post_type($post_id) !== 'need') {
            return;
        }

        // Sanitize nonce
        $nonce = sanitize_text_field(wp_unslash($_GET['_wpnonce']));

        // Must match nonce used in MyNeeds.php
        if (!wp_verify_nonce($nonce, 'oc_close_need_action_' . $post_id)) {
            return;
        }

        // Only post author or admins/editors may close
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Update status
        wp_set_object_terms($post_id, 'Closed', 'need_status', false);

        // Log closure
        update_post_meta($post_id, 'closed_by', get_current_user_id());
        update_post_meta($post_id, 'closed_at', current_time('mysql'));

        // Where to redirect
        $redirect = isset($_GET['redirect_to'])
            ? esc_url_raw($_GET['redirect_to'])
            : get_permalink($post_id);

        wp_safe_redirect($redirect);
        exit;
    }
}
