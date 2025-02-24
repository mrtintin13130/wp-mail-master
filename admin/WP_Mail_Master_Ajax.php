<?php

// admin/WP_Mail_Master_Ajax.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Ajax
{
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        add_action('wp_ajax_send_test_email', array($this, 'send_test_email'));
        add_action('wp_ajax_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_preview_template', array($this, 'ajax_preview_template'));
        add_action('wp_ajax_select_template', array($this, 'ajax_select_template'));
    }

    public function send_test_email()
    {
        try {
            // Verify nonce
            if (!check_ajax_referer('wp_mail_master_nonce', 'nonce', false)) {
                throw new \Exception(__('Security check failed.', 'wp-mail-master'));
            }

            // Verify capabilities
            if (!current_user_can('manage_options')) {
                throw new \Exception(__('You do not have permission to perform this action.', 'wp-mail-master'));
            }

            // Sanitize and validate email
            $email_address = isset($_POST['test_email_address']) ?
                sanitize_email($_POST['test_email_address']) : '';

            if (empty($email_address) || !is_email($email_address)) {
                throw new \Exception(__('Invalid email address.', 'wp-mail-master'));
            }

            // Rate limiting
            $this->check_rate_limit();

            // Send the test email
            $emailer = new WP_Mail_Master_Emailer($this->plugin_name, $this->version);
            $result = $emailer->send_test_email($email_address);

            if (!$result) {
                throw new \Exception(__('Failed to send test email.', 'wp-mail-master'));
            }

            wp_send_json_success(array(
                'message' => __('Test email sent successfully.', 'wp-mail-master')
            ));

        } catch (\Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Implement rate limiting for test emails
     */
    private function check_rate_limit()
    {
        $user_id = get_current_user_id();
        $transient_key = 'wp_mail_master_test_email_' . $user_id;
        $limit_count = get_transient($transient_key);

        if (false === $limit_count) {
            set_transient($transient_key, 1, HOUR_IN_SECONDS);
        } else if ($limit_count >= 15) { // Max 5 test emails per hour
            throw new \Exception(__('Test email limit reached. Please try again later.', 'wp-mail-master'));
        } else {
            set_transient($transient_key, $limit_count + 1, HOUR_IN_SECONDS);
        }
    }

    public function ajax_save_template()
    {
        check_ajax_referer('save_template', 'template_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wp-mail-master')));
            return;
        }

        $template_id = sanitize_text_field($_POST['template_id']);
        $template_data = wp_unslash($_POST['template']);

        try {
            // Validate template using our new validator
            $validator = new WP_Mail_Master_Template_Validator();
            $sanitized_data = $validator->validate_template($template_data);

            // Get existing templates
            $templates = get_option('wp_mail_master_templates', array());

            // Save the template
            $templates[$template_id] = $sanitized_data;

            // Update templates in database
            $result = update_option('wp_mail_master_templates', $templates);

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Template saved successfully.', 'wp-mail-master'),
                    'template_id' => $template_id
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to save template.', 'wp-mail-master')));
            }
        } catch (WP_Mail_Master_Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => __('An unexpected error occurred.', 'wp-mail-master')));
        }
    }

    public function ajax_delete_template()
    {
        check_ajax_referer('delete_template');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $template_id = sanitize_text_field($_POST['template_id']);

        // Don't allow deleting preset templates
        $protected_templates = ['simple', 'modern', 'business', 'default'];
        if (in_array($template_id, $protected_templates)) {
            wp_send_json_error(array('message' => __('Cannot delete preset templates.', 'wp-mail-master')));
            return;
        }

        $templates = get_option('wp_mail_master_templates', array());

        if (isset($templates[$template_id])) {
            unset($templates[$template_id]);

            // If this was the selected template, reset to 'simple'
            $selected_template = get_option('wp_mail_master_selected_template', '');
            if ($selected_template === $template_id) {
                update_option('wp_mail_master_selected_template', 'simple');
            }

            if (update_option('wp_mail_master_templates', $templates)) {
                wp_send_json_success();
            }
        }

        wp_send_json_error();
    }

    public function ajax_select_template()
    {
        check_ajax_referer('select_template_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('Permission denied.', 'wp-mail-master')
            ]);
            return;
        }

        $template_id = sanitize_text_field($_POST['template_id']);

        // Get existing templates
        $templates = get_option('wp_mail_master_templates', array());

        // Check if the template exists
        if (!isset($templates[$template_id])) {
            wp_send_json_error([
                'message' => __('Template not found.', 'wp-mail-master')
            ]);
            return;
        }

        // Just update the selected template ID
        $result = update_option('wp_mail_master_selected_template', $template_id);

        if ($result) {
            wp_send_json_success([
                'message' => __('Template selected successfully.', 'wp-mail-master'),
                'template_id' => $template_id
            ]);
        } else {
            error_log('WP Mail Master: Failed to update selected template. Template ID: ' . $template_id);
            wp_send_json_error([
                'message' => __('Failed to select template.', 'wp-mail-master')
            ]);
        }
    }

    public function ajax_send_test_email()
    {
        check_ajax_referer('send_test_email');

        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }

        $email = sanitize_email($_POST['email']);
        $template_id = sanitize_text_field($_POST['template_id']);

        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Invalid email address.', 'wp-mail-master')));
            return;
        }

        $templates = get_option('wp_mail_master_templates', array());
        if (!isset($templates[$template_id])) {
            wp_send_json_error(array('message' => __('Template not found.', 'wp-mail-master')));
            return;
        }

        $template = $templates[$template_id];

        // Create test content
        $template_subject = isset($template['subject']) ? (string)$template['subject'] : '';
        $site_name = (string)get_bloginfo('name');
        $current_date = (string)date_i18n(get_option('date_format'));
        
        $subject = str_replace(
            array('{site_name}', '{date}'),
            array($site_name, $current_date),
            $template_subject
        );

        $template_content = isset($template['content']) ? (string)$template['content'] : '';
        $site_url = (string)get_bloginfo('url');
        $current_year = (string)date('Y');
        $test_message = (string)__('This is a test email from your website.', 'wp-mail-master');
        
        $content = str_replace(
            array(
                '{site_name}',
                '{site_url}',
                '{email_title}',
                '{year}',
                '{date}',
                '{email_content}'
            ),
            array(
                $site_name,
                $site_url,
                $subject,
                $current_year,
                $current_date,
                $test_message
            ),
            $template_content
        );

        $headers = array('Content-Type: text/html; charset=UTF-8');

        if (wp_mail($email, $subject, $content, $headers)) {
            wp_send_json_success(array(
                'message' => __('Test email sent successfully!', 'wp-mail-master')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to send test email.', 'wp-mail-master')
            ));
        }
    }
}
