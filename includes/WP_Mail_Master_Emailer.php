<?php

// includes/WP_Mail_Master_Emailer.php

namespace Martho\WpMailMaster;

use Martho\WpMailMaster\Services\EmailServiceFactory;

class WP_Mail_Master_Emailer
{
    private $service;
    private $templates;

    public function __construct()
    {
        $options = get_option('wp_mail_master_options', []);
        $service_type = isset($options['email_service']) ? $options['email_service'] : 'smtp';
        $this->service = EmailServiceFactory::create($service_type, $options);
        $this->templates = new WP_Mail_Master_Templates();
    }

    public function send_email($to, $subject, $message, $headers = '', $attachments = array(), $template_id = 'default')
    {
        try {
            // If no template_id is provided, use the selected template
            if ($template_id === null) {
                $template_id = get_option('wp_mail_master_selected_template', 'simple');
            }
    
            // Get templates
            $templates = get_option('wp_mail_master_templates', array());
            
            // Get the template (fallback to simple if selected doesn't exist)
            $template = isset($templates[$template_id]) ? $templates[$template_id] : $templates['simple'];

            // Validate inputs
            if (empty($to) || !is_email($to)) {
                throw new WP_Mail_Master_Exception(
                    __('Invalid recipient email address.', 'wp-mail-master'),
                    WP_Mail_Master_Exception::VALIDATION_ERROR,
                    ['to' => $to]
                );
            }

            // Replace variables in subject
            $variables = array(
                '{site_name}' => get_bloginfo('name'),
                '{site_url}' => get_bloginfo('url'),
                '{year}' => date('Y'),
                '{date}' => date_i18n(get_option('date_format')),
                '{email_title}' => $subject // Use the passed subject as email_title
            );

            // Parse template variables in subject
            $parsed_subject = str_replace(
                array_keys($variables),
                array_values($variables),
                $subject ? $subject : $template['subject']
            );

            // Replace variables in template
            $html_message = str_replace(
                array_keys($variables) + ['{email_content}'],
                array_values($variables) + [$message],
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

            // Send email using the selected service
            $result = $this->service->send($to, $parsed_subject, $html_message, $headers, $attachments);

            // Log the result
            $this->log_email(
                $to,
                $parsed_subject,
                $html_message,
                $result ? 'success' : 'error'
            );

            return $result;

        } catch (\Exception $e) {
            WP_Mail_Master_Error_Handler::getInstance()->handleException($e);
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

        // Add debug log
        error_log("Logging email: to=$to, subject=$subject, status=$status");

        $wpdb->insert(
            $table_name,
            array(
                'time' => current_time('mysql'),
                'to_email' => $to,
                'subject' => $subject,
                'message' => $message,
                'status' => $status,
                'error_info' => $error_info
            )
        );
    }
    public function send_test_email($to)
    {
        $subject = 'Test Email from {site_name}';
        $message = '<p>This is a test email from WP Mail Master plugin.</p>';
        return $this->send_email($to, $subject, $message);
    }

    // private function get_email_template($subject, $message)
    // {
    //     return '
    // <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    // <html xmlns="http://www.w3.org/1999/xhtml">
    // <head>
    //     <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    // </head>
    // <body style="margin: 0; padding: 0;">
    //     <table border="0" cellpadding="0" cellspacing="0" width="100%">
    //         <tr>
    //             <td style="padding: 20px 0 30px 0;">
    //                 <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #cccccc;">
    //                     <tr>
    //                         <td align="center" bgcolor="#0073aa" style="padding: 40px 0 30px 0; color: #ffffff; font-size: 28px; font-weight: bold; font-family: Arial, sans-serif;">
    //                             ' . esc_html($subject) . '
    //                         </td>
    //                     </tr>
    //                     <tr>
    //                         <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
    //                             <table border="0" cellpadding="0" cellspacing="0" width="100%">
    //                                 <tr>
    //                                     <td style="color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px;">
    //                                         ' . wp_kses_post($message) . '
    //                                     </td>
    //                                 </tr>
    //                             </table>
    //                         </td>
    //                     </tr>
    //                     <tr>
    //                         <td bgcolor="#f7f7f7" style="padding: 30px 30px;">
    //                             <table border="0" cellpadding="0" cellspacing="0" width="100%">
    //                                 <tr>
    //                                     <td style="color: #777777; font-family: Arial, sans-serif; font-size: 14px;" width="75%">
    //                                         &copy; ' . date('Y') . ' WP Mail Master. All rights reserved.<br/>
    //                                         <a href="' . site_url() . '" style="color: #0073aa; text-decoration: none;">Visit our website</a>
    //                                     </td>
    //                                 </tr>
    //                             </table>
    //                         </td>
    //                     </tr>
    //                 </table>
    //             </td>
    //         </tr>
    //     </table>
    // </body>
    // </html>';
    // }
}
