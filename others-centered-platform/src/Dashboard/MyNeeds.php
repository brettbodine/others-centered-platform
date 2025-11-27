<?php

namespace OthersCentered\Platform\Dashboard;

class MyNeeds
{
    /**
     * Register shortcode.
     */
    public static function register(): void
    {
        add_shortcode('oc_my_needs', [self::class, 'render_shortcode']);
    }

    /**
     * Shortcode wrapper.
     */
    public static function render_shortcode(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your needs.</p>';
        }

        return self::render_for_user(get_current_user_id());
    }

    /**
     * Render table of needs created by this user.
     */
    public static function render_for_user(int $user_id): string
    {
        $needs = get_posts([
            'post_type'      => 'need',
            'posts_per_page' => -1,
            'post_status'    => ['publish', 'pending', 'draft'],
            'author'         => $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        if (empty($needs)) {
            return '<p>You have not submitted any needs yet.</p>';
        }

        ob_start();
        ?>

        <table class="oc-my-needs-table" style="width:100%;border-collapse:collapse;">
            <thead>
            <tr style="text-align:left;border-bottom:2px solid #ddd;">
                <th style="padding:8px;">Need</th>
                <th style="padding:8px;">Needed By</th>
                <th style="padding:8px;">Status</th>
                <th style="padding:8px;">Actions</th>
            </tr>
            </thead>
            <tbody>

            <?php foreach ($needs as $need): ?>
                <?php
                // Correct due date field
                $due_date = function_exists('get_field')
                    ? get_field('due_date', $need->ID)
                    : get_post_meta($need->ID, 'due_date', true);

                $status_badge = self::get_status_badge($need->ID);
                $close_url    = self::get_close_link($need->ID);
                ?>

                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:8px;">
                        <a href="<?php echo esc_url(get_edit_post_link($need->ID)); ?>">
                            <?php echo esc_html(get_the_title($need->ID)); ?>
                        </a>
                    </td>

                    <td style="padding:8px;">
                        <?php
                        echo $due_date
                            ? esc_html(date('M j, Y', strtotime($due_date)))
                            : '—';
                        ?>
                    </td>

                    <td style="padding:8px;">
                        <?php echo $status_badge; ?>
                    </td>

                    <td style="padding:8px;">
                        <a href="<?php echo esc_url(get_permalink($need->ID)); ?>">View</a>
                        &nbsp;|&nbsp;
                        <a href="<?php echo esc_url($close_url); ?>" style="color:#a00;">
                            Close Need
                        </a>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php
        return ob_get_clean();
    }

    /**
     * Status badge based on taxonomy need_status.
     */
    private static function get_status_badge(int $post_id): string
    {
        $terms = wp_get_post_terms($post_id, 'need_status');

        if (empty($terms) || is_wp_error($terms)) {
            return '<span style="background:#999;color:#fff;padding:3px 8px;border-radius:6px;">Unknown</span>';
        }

        $name  = strtolower($terms[0]->name);

        $colors = [
            'new'        => '#007bff',
            'active'     => '#28a745',
            'matched'    => '#17a2b8',
            'fulfilled'  => '#6f42c1',
            'closed'     => '#6c757d',
            'claimed'    => '#ff8800',
            'met'        => '#20c997',
        ];

        $color = $colors[$name] ?? '#999';

        return sprintf(
            '<span style="background:%s;color:#fff;padding:3px 8px;border-radius:6px;">%s</span>',
            esc_attr($color),
            esc_html(ucfirst($name))
        );
    }

    /**
     * Secure close-need link (nonced).
     */
    private static function get_close_link(int $post_id): string
    {
        return wp_nonce_url(
            add_query_arg(['oc_close_need' => $post_id], home_url('/')),
            'oc_close_need_action_' . $post_id
        );
    }
}
