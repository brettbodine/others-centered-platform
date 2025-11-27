<?php

namespace OthersCentered\Platform\Admin;

class MetaBoxes {

    public static function register() {
        add_meta_box(
            'oc_completion_log',
            'Completion Log',
            [self::class, 'render_completion_box'],
            'need',
            'normal',
            'default'
        );

        add_meta_box(
            'oc_helper_contacts',
            'Helper Contacts',
            [self::class, 'render_helper_contacts_box'],
            'need',
            'normal',
            'default'
        );
    }

    public static function render_completion_box( $post ) {
        // your existing completion log rendering logic goes here
    }

    public static function render_helper_contacts_box( $post ) {
        // your existing helper contacts logic goes here
    }
}
