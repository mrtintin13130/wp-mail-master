<?php

// includes/WP_Mail_Master_Emailer.php

namespace Martho\WpMailMaster;

use Martho\WpMailMaster\Services\EmailServiceFactory;

class WP_Mail_Master_Emailer
{
    private $service;
    private $templates;
    private $spam_protection;
    private $settings;
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings = new WP_Mail_Master_Settings($plugin_name, $version);
        $options = get_option('wp_mail_master_options', []);
        $service_type = isset($options['email_service']) ? $options['email_service'] : 'smtp';
        
        // Try to create the service, but don't fail if it can't be created
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
                return false;
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

            // If we don't have a valid service, fall back to wp_mail
            if (!$this->service) {
                error_log('WP Mail Master - No email service configured, falling back to wp_mail');
                return wp_mail(implode(',', $valid_emails), $parsed_subject, $html_message, $headers, $attachments);
            }

            // Process the email through the selected service
            $recipients = implode(',', $valid_emails);
            error_log('WP Mail Master - Attempting to send email to: ' . $recipients);
            
            try {
                $result = $this->service->send($recipients, $parsed_subject, $html_message, $headers, $attachments);
                error_log('WP Mail Master - Service send result: ' . var_export($result, true));

                if ($result === true) {
                    error_log('WP Mail Master - Email sent successfully');
                    $this->log_email($recipients, $parsed_subject, $html_message, 'success');
                    do_action('wp_mail_master_after_send_success', $email_data);
                    return true;
                }
            } catch (WP_Mail_Master_Exception $e) {
                // If service fails due to configuration, fall back to wp_mail
                if ($e->getCode() === WP_Mail_Master_Exception::INVALID_CONFIG_ERROR) {
                    error_log('WP Mail Master - Service not configured, falling back to wp_mail');
                    return wp_mail(implode(',', $valid_emails), $parsed_subject, $html_message, $headers, $attachments);
                }
                throw $e;
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

        // Handle array input for $to
        if (is_array($to)) {
            $to = implode(',', $to);
        }

        // Create a summary of the message instead of storing the full HTML
        if (is_string($message)) {
            // Strip HTML tags
            $stripped_message = wp_strip_all_tags($message);
            // Get first 150 characters
            $message_summary = substr($stripped_message, 0, 150);
            if (strlen($stripped_message) > 150) {
                $message_summary .= '...';
            }
        } else if (is_array($message)) {
            $message_summary = 'Array message data';
        } else {
            $message_summary = 'Invalid message format';
        }

        // Add debug log
        error_log(sprintf(
            "WP Mail Master - Logging email: to=%s, subject=%s, status=%s, error=%s",
            $to,
            $subject,
            $status,
            $error_info ?? 'none'
        ));

        // Map old statuses to new ones with better clarity
        $status_map = [
            'sent' => 'success',
            'failed' => 'error',
            'blocked' => 'blocked',  // Changed from 'warning' to 'blocked'
            'success' => 'success',
            'error' => 'error'
        ];
        $mapped_status = isset($status_map[$status]) ? $status_map[$status] : $status;

        // Store the full message in metadata for reference
        $metadata = [
            'to_email' => $to,
            'subject' => $subject,
            'full_message' => $message,
            'status_details' => [
                'original_status' => $status,
                'mapped_status' => $mapped_status,
                'timestamp' => current_time('mysql')
            ]
        ];

        if ($error_info) {
            $metadata['error_info'] = $error_info;
        }

        // Get logs instance
        $logs = new WP_Mail_Master_Logs($this->plugin_name, $this->version, false);

        // Determine level based on status
        $level = match($mapped_status) {
            'success' => 'info',
            'error' => 'error',
            'blocked' => 'warning',
            default => 'info'
        };

        // Log with new system using the summary as the main message
        $logs->log(
            'email',
            $level,
            $mapped_status,
            $message_summary,
            null,
            $metadata
        );
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

    public function override_wp_mail($null, $to = '', $subject = '', $message = '', $headers = '', $attachments = array())
    {
        try {
            error_log('WP Mail Master - Mail parameters received:');
            error_log('To: ' . print_r($to, true));
            error_log('Subject: ' . print_r($subject, true));
            error_log('Headers: ' . print_r($headers, true));

            // Handle case where all parameters are passed as a single array
            if (is_array($to) && isset($to['to'])) {
                $email_data = $to;
                $to = $email_data['to'];
                $subject = $email_data['subject'];
                $message = $email_data['message'];
                $headers = $email_data['headers'];
                $attachments = isset($email_data['attachments']) ? $email_data['attachments'] : array();
            }

            // Handle case where 'to' is an array of email addresses
            if (is_array($to) && isset($to[0])) {
                $to = $to[0]; // Take the first email address
            }

            // Ensure we have required fields
            if (empty($to)) {
                error_log('WP Mail Master - No recipient specified');
                return null;
            }

            // Send email using our service
            $result = $this->send_email($to, $subject, $message, $headers, $attachments);
            
            // Return true if successful (prevents WordPress from sending), null if failed (lets WordPress try)
            return $result ? true : null;

        } catch (\Exception $e) {
            error_log('WP Mail Master - Error: ' . $e->getMessage());
            return null;
        }
    }

    public function handle_wp_mail_failed($wp_error)
    {
        if (!($wp_error instanceof \WP_Error)) {
            return;
        }

        $error_data = $wp_error->get_error_data();
        
        // Extract email details from error data
        $to = isset($error_data['to']) ? $error_data['to'] : '';
        $subject = isset($error_data['subject']) ? $error_data['subject'] : '';
        $message = isset($error_data['message']) ? $error_data['message'] : '';
        
        // Get error message
        $error_message = $wp_error->get_error_message();
        
        error_log('WP Mail Master - Mail failure: ' . $error_message);
        
        // Log the failure
        $this->log_email(
            $to,
            $subject,
            $message,
            'failed',
            $error_message
        );
        
        do_action('wp_mail_master_send_error', $wp_error);
    }
}
