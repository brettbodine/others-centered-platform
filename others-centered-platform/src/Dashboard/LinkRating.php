<?php

namespace OthersCentered\Platform\Dashboard;

class LinkRating
{
    /**
     * Count completed needs for a user.
     * A need is "completed" if:
     *   - helper_user_id == user
     *   - AND status is Fulfilled or Met
     */
    public static function count_completed(int $user_id): int
    {
        $needs = get_posts([
            'post_type'      => 'need',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_key'       => 'helper_user_id',
            'meta_value'     => $user_id,
            'tax_query'      => [
                [
                    'taxonomy' => 'need_status',
                    'field'    => 'name',
                    'terms'    => ['Fulfilled', 'Met'],
                ],
            ],
        ]);

        return is_array($needs) ? count($needs) : 0;
    }

    /**
     * Render a star-based link rating for the user.
     */
    public static function render_for_user(int $user_id): string
    {
        $count = self::count_completed($user_id);

        // UX: max 5 stars
        $max_stars = 5;
        $stars_to_show = min($count, $max_stars);

        $icon = '<span style="color:#f7c600; font-size:22px;">★</span>';
        $icons_html = str_repeat($icon, $stars_to_show);

        ob_start();
        ?>
        <div class="oc-link-rating" style="display:flex;align-items:center;gap:6px;">
            <div class="oc-link-rating-icons">
                <?php echo $icons_html; ?>
            </div>
            <div class="oc-link-rating-count" style="font-weight:600;">
                (<?php echo esc_html($count); ?> completed)
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Shortcode [oc_my_link_rating]
     */
    public static function register(): void
    {
        add_shortcode('oc_my_link_rating', function () {
            if (!is_user_logged_in()) {
                return '<p>Please log in to view your rating.</p>';
            }

            return self::render_for_user(get_current_user_id());
        });
    }
}
