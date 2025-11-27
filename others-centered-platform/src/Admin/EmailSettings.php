<?php

namespace OthersCentered\Platform\Admin;

use OthersCentered\Platform\Emails\Templates;

class EmailSettings
{
    /**
     * Register the admin menu page.
     */
    public static function register_menu(): void
    {
        add_menu_page(
            __('OC Email Templates', 'others-centered-platform'),
            __('OC Email Templates', 'others-centered-platform'),
            'manage_options',
            'ocp-email-templates',
            [self::class, 'page'],
            'dashicons-email-alt2',
            58
        );
    }

    /**
     * Render Email Templates admin page.
     */
    public static function page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'others-centered-platform'));
        }

        $templates = Templates::config();

        // Determine which template is selected
        $current_key = isset($_GET['template'])
            ? sanitize_key(wp_unslash($_GET['template']))
            : '';

        if (! isset($templates[$current_key])) {
            $current_key = (string) array_key_first($templates);
        }

        /**
         * -------------------------------------------------
         * SAVE HANDLER
         * -------------------------------------------------
         */
        if (
            isset($_POST['ocp_template_key'], $_POST['ocp_subject'], $_POST['ocp_body']) &&
            check_admin_referer('ocp_save_email_template', 'ocp_nonce')
        ) {
            $key = sanitize_text_field(wp_unslash($_POST['ocp_template_key']));

            if (isset($templates[$key])) {
                $subject = sanitize_text_field(wp_unslash($_POST['ocp_subject']));
                $body    = wp_kses_post(wp_unslash($_POST['ocp_body']));

                update_option("oc_email_{$key}_subject", $subject);
                update_option("oc_email_{$key}_body",    $body);

                echo '<div class="notice notice-success"><p>' .
                     esc_html__('Email template updated.', 'others-centered-platform') .
                     '</p></div>';
            }
        }

        /**
         * -------------------------------------------------
         * TEST EMAIL HANDLER
         * -------------------------------------------------
         */
        if (
            isset($_POST['ocp_send_test'], $_POST['ocp_test_email'], $_POST['ocp_template_key']) &&
            check_admin_referer('ocp_send_test_email', 'ocp_test_nonce')
        ) {
            $test_email = sanitize_email(wp_unslash($_POST['ocp_test_email']));
            $key        = sanitize_text_field(wp_unslash($_POST['ocp_template_key']));

            if ($test_email && isset($templates[$key])) {
                $sent = self::send_test_email($key, $test_email);

                if ($sent) {
                    echo '<div class="notice notice-success"><p>' .
                         esc_html__('Test email sent successfully.', 'others-centered-platform') .
                         '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' .
                         esc_html__('Failed to send test email. Check your email settings.', 'others-centered-platform') .
                         '</p></div>';
                }
            }
        }

        // Load current template values (after any save)
        $current  = Templates::get($current_key);
        $subject  = $current['subject'] ?? '';
        $body     = $current['body'] ?? '';

        $current_user   = wp_get_current_user();
        $default_test_to = $current_user && $current_user->user_email ? $current_user->user_email : get_option('admin_email');

        ?>
        <div class="wrap ocp-email-templates-page">
            <h1><?php esc_html_e('OC Email Templates', 'others-centered-platform'); ?></h1>

            <p>
                <?php esc_html_e(
                    'Configure the emails Others Centered sends when needs go live, are matched, or fulfilled.',
                    'others-centered-platform'
                ); ?>
            </p>

            <hr />

            <h2><?php esc_html_e('Available Tokens', 'others-centered-platform'); ?></h2>
            <p><?php esc_html_e('You can use these tokens in the subject and body. They will be replaced automatically when emails are sent.', 'others-centered-platform'); ?></p>

            <table class="widefat striped" style="max-width:700px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Token', 'others-centered-platform'); ?></th>
                        <th><?php esc_html_e('Replacement', 'others-centered-platform'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>{need_link}</code></td>
                        <td><?php esc_html_e('Permalink of the need', 'others-centered-platform'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{need_title}</code></td>
                        <td><?php esc_html_e('Title of the need', 'others-centered-platform'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{amount}</code></td>
                        <td><?php esc_html_e('Amount entered in the verification form (Form 4), or requested amount if none confirmed', 'others-centered-platform'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{edit_link}</code></td>
                        <td><?php esc_html_e('Admin Edit URL for the need', 'others-centered-platform'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{helper_name_optional}</code></td>
                        <td><?php esc_html_e('Helper’s name if available (blank if not)', 'others-centered-platform'); ?></td>
                    </tr>
                    <tr>
                        <td><code>{need_id}</code></td>
                        <td><?php esc_html_e('Numeric ID of the need post', 'others-centered-platform'); ?></td>
                    </tr>
                </tbody>
            </table>

            <h2 style="margin-top:2em;"><?php esc_html_e('Available Templates', 'others-centered-platform'); ?></h2>

            <ul>
                <?php foreach ($templates as $key => $tpl) : ?>
                    <li>
                        <a href="<?php echo esc_url( add_query_arg( 'template', $key, menu_page_url( 'ocp-email-templates', false ) ) ); ?>">
                            <code><?php echo esc_html($key); ?></code>
                        </a>
                        &mdash; <?php echo esc_html($tpl['label']); ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <hr />

            <h2><?php esc_html_e('Edit Template', 'others-centered-platform'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('ocp_save_email_template', 'ocp_nonce'); ?>

                <input type="hidden" name="ocp_template_key" value="<?php echo esc_attr($current_key); ?>" />

                <p>
                    <strong><?php esc_html_e('Editing template:', 'others-centered-platform'); ?></strong>
                    <code><?php echo esc_html($current_key); ?></code>
                    &mdash;
                    <?php echo esc_html($templates[$current_key]['label']); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ocp_subject"><?php esc_html_e('Subject', 'others-centered-platform'); ?></label>
                        </th>
                        <td>
                            <input type="text"
                                   class="regular-text"
                                   id="ocp_subject"
                                   name="ocp_subject"
                                   value="<?php echo esc_attr($subject); ?>" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="ocp_body"><?php esc_html_e('Body', 'others-centered-platform'); ?></label>
                        </th>
                        <td>
                            <?php
                            wp_editor(
                                $body,
                                'ocp_body',
                                [
                                    'textarea_name' => 'ocp_body',
                                    'textarea_rows' => 12,
                                ]
                            );
                            ?>
                            <p class="description">
                                <?php esc_html_e('Simple HTML is supported. Line breaks will be converted to <br> in the email.', 'others-centered-platform'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Save Template', 'others-centered-platform')); ?>
            </form>

            <hr />

            <h2><?php esc_html_e('Send Test Email', 'others-centered-platform'); ?></h2>
            <p>
                <?php esc_html_e('Sends this template using the most recent Need as sample data.', 'others-centered-platform'); ?>
            </p>

            <form method="post" style="max-width:480px;">
                <?php wp_nonce_field('ocp_send_test_email', 'ocp_test_nonce'); ?>
                <input type="hidden" name="ocp_template_key" value="<?php echo esc_attr($current_key); ?>" />

                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="ocp_test_email"><?php esc_html_e('Send test to', 'others-centered-platform'); ?></label>
                        </th>
                        <td>
                            <input type="email"
                                   name="ocp_test_email"
                                   id="ocp_test_email"
                                   class="regular-text"
                                   value="<?php echo esc_attr($default_test_to); ?>" />
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Send Test Email', 'others-centered-platform'), 'secondary', 'ocp_send_test'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Build sample replacement data from the latest Need and send a test email.
     */
    protected static function send_test_email(string $key, string $to): bool
    {
        $need = get_posts([
            'post_type'      => 'need',
            'post_status'    => 'any',
            'numberposts'    => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $need_post = $need[0] ?? null;

        $replacements = [];

        if ($need_post) {
            $need_id    = $need_post->ID;
            $title      = get_the_title($need_id);
            $link       = get_permalink($need_id);
            $edit_link  = get_edit_post_link($need_id);

            $amount_confirmed = get_post_meta($need_id, 'amount_confirmed', true);
            $amount_requested = get_post_meta($need_id, 'amount_requested', true);
            $amount           = $amount_confirmed !== '' ? $amount_confirmed : $amount_requested;

            $helper_name = get_post_meta($need_id, 'helper_name', true);

            $replacements = [
                '{need_id}'              => (string) $need_id,
                '{need_title}'           => (string) $title,
                '{need_link}'            => (string) $link,
                '{edit_link}'            => (string) $edit_link,
                '{amount}'               => $amount !== '' ? (string) $amount : '',
                '{helper_name_optional}' => $helper_name ? ' ' . $helper_name : '',
            ];
        }

        return Templates::send($key, $to, $replacements);
    }
}
