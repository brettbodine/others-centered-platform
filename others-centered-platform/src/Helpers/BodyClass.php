<?php

namespace OthersCentered\Platform\Helpers;

class BodyClass
{
    /**
     * Register body_class filter.
     */
    public static function register(): void
    {
        add_filter('body_class', [self::class, 'add_role_classes']);
    }

    /**
     * Adds classes to <body>:
     * - oc-logged-in / oc-logged-out
     * - oc-role-{role}
     * - oc-user-has-needs
     * - oc-user-has-claimed-needs
     */
    public static function add_role_classes(array $classes): array
    {
        // Logged-out users
        if (!is_user_logged_in()) {
            $classes[] = 'oc-logged-out';
            return $classes;
        }

        // Logged-in users
        $classes[] = 'oc-logged-in';

        $user_id = get_current_user_id();
        $user    = wp_get_current_user();
        $roles   = (array) $user->roles;

        // Add each WP role
        foreach ($roles as $role) {
            $classes[] = 'oc-role-' . sanitize_html_class($role);
        }

        // User has submitted needs?
        $has_needs = get_posts([
            'post_type'      => 'need',
            'post_status'    => 'any',
            'author'         => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($has_needs)) {
            $classes[] = 'oc-user-has-needs';
        }

        // User has claimed needs?
        $has_claimed = get_posts([
            'post_type'      => 'need',
            'post_status'    => 'any',
            'meta_key'       => 'helper_user_id',
            'meta_value'     => $user_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);

        if (!empty($has_claimed)) {
            $classes[] = 'oc-user-has-claimed-needs';
        }

        return $classes;
    }
}
