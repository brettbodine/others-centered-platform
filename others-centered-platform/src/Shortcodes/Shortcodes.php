<?php

namespace OthersCentered\Platform\Shortcodes;

use WP_Post;

class Shortcodes
{
    /**
     * Register all shortcodes for the platform.
     */
    public static function register(): void
    {
        add_shortcode('needs_due_status', [self::class, 'needs_due_status']);
        add_shortcode('oc_need_status',  [self::class, 'need_status']);
        add_shortcode('oc_need_verification_form', [self::class, 'verification_form']);


        // Delegate [oc_needs_filters] out to NeedsFilters::render()
        add_shortcode(
            'oc_needs_filters',
            \OthersCentered\Platform\Shortcodes\NeedsFilters::class . '::render'
        );

        add_shortcode('oc_my_claimed_needs', [self::class, 'my_claimed_needs']);
        add_shortcode('oc_my_link_rating',  [self::class, 'my_link_rating']);
        add_shortcode('oc_how_it_works',    [self::class, 'how_it_works']);
    }


    /**
     * -----------------------------------------------------
     * [needs_due_status]
     * Displays a colored pill showing urgency level.
     * -----------------------------------------------------
     */
    public static function needs_due_status(): string
    {
        global $post;

        if (!$post instanceof WP_Post) {
            return '';
        }

        $date = function_exists('get_field')
            ? get_field('due_date', $post->ID)
            : get_post_meta($post->ID, 'due_date', true);

        if (!$date) {
            return '';
        }

        $due_ts = strtotime($date);
        $today  = strtotime(date('Y-m-d'));
        $days   = (int) floor(($due_ts - $today) / 86400);

        // Determine pill style
        if ($days > 7) {
            $label = 'Upcoming';
            $class = 'pill-upcoming';
        } elseif ($days >= 0) {
            $label = 'Urgent';
            $class = 'pill-urgent';
        } else {
            $label = 'Past Due';
            $class = 'pill-pastdue';
        }

        // Human-readable time
        if ($days > 0) {
            $days_text = $days . ' day' . ($days === 1 ? '' : 's');
        } elseif ($days === 0) {
            $days_text = 'Due today';
        } else {
            $days_text = abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' ago';
        }

        $icon = '<span class="needs-calendar-icon"><i class="eicon-calendar" aria-hidden="true"></i></span>';

        return sprintf(
            '<span class="needs-status-pill %1$s">%2$s<span class="needs-status-text">%3$s — %4$s</span></span>',
            esc_attr($class),
            $icon,
            esc_html($days_text),
            esc_html($label)
        );
    }


    /**
     * -----------------------------------------------------
     * [oc_need_status]
     * Displays current need_status term badge.
     * -----------------------------------------------------
     */
    public static function need_status(): string
    {
        global $post;

        if (!$post instanceof WP_Post || $post->post_type !== 'need') {
            return '';
        }

        $terms = wp_get_post_terms($post->ID, 'need_status');

        if (is_wp_error($terms) || empty($terms)) {
            return '';
        }

        $term  = $terms[0];
        $class = 'need-status-badge need-status-' . sanitize_html_class($term->slug);

        return sprintf(
            '<span class="%1$s">%2$s</span>',
            esc_attr($class),
            esc_html($term->name)
        );
    }


    /**
     * -----------------------------------------------------
     * [oc_my_claimed_needs]
     * Displays a table of needs the logged-in user has claimed.
     * -----------------------------------------------------
     */
    public static function my_claimed_needs(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to see the needs you’ve claimed.</p>';
        }

        return \OthersCentered\Platform\Dashboard\ClaimedNeeds::render_for_user(
            get_current_user_id()
        );
    }
    
        /**
     * -----------------------------------------------------
     * [oc_need_verification_form]
     * Show Gravity Form 4 only when:
     *   - post type = need
     *   - need_status contains "Matched"
     *   - and NOT "Fulfilled" or "Met"
     * -----------------------------------------------------
     */
    public static function verification_form(): string
    {
        global $post;

        if (! ($post instanceof WP_Post) || $post->post_type !== 'need') {
            return '';
        }

        // Get statuses
        $statuses = wp_get_post_terms($post->ID, 'need_status', ['fields' => 'names']);
        if (is_wp_error($statuses) || empty($statuses)) {
            return '';
        }

        $has_matched   = in_array('Matched', $statuses, true);
        $has_fulfilled = in_array('Fulfilled', $statuses, true);
        $has_met       = in_array('Met', $statuses, true);

        // Conditions to show the form
        if (! $has_matched || $has_fulfilled || $has_met) {
            return '';
        }

        ob_start();

        if (function_exists('gravity_form')) {
            gravity_form(
                4,                           // Form ID
                false,                       // No title
                false,                       // No description
                false,                       // Display inactive
                ['need_id' => $post->ID],    // Pass token
                true                         // AJAX
            );
        } else {
            echo '<p>Verification form not available.</p>';
        }

        return ob_get_clean();
    }


    /**
     * -----------------------------------------------------
     * [oc_how_it_works]
     * Simple informational block.
     * -----------------------------------------------------
     */
    public static function how_it_works(): string
    {
        ob_start();
        ?>
        <div class="oc-how-it-works">
            <h2>How Others Centered works</h2>

            <ol>
                <li><strong>Join the community.</strong> Create an account to connect with helpers safely.</li>
                <li><strong>Post a need.</strong> Share what you're facing—your info remains private.</li>
                <li><strong>Review process.</strong> A coordinator ensures the request is appropriate and safe.</li>
                <li><strong>A helper steps in.</strong> Helpers reach out using our secure forms.</li>
                <li><strong>Work out details.</strong> Meet safely and finalize what is needed.</li>
                <li><strong>Verification.</strong> A final verification keeps the community accountable.</li>
            </ol>

            <p class="oc-safety-note" style="font-size:13px;color:#475569;margin-top:8px;">
                <strong>Safety first:</strong>
                Never share passwords, banking logins, or sensitive documents. Stop immediately if anything feels unsafe.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
