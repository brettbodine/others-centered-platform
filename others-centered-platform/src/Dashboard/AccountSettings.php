<?php

namespace OthersCentered\Platform\Dashboard;

class AccountSettings
{
    /**
     * Register shortcode [oc_account_settings]
     */
    public static function register(): void
    {
        add_shortcode('oc_account_settings', [self::class, 'render']);
    }

    /**
     * Handle POST + render form
     */
    public static function render(): string
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to manage your account settings.</p>';
        }

        $user_id = get_current_user_id();
        $message = '';

        // --------------------
        // Handle POST
        // --------------------
        if (
            isset($_POST['oc_account_settings_nonce']) &&
            wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['oc_account_settings_nonce'])),
                'oc_account_settings'
            )
        ) {
            $zip = isset($_POST['oc_zip'])
                ? sanitize_text_field(wp_unslash($_POST['oc_zip']))
                : '';

            $notify = isset($_POST['oc_notify_email']) ? 'yes' : 'no';

            update_user_meta($user_id, 'oc_zip', $zip);
            update_user_meta($user_id, 'oc_notify_email', $notify);

            $message = '<div class="oc-account-settings-message">Your settings have been updated.</div>';
        }

        // Load saved values
        $zip     = get_user_meta($user_id, 'oc_zip', true);
        $notify  = get_user_meta($user_id, 'oc_notify_email', true);
        $checked = ($notify !== 'no'); // default = yes

        ob_start();
        ?>

        <form method="post" class="oc-account-settings-form">

            <?php
            // Show success message
            echo $message;

            // Nonce field
            wp_nonce_field('oc_account_settings', 'oc_account_settings_nonce');
            ?>

            <h3><?php esc_html_e('Others Centered Account Settings', 'others-centered-platform'); ?></h3>

            <p>
                <label>
                    <span><?php esc_html_e('Your ZIP code', 'others-centered-platform'); ?></span><br>
                    <input type="text"
                           name="oc_zip"
                           value="<?php echo esc_attr($zip); ?>"
                           placeholder="e.g. 68128"
                           style="max-width:200px;">
                </label>
            </p>

            <p>
                <label>
                    <input type="checkbox"
                           name="oc_notify_email"
                           value="yes"
                           <?php checked($checked); ?>>
                    <?php esc_html_e('Email me when my needs go live, are matched, or fulfilled.', 'others-centered-platform'); ?>
                </label>
            </p>

            <p>
                <button type="submit" class="oc-btn">
                    <?php esc_html_e('Save settings', 'others-centered-platform'); ?>
                </button>
            </p>

        </form>

        <?php
        return ob_get_clean();
    }
}
