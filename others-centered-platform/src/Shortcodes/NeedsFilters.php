<?php

namespace OthersCentered\Platform\Shortcodes;

class NeedsFilters
{
    public static function register()
    {
        add_shortcode('oc_needs_filters', [self::class, 'render']);
    }

    /**
     * Render full filter form exactly like the original
     */
    public static function render()
    {
        // current selections
        $sel_cat    = isset($_GET['category']) ? sanitize_title($_GET['category']) : '';
        $sel_city   = isset($_GET['city']) ? sanitize_text_field($_GET['city']) : '';
        $sel_urg    = isset($_GET['urgency']) ? sanitize_text_field($_GET['urgency']) : '';
        $sel_amt    = isset($_GET['amount']) ? sanitize_text_field($_GET['amount']) : '';
        $sel_zip    = isset($_GET['zip']) ? sanitize_text_field($_GET['zip']) : '';
        $sel_radius = isset($_GET['radius']) ? sanitize_text_field($_GET['radius']) : '';

        // categories
        $cats = get_terms([
            'taxonomy'   => 'need_category',
            'hide_empty' => true,
        ]);

        // ---- Collect available cities (same logic as original) ----
        $collect_cities = function ($query_args) {
            $ids = get_posts(array_merge([
                'post_type'      => 'need',
                'post_status'    => 'publish',
                'posts_per_page' => 1000,
                'fields'         => 'ids',
            ], $query_args));

            $uniq = [];
            foreach ($ids as $id) {
                $val = get_post_meta($id, 'city', true);
                if (is_array($val)) {
                    $val = implode(', ', array_filter($val));
                }
                $val = trim((string) $val);
                if ($val !== '') {
                    $uniq[$val] = true;
                }
            }

            $list = array_keys($uniq);
            natcasesort($list);
            return $list;
        };

        // Try only New + Active first
        $cities = $collect_cities([
            'tax_query' => [
                [
                    'taxonomy' => 'need_status',
                    'field'    => 'name',
                    'terms'    => ['New', 'Active'],
                    'operator' => 'IN',
                ]
            ]
        ]);

        // Fallback: all published
        if (empty($cities)) {
            $cities = $collect_cities([]);
        }

        // urgency
        $urgency = [
            ''        => 'Any date',
            'today'   => 'Due today',
            'next7'   => 'Next 7 days',
            'month'   => 'This month',
            'overdue' => 'Overdue',
            'nodue'   => 'No due date',
        ];

        // amount buckets
        $amounts = [
            ''        => 'Any amount',
            '1-50'    => '$1–$50',
            '51-100'  => '$51–$100',
            '101-250' => '$101–$250',
            '251+'    => '$251+',
        ];

        // radius
        $radii = [
            ''   => 'Any distance',
            '5'  => 'Within 5 miles',
            '10' => 'Within 10 miles',
            '25' => 'Within 25 miles',
            '50' => 'Within 50 miles',
        ];

        // reset link
        $reset = esc_url(get_post_type_archive_link('need'));

        ob_start();
        ?>

        <form class="oc-filters-form" method="get" action="">
            <div class="oc-filters">

                <!-- Category -->
                <label>
                    <span>Category</span>
                    <select name="category">
                        <option value="">All categories</option>
                        <?php foreach ($cats as $t): ?>
                            <option value="<?php echo esc_attr($t->slug); ?>" <?php selected($sel_cat, $t->slug); ?>>
                                <?php echo esc_html($t->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- City -->
                <label>
                    <span>City</span>
                    <select name="city">
                        <option value="">
                            <?php echo empty($cities) ? 'No cities yet' : 'All cities'; ?>
                        </option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?php echo esc_attr($c); ?>" <?php selected($sel_city, $c); ?>>
                                <?php echo esc_html($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- Urgency -->
                <label>
                    <span>Date</span>
                    <select name="urgency">
                        <?php foreach ($urgency as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sel_urg, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- Amount -->
                <label>
                    <span>Amount needed</span>
                    <select name="amount">
                        <?php foreach ($amounts as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sel_amt, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- Zip -->
                <label>
                    <span>Zip code</span>
                    <input
                        type="text"
                        name="zip"
                        value="<?php echo esc_attr($sel_zip); ?>"
                        placeholder="Any zip"
                    />
                </label>

                <!-- Radius -->
                <label>
                    <span>Radius</span>
                    <select name="radius">
                        <?php foreach ($radii as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($sel_radius, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <button type="submit" class="oc-btn">Filter</button>
                <a class="oc-reset" href="<?php echo $reset; ?>">Reset</a>

            </div>
        </form>

        <script>
        // Auto-submit on change for selects
        document.addEventListener('change', function(e){
            if (e.target.closest('.oc-filters-form select')) {
                e.target.closest('form').submit();
            }
        });
        </script>

        <?php
        return ob_get_clean();
    }
}
