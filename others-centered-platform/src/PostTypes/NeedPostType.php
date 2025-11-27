<?php

namespace OthersCentered\Platform\PostTypes;

class NeedPostType
{
    public static function register(): void
    {
        register_post_type('need', [
            'labels' => [
                'name'               => __('Needs', 'others-centered-platform'),
                'singular_name'      => __('Need', 'others-centered-platform'),
                'add_new'            => __('Add New', 'others-centered-platform'),
                'add_new_item'       => __('Add New Need', 'others-centered-platform'),
                'edit_item'          => __('Edit Need', 'others-centered-platform'),
                'new_item'           => __('New Need', 'others-centered-platform'),
                'view_item'          => __('View Need', 'others-centered-platform'),
                'search_items'       => __('Search Needs', 'others-centered-platform'),
                'not_found'          => __('No needs found', 'others-centered-platform'),
                'not_found_in_trash' => __('No needs found in Trash', 'others-centered-platform'),
                'all_items'          => __('All Needs', 'others-centered-platform'),
            ],

            'public'              => true,
            'publicly_queryable'  => true,
            'has_archive'         => true,

            // If you want Needs to disappear from site-wide search, set this to true
            'exclude_from_search' => false,

            'menu_icon'           => 'dashicons-hammer',
            'menu_position'       => 21,

            'supports' => [
                'title',
                'editor',
                'excerpt',
                'author',
                'thumbnail',
                'custom-fields',
            ],

            'rewrite' => ['slug' => 'needs'],

            'show_in_rest'          => true,
            'rest_base'             => 'needs',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
        ]);

        /**
         * -----------------------------------------------
         * TAXONOMIES
         * -----------------------------------------------
         */

        // Need Category
        register_taxonomy('need_category', 'need', [
            'label'        => __('Need Categories', 'others-centered-platform'),
            'public'       => true,
            'hierarchical' => false,
            'rewrite'      => ['slug' => 'need-category'],
            'show_in_rest' => true,
        ]);

        // Need Status
        register_taxonomy('need_status', 'need', [
            'label'        => __('Need Status', 'others-centered-platform'),
            'public'       => true,
            'hierarchical' => true,
            'rewrite'      => ['slug' => 'need-status'],
            'show_in_rest' => true,
        ]);

        self::ensure_default_terms();
    }


    /**
     * Insert default category & status terms
     */
    protected static function ensure_default_terms(): void
    {
        $categories = [
            'Bills & Essentials',
            'Meals',
            'Household Help/Repairs',
        ];

        foreach ($categories as $c) {
            if (!term_exists($c, 'need_category')) {
                wp_insert_term($c, 'need_category');
            }
        }

        $statuses = [
            'In Review',
            'New',
            'Active',
            'Posted',
            'Matched',
            'Fulfilled',
            'Closed',
            'Met',
            'Claimed',
        ];

        foreach ($statuses as $s) {
            if (!term_exists($s, 'need_status')) {
                wp_insert_term($s, 'need_status');
            }
        }
    }
}
