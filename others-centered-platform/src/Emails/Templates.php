<?php

namespace OthersCentered\Platform\Emails;

class Templates
{
    /**
     * All email templates & default content.
     */
    public static function config(): array
    {
        return [
            'admin_new_need' => [
                'label'   => 'Admin – New Need Submitted',
                'subject' => 'New Need submitted (Pending Review)',
                'body'    =>
                    "A new Need is awaiting review.\n\n" .
                    "Title: {need_title}\n\n" .
                    "Edit:\n{edit_link}\n",
            ],
     
            'need_live_member' => [
                'label'   => 'Need goes live (Member)',
                'subject' => 'Your need is now live on Others Centered',
                'body'    =>
                    "Hi{helper_name_optional},\n\n" .
                    "Your need \"{need_title}\" is now live on Others Centered.\n\n" .
                    "You can view it here:\n{need_link}\n\n" .
                    "Helpers in your area can now see this need and reach out.\n\n" .
                    "– Others Centered",
            ],

            'matched_member' => [
                'label'   => 'Need matched (Member)',
                'subject' => 'Someone has offered to help your need',
                'body'    =>
                    "Hi,\n\n" .
                    "Someone has reached out about your need \"{need_title}\" on Others Centered.\n\n" .
                    "You should receive a separate email with their message (sent anonymously).\n\n" .
                    "View your need here:\n{need_link}\n\n" .
                    "– Others Centered",
            ],

            'fulfilled_member' => [
                'label'   => 'Need fulfilled (Member)',
                'subject' => 'Your need has been marked fulfilled',
                'body'    =>
                    "Hi,\n\n" .
                    "Your need \"{need_title}\" has been marked fulfilled.\n" .
                    "Confirmed amount: {amount}\n\n" .
                    "You can review it here:\n{need_link}\n\n" .
                    "– Others Centered",
            ],

            'helper_thanks' => [
                'label'   => 'Thank-you (Helper)',
                'subject' => 'Thank you for helping on Others Centered',
                'body'    =>
                    "Hi{helper_name_optional},\n\n" .
                    "Thank you for helping with \"{need_title}\" on Others Centered.\n" .
                    "Your generosity made a real difference.\n\n" .
                    "– Others Centered",
            ],

            'admin_matched' => [
                'label'   => 'Admin Notice – Need Matched',
                'subject' => 'A need has been matched on Others Centered',
                'body'    =>
                    "Need #{need_id} ({need_title}) has been matched.\n\n" .
                    "Edit the need:\n{edit_link}\n",
            ],

            'admin_fulfilled' => [
                'label'   => 'Admin Notice – Need Fulfilled',
                'subject' => 'A need has been fulfilled on Others Centered',
                'body'    =>
                    "Need #{need_id} ({need_title}) has been marked fulfilled.\n\n" .
                    "Edit the need:\n{edit_link}\n",
            ],
        ];
    }

    /**
     * Load a template (subject/body) with saved overrides.
     */
    public static function get(string $key): array
    {
        $all = self::config();

        if (! isset($all[$key])) {
            return ['subject' => '', 'body' => ''];
        }

        $default = $all[$key];

        return [
            'subject' => get_option("oc_email_{$key}_subject", $default['subject']),
            'body'    => get_option("oc_email_{$key}_body",    $default['body']),
        ];
    }

    /**
     * Send an email with replacements as simple HTML.
     * Tokens like {need_title} and {need_link} are replaced before send.
     */
    public static function send(string $template_key, string $to, array $replacements = []): bool
    {
        if (empty($to)) {
            return false;
        }

        $tpl = self::get($template_key);

        $subject = $tpl['subject'] ?? '';
        $body    = $tpl['body'] ?? '';

        // Replace tokens
        if (! empty($replacements)) {
            $subject = strtr($subject, $replacements);
            $body    = strtr($body, $replacements);
        }

        // Simple HTML: convert line breaks to <br>
        $body_html = nl2br($body);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        return wp_mail($to, $subject, $body_html, $headers);
    }
}
