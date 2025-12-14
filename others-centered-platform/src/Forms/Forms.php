<?php

namespace OthersCentered\Platform\Forms;

class Forms
{
    public const FORM_ID_ASK    = 1;
    public const FORM_ID_HELP   = 2;
    public const FORM_ID_VERIFY = 4;

    // Hidden field IDs that contain the Need post ID
    private const FORM2_NEED_ID_FIELD = 1;
    private const FORM4_NEED_ID_FIELD = 1;

    public static function register(): void
    {
        add_action(
            'gform_advancedpostcreation_post_after_creation',
            [self::class, 'after_need_created'],
            10,
            4
        );

        add_action('gform_after_submission_2', [self::class, 'handle_help_submission'], 10, 2);
        add_action('gform_after_submission_2', [self::class, 'store_helper_user'], 20, 2);

        add_action('gform_after_submission_4', [self::class, 'verify_member'], 10, 2);
    }

    /* ----------------------------------------------------
     * FORM 1 — Need creation
     * ---------------------------------------------------- */
    public static function after_need_created($post_id, $feed, $entry, $form): void
    {
        if ((int) $form['id'] !== self::FORM_ID_ASK || get_post_type($post_id) !== 'need') {
            return;
        }

        wp_set_object_terms($post_id, 'In Review', 'need_status', false);
    }

    /* ----------------------------------------------------
     * FORM 2 — Helper claims a need
     * ---------------------------------------------------- */
    public static function handle_help_submission($entry, $form): void
    {
        if ((int) ($form['id'] ?? 0) !== self::FORM_ID_HELP) {
            return;
        }

        $need_id = self::get_need_id_for_form($entry, self::FORM_ID_HELP);
        if (!$need_id) {
            error_log('OC Form2: need_id not found');
            return;
        }

        self::set_need_status_by_slug($need_id, ['claimed', 'matched']);
    }

    public static function store_helper_user($entry, $form): void
    {
        if ((int) ($form['id'] ?? 0) !== self::FORM_ID_HELP) {
            return;
        }

        $need_id = self::get_need_id_for_form($entry, self::FORM_ID_HELP);
        $user_id = get_current_user_id();

        if (!$need_id || !$user_id) {
            return;
        }

        $log = get_post_meta($need_id, 'helper_contacts', true);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'helper_user_id' => (int) $user_id,
            'time'           => current_time('mysql'),
        ];

        update_post_meta($need_id, 'helper_contacts', $log);
        update_post_meta($need_id, 'helper_user_id', (int) $user_id);
    }

    /* ----------------------------------------------------
     * FORM 4 — Member verifies completion
     * ---------------------------------------------------- */
    public static function verify_member($entry, $form): void
    {
        if ((int) ($form['id'] ?? 0) !== self::FORM_ID_VERIFY) {
            return;
        }

        $need_id = self::get_need_id_for_form($entry, self::FORM_ID_VERIFY);
        if (!$need_id) {
            error_log('OC Form4: need_id not found');
            return;
        }

        // Completion log
        $log = get_post_meta($need_id, 'completion_log', true);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'time'     => current_time('mysql'),
            'user_id'  => (int) get_current_user_id(),
            'entry_id' => isset($entry['id']) ? (int) $entry['id'] : 0,
        ];

        update_post_meta($need_id, 'completion_log', $log);

        // 🔒 FINAL STATUS TRANSITION — FIXED
        self::set_need_status_by_slug($need_id, ['met', 'fulfilled', 'closed']);
    }

    /* ----------------------------------------------------
     * Helpers
     * ---------------------------------------------------- */
    private static function set_need_status_by_slug(int $need_id, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, 'need_status');
            if ($term && !is_wp_error($term)) {
                wp_set_post_terms($need_id, [$term->term_id], 'need_status', false);
                error_log("OC Status: Need #{$need_id} set to {$slug}");
                return;
            }
        }

        error_log("OC Status: No matching status found for Need #{$need_id}");
    }

    private static function get_need_id_for_form($entry, int $form_id): int
    {
        $field_id = $form_id === self::FORM_ID_HELP
            ? self::FORM2_NEED_ID_FIELD
            : self::FORM4_NEED_ID_FIELD;

        if ($field_id > 0) {
            $id = absint(rgar($entry, (string) $field_id));
            if ($id && get_post_type($id) === 'need') {
                return $id;
            }
        }

        foreach ($entry as $val) {
            $id = absint($val);
            if ($id && get_post_type($id) === 'need') {
                return $id;
            }
        }

        return 0;
    }
}
