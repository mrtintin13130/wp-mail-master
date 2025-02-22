<?php

// includes/WP_Mail_Master_Emailer.php

namespace Martho\WpMailMaster;

use Martho\WpMailMaster\Services\EmailServiceFactory;

class WP_Mail_Master_Emailer
{
    private $service;
    private $templates;
    private $spam_protection;

    public function __construct()
    {
        $options = get_option('wp_mail_master_options', []);
        $service_type = isset($options['email_service']) ? $options['email_service'] : 'smtp';
        $this->service = EmailServiceFactory::create($service_type, $options);
        $this->templates = new WP_Mail_Master_Templates();
        $this->spam_protection = new WP_Mail_Master_Spam_Protection();
    }

    public function send_email($to, $subject, $message, $headers = '', $attachments = array(), $template_id = 'default')
    {
        try {
            error_log('WP Mail Master - Starting send_email');
            error_log('WP Mail Master - Input validation for recipient: ' . print_r($to, true));

            // Normalize $to to array and validate each email
            $to_emails = is_array($to) ? $to : array($to);
            $valid_emails = array();
            
            foreach ($to_emails as $email) {
                error_log('WP Mail Master - Validating email: ' . $email);
                if (is_string($email) && is_email($email)) {
                    $valid_emails[] = $email;
                }
            }
            
            if (empty($valid_emails)) {
                error_log('WP Mail Master - No valid emails found');
                $error_info = 'No valid recipient email addresses provided';
                $this->log_email($to, $subject, $message, 'failed', $error_info);
                return false;
            }

            error_log('WP Mail Master - Valid emails: ' . print_r($valid_emails, true));

            // Prepare email data for spam check
            $email_data = [
                'to' => $valid_emails,
                'subject' => $subject,
                'message' => $message,
                'honeypot_field' => isset($_POST['wp_mail_master_hp']) ? 'wp_mail_master_hp' : '',
                'honeypot_field_value' => isset($_POST['wp_mail_master_hp']) ? $_POST['wp_mail_master_hp'] : ''
            ];

            try {
                // Run spam protection checks
                do_action('wp_mail_master_before_send', $email_data);
            } catch (WP_Mail_Master_Exception $e) {
                $error_info = 'Spam protection: ' . $e->getMessage();
                $this->log_email(implode(',', $valid_emails), $subject, $message, 'blocked', $error_info);
                throw $e;
            }
            
            // If no template_id is provided, use the selected template
            if ($template_id === null) {
                $template_id = get_option('wp_mail_master_selected_template', 'simple');
            }
    
            // Get templates
            $templates = get_option('wp_mail_master_templates', array());
            
            // Get the template (fallback to simple if selected doesn't exist)
            $template = isset($templates[$template_id]) ? $templates[$template_id] : $templates['simple'];

            // Replace variables in subject
            $variables = array(
                '{site_name}' => get_bloginfo('name'),
                '{site_url}' => get_bloginfo('url'),
                '{year}' => date('Y'),
                '{date}' => date_i18n(get_option('date_format')),
                '{email_title}' => $subject,
                '{email_content}' => $message
            );

            // Parse template variables in subject
            $parsed_subject = str_replace(
                array_keys($variables),
                array_values($variables),
                $subject ? $subject : $template['subject']
            );

            // Replace variables in template
            $html_message = str_replace(
                array_keys($variables),
                array_values($variables),
                $template['content']
            );

            // Ensure headers include HTML content type
            if (empty($headers)) {
                $headers = array('Content-Type: text/html; charset=UTF-8');
            } elseif (is_string($headers)) {
                $headers = array($headers, 'Content-Type: text/html; charset=UTF-8');
            } elseif (is_array($headers)) {
                $headers[] = 'Content-Type: text/html; charset=UTF-8';
            }

            // Process the email through the selected service
            $recipients = implode(',', $valid_emails);
            error_log('WP Mail Master - Attempting to send email to: ' . $recipients);
            
            $result = $this->service->send($recipients, $parsed_subject, $html_message, $headers, $attachments);
            error_log('WP Mail Master - Service send result: ' . var_export($result, true));

            if ($result === true) {
                error_log('WP Mail Master - Email sent successfully');
                $this->log_email($recipients, $parsed_subject, $html_message, 'success');
                do_action('wp_mail_master_after_send_success', $email_data);
                return true;
            }

            error_log('WP Mail Master - Email sending failed');
            $error_info = 'Email service failed to send the email';
            $this->log_email($recipients, $parsed_subject, $html_message, 'failed', $error_info);
            return false;

        } catch (WP_Mail_Master_Exception $e) {
            error_log('WP Mail Master - Exception in send_email: ' . $e->getMessage());
            error_log('WP Mail Master - Exception trace: ' . $e->getTraceAsString());
            $error_info = $e->getMessage();
            $this->log_email(
                is_array($to) ? implode(',', $to) : $to,
                $subject,
                $message,
                'failed',
                $error_info
            );
            
            do_action('wp_mail_master_send_error', $e);
            error_log('WP Mail Master Error: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log('WP Mail Master - Unexpected error in send_email: ' . $e->getMessage());
            error_log('WP Mail Master - Error trace: ' . $e->getTraceAsString());
            $error_info = 'Unexpected error: ' . $e->getMessage();
            $this->log_email(
                is_array($to) ? implode(',', $to) : $to,
                $subject,
                $message,
                'failed',
                $error_info
            );
            
            error_log('WP Mail Master Unexpected Error: ' . $e->getMessage());
            return false;
        }
    }

    public function log_email($to, $subject, $message, $status, $error_info = null)
    {
        global $wpdb;

        if (!$wpdb) {
            error_log("WordPress database object is not available.");
            return;
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_logs';
        
        // Handle array input for $to
        if (is_array($to)) {
            $to = implode(',', $to);
        }

        // Ensure message is a string
        if (is_array($message)) {
            $message = wp_json_encode($message);
        }

        // Add debug log
        error_log(sprintf(
            "WP Mail Master - Logging email: to=%s, subject=%s, status=%s, error=%s",
            $to,
            $subject,
            $status,
            $error_info ?? 'none'
        ));

        $data = array(
            'time' => current_time('mysql'),
            'to_email' => $to,
            'subject' => $subject,
            'message' => $message,
            'status' => $status,
            'error_info' => $error_info
        );

        // Ensure data types are correct
        $data = array_map(function($value) {
            if (is_null($value)) return null;
            return (string)$value;
        }, $data);

        $result = $wpdb->insert($table_name, $data);
        
        if ($result === false) {
            error_log("WP Mail Master - Failed to log email: " . $wpdb->last_error);
        }
    }
    public function send_test_email($to)
    {
        $site_name = get_bloginfo('name');
        $subject = 'Test Email from ' . $site_name;
        $message = '<p>This is a test email from WP Mail Master plugin.</p>' .
                  '<p>If you are reading this message, it means the email was delivered successfully and the plugin is working correctly.</p>' .
                  '<p>You can now use WP Mail Master to send emails from your WordPress site with confidence.</p>';
        return $this->send_email($to, $subject, $message);
    }
}
