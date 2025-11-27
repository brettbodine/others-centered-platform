<?php

namespace OthersCentered\Platform\Dashboard;

class ClaimedNeeds
{
    /**
     * Render the full "Needs I've Claimed" table for a user.
     */
    public static function render_for_user(int $user_id): string
    {
        // Use correct meta key from Forms::store_helper_user()
        $needs = get_posts([
            'post_type'      => 'need',
            'post_status'    => ['publish', 'pending'],
            'meta_key'       => 'helper_user_id',
            'meta_value'     => $user_id,
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ]);

        if (empty($needs)) {
            return '<p>You have not claimed any needs yet.</p>';
        }

        ob_start();
        ?>

        <table class="oc-claimed-needs-table" style="width:100%;border-collapse:collapse;">
            <thead>
            <tr style="text-align:left;border-bottom:2px solid #ddd;">
                <th style="padding:8px;">Need</th>
                <th style="padding:8px;">Posted By</th>
                <th style="padding:8px;">Claimed On</th>
                <th style="padding:8px;">Status</th>
                <th style="padding:8px;">Actions</th>
            </tr>
            </thead>
            <tbody>

            <?php foreach ($needs as $need_id): ?>

                <?php
                $title  = get_the_title($need_id);
                $author = get_the_author_meta('display_name', get_post_field('post_author', $need_id));

                // Claimed log from helper_contacts (latest entry)
                $log = get_post_meta($need_id, 'helper_contacts', true);
                if (is_array($log) && !empty($log)) {
                    $last = end($log);
                    $claimed_on = date('M j, Y', strtotime($last['time']));
                } else {
                    $claimed_on = '—';
                }

                $status = self::get_status_label($need_id);
                ?>

                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:8px;">
                        <a href="<?php echo esc_url(get_permalink($need_id)); ?>">
                            <?php echo esc_html($title); ?>
                        </a>
                    </td>

                    <td style="padding:8px;">
                        <?php echo esc_html($author ?: '—'); ?>
                    </td>

                    <td style="padding:8px;">
                        <?php echo esc_html($claimed_on); ?>
                    </td>

                    <td style="padding:8px;">
                        <?php echo $status; ?>
                    </td>

                    <td style="padding:8px;">
                        <a href="<?php echo esc_url(get_permalink($need_id)); ?>">View</a>
                    </td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>

        <?php
        return ob_get_clean();
    }


    /**
     * Generate colored status label for need_status taxonomy.
     */
    private static function get_status_label(int $post_id): string
    {
        $terms = wp_get_post_terms($post_id, 'need_status');

        if (empty($terms) || is_wp_error($terms)) {
            return '<span style="background:#777;color:#fff;padding:3px 8px;border-radius:6px;">Unknown</span>';
        }

        $term = strtolower($terms[0]->name);

        // Map your actual statuses
        $colors = [
            'new'        => '#007bff',
            'active'     => '#28a745',
            'matched'    => '#17a2b8',
            'fulfilled'  => '#6f42c1',
            'closed'     => '#6c757d',
            'claimed'    => '#ff8800',
            'met'        => '#20c997',
        ];

        $color = $colors[$term] ?? '#777';

        return sprintf(
            '<span style="background:%s;color:#fff;padding:3px 8px;border-radius:6px;">%s</span>',
            esc_attr($color),
            esc_html(ucfirst($term))
        );
    }
}
