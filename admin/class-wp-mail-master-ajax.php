<?php

// admin/class-wp-mail-master-ajax.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Ajax {
    public function __construct() {
        add_action('wp_ajax_send_test_email', array($this, 'send_test_email'));
    }

    public function send_test_email() {
        // Check if the current user has the capability to manage options
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You are not allowed to send test emails.', 'wp-mail-master')));
            wp_die();
        }

        // Verify the nonce for security
        check_ajax_referer('send_test_email_nonce', 'nonce');

        // Get the email address from the request
        $email_address = isset($_POST['test_email_address']) ? sanitize_email($_POST['test_email_address']) : '';

        if (empty($email_address) || !is_email($email_address)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'wp-mail-master')));
            wp_die();
        }

        // Send the test email
        $emailer = new WP_Mail_Master_Emailer();
        $result = $emailer->send_test_email($email_address);

        if ($result) {
            wp_send_json_success(array('message' => __('Test email sent successfully.', 'wp-mail-master')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email.', 'wp-mail-master')));
        }

        wp_die();
    }
}
