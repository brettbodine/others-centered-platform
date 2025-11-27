<?php

namespace OthersCentered\Platform\Queries;

use OthersCentered\Platform\Geocoding\ZipGeocoder;
use WP_Query;

class NeedsGridQuery
{
    /**
     * Fired by: add_action('elementor/query/needs_grid')
     */
    public static function modify_query(WP_Query $q): void
    {
        /**
         * -----------------------------------------------------
         * Core query defaults
         * -----------------------------------------------------
         */
        $q->set('post_type', 'need');
        $q->set('post_status', 'publish');

        // Prevent Elementor's auto-sorting from overriding us
        $q->set('ignore_custom_sort', true);

        /**
         * -----------------------------------------------------
         * TAXONOMY FILTERS
         * -----------------------------------------------------
         */
        $tax = ['relation' => 'AND'];

        // Default visibility: "New" + "Active"
        $tax[] = [
            'taxonomy'         => 'need_status',
            'field'            => 'name',
            'terms'            => ['New', 'Active'],
            'include_children' => true,
        ];

        // Category filter (?category=meals,repairs)
        if (!empty($_GET['category'])) {
            $slugs = array_map(
                'sanitize_title',
                explode(',', wp_unslash($_GET['category']))
            );

            $term_ids = [];
            foreach ($slugs as $slug) {
                $t = get_term_by('slug', $slug, 'need_category');
                if ($t) {
                    $term_ids[] = (int) $t->term_id;
                }
            }

            if (!empty($term_ids)) {
                $tax[] = [
                    'taxonomy'         => 'need_category',
                    'field'            => 'term_id',
                    'terms'            => $term_ids,
                    'include_children' => false,
                ];
            }
        }

        if (count($tax) > 1) {
            $q->set('tax_query', $tax);
        }

        /**
         * -----------------------------------------------------
         * META FILTERS
         * -----------------------------------------------------
         */
        $meta = ['relation' => 'AND'];

        /**
         * City filter (?city=Omaha)
         */
        if (!empty($_GET['city'])) {
            $city = trim(sanitize_text_field(wp_unslash($_GET['city'])));

            if ($city !== '') {
                $meta[] = [
                    'relation' => 'OR',
                    [
                        'key'     => 'city',
                        'value'   => $city,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'city',
                        'value'   => $city,
                        'compare' => 'LIKE',
                    ],
                ];
            }
        }

        /**
         * ZIP + Radius filtering
         */
        $zip    = !empty($_GET['zip']) ? sanitize_text_field(wp_unslash($_GET['zip'])) : '';
        $radius = !empty($_GET['radius']) ? (float) $_GET['radius'] : 0;
        
        if ($zip && $radius > 0 && preg_match('/^[0-9]{3,10}$/', $zip)) {
        
            // Get coords for this ZIP
            $coords = ZipGeocoder::geocode_zip($zip);
        
            if ($coords) {
                $lat = $coords['lat'];
                $lng = $coords['lng'];
                $earth_radius = 3959;
        
                $lat_delta = rad2deg($radius / $earth_radius);
                $lng_delta = rad2deg($radius / $earth_radius / cos(deg2rad($lat)));
        
                $meta[] = [
                    'key'     => 'need_lat',
                    'value'   => [$lat - $lat_delta, $lat + $lat_delta],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ];
                $meta[] = [
                    'key'     => 'need_lng',
                    'value'   => [$lng - $lng_delta, $lng + $lng_delta],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ];
            }
        
        } elseif ($zip) {
            $meta[] = [
                'key'     => 'zip',
                'value'   => $zip,
                'compare' => '=',
            ];
        }


        /**
         * Urgency filter (?urgency=today,next7,month,overdue,nodue)
         */
        if (!empty($_GET['urgency'])) {
            $u     = sanitize_text_field(wp_unslash($_GET['urgency']));
            $today = date('Y-m-d');

            $between = function ($start, $end) {
                return [
                    'relation' => 'OR',
                    [
                        'key'     => 'due_date',
                        'value'   => [$start, $end],
                        'compare' => 'BETWEEN',
                        'type'    => 'DATE',
                    ],
                    [
                        'key'     => 'due_date',
                        'value'   => [str_replace('-', '', $start), str_replace('-', '', $end)],
                        'compare' => 'BETWEEN',
                        'type'    => 'NUMERIC',
                    ],
                ];
            };

            $lt = function ($date) {
                return [
                    'relation' => 'OR',
                    [
                        'key'     => 'due_date',
                        'value'   => $date,
                        'compare' => '<',
                        'type'    => 'DATE',
                    ],
                    [
                        'key'     => 'due_date',
                        'value'   => str_replace('-', '', $date),
                        'compare' => '<',
                        'type'    => 'NUMERIC',
                    ],
                ];
            };

            switch ($u) {
                case 'today':
                    $meta[] = $between($today, $today);
                    break;

                case 'next7':
                    $meta[] = $between($today, date('Y-m-d', strtotime('+7 days')));
                    break;

                case 'month':
                    $meta[] = $between(date('Y-m-01'), date('Y-m-t'));
                    break;

                case 'overdue':
                    $meta[] = $lt($today);
                    break;

                case 'nodue':
                    $meta[] = [
                        'relation' => 'OR',
                        [
                            'key'     => 'due_date',
                            'compare' => 'NOT EXISTS',
                        ],
                        [
                            'key'     => 'due_date',
                            'value'   => '',
                            'compare' => '=',
                        ],
                    ];
                    break;
            }
        }

        /**
         * Amount filter (?amount=0-100 or 200+)
         */
        if (!empty($_GET['amount'])) {
            $raw = trim((string) $_GET['amount']);

            if (preg_match('/^(\d+)\-(\d+)$/', $raw, $m)) {
                $meta[] = [
                    'key'     => 'amount_requested',
                    'value'   => [(float) $m[1], (float) $m[2]],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ];
            }

            elseif (preg_match('/^(\d+)\+$/', $raw, $m)) {
                $meta[] = [
                    'key'     => 'amount_requested',
                    'value'   => (float) $m[1],
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ];
            }
        }

        if (count($meta) > 1) {
            $q->set('meta_query', $meta);
        }

        /**
         * Default sort: soonest due date first
         */
        $q->set('meta_key', 'due_date');
        $q->set('orderby', 'meta_value');
        $q->set('order', 'ASC');
    }
}
