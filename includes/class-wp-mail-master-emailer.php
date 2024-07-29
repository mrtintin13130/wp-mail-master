<?php

// includes/class-wp-mail-master-emailer.php

namespace Martho\WpMailMaster;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;
use Brevo\Client\Model\SendSmtpEmailSender;
use Brevo\Client\Model\SendSmtpEmailTo;
use SendGrid;
use SendGrid\Mail\Mail;
use Mailjet\Client;
use Mailjet\Resources;

class WP_Mail_Master_Emailer
{
    public function send_email($to, $subject, $message, $headers = '', $attachments = array())
    {
        // error_log("send_email called: to=$to, subject=$subject"); // Debug log

        $options = get_option('wp_mail_master_options');
        $email_service = isset($options['email_service']) ? $options['email_service'] : 'smtp';

        // HTML email template
        $html_template = $this->get_email_template($subject, $message);

        if ($email_service === 'smtp') {
            return $this->send_via_smtp($to, $subject, $html_template, $headers, $attachments);
        } elseif ($email_service === 'brevo') {
            return $this->send_via_brevo($to, $subject, $html_template, $headers, $attachments);
        } elseif ($email_service === 'sendgrid') {
            return $this->send_via_sendgrid($to, $subject, $html_template, $headers, $attachments);
        } elseif ($email_service === 'smtpcom') {
            return $this->send_via_smtpcom($to, $subject, $html_template, $headers, $attachments);
        } elseif ($email_service === 'mailjet') {
            return $this->send_via_mailjet($to, $subject, $html_template, $headers, $attachments);
        } else {
            return false;
        }
    }

    private function send_via_smtp($to, $subject, $html_template, $headers, $attachments)
    {
        $options = get_option('wp_mail_master_options');
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $options['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $options['smtp_username'];
            $mail->Password = $options['smtp_password'];
            $mail->SMTPSecure = $options['smtp_encryption'];
            $mail->Port = $options['smtp_port'];

            // Recipients
            $mail->setFrom($options['from_email'], $options['from_name']);
            $mail->addAddress($to);

            // Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $html_template;

            $mail->send();
            $this->log_email($to, $subject, $html_template, 'success');
            return true;
        } catch (Exception $e) {
            $error_message = 'Mailer Error: ' . $mail->ErrorInfo;
            $this->log_email($to, $subject, $html_template, $error_message);
            error_log($error_message);
            return false;
        }
    }

    private function send_via_brevo($to, $subject, $html_template, $headers, $attachments)
    {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['brevo_api_key']) ? $options['brevo_api_key'] : '';

        if (empty($api_key)) {
            $error_message = 'Brevo API key is missing.';
            $this->log_email($to, $subject, $html_template, $error_message);
            error_log($error_message);
            return false;
        }

        $config = Configuration::getDefaultConfiguration()->setApiKey('api-key', $api_key);

        $apiInstance = new TransactionalEmailsApi(new \GuzzleHttp\Client(), $config);

        $email = new SendSmtpEmail([
            'sender' => new SendSmtpEmailSender(['name' => $options['from_name'], 'email' => $options['from_email']]),
            'to' => [new SendSmtpEmailTo(['email' => $to])],
            'subject' => $subject,
            'htmlContent' => $html_template
        ]);

        try {
            $apiInstance->sendTransacEmail($email);
            $this->log_email($to, $subject, $html_template, 'success');
            return true;
        } catch (\Brevo\Client\ApiException $e) {
            $response_body = $e->getResponseBody();
            $error_message = 'Brevo API Error: ' . $e->getMessage();
            $error_info = 'Response: ' . json_encode($response_body);
            $this->log_email($to, $subject, $html_template, 'error', $error_info);
            error_log($error_message);
            return false;
        }
    }

    private function send_via_sendgrid($to, $subject, $html_template, $headers, $attachments)
    {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['sendgrid_api_key']) ? $options['sendgrid_api_key'] : '';

        if (empty($api_key)) {
            $error_message = 'SendGrid API key is missing.';
            $this->log_email($to, $subject, $html_template, 'error', $error_message);
            error_log($error_message);
            return false;
        }

        $email = new Mail();
        $email->setFrom($options['from_email'], $options['from_name']);
        $email->setSubject($subject);
        $email->addTo($to);
        $email->addContent("text/html", $html_template);

        try {
            $sendgrid = new SendGrid($api_key);
            $response = $sendgrid->send($email);
            if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
                $this->log_email($to, $subject, $html_template, 'success');
                return true;
            } else {
                $error_message = 'SendGrid API Error: ' . $response->statusCode() . ' - ' . $response->body();
                $this->log_email($to, $subject, $html_template, 'error', $error_message);
                error_log($error_message);
                return false;
            }
        } catch (Exception $e) {
            $error_message = 'SendGrid Exception: ' . $e->getMessage();
            $this->log_email($to, $subject, $html_template, 'error', $error_message);
            error_log($error_message);
            return false;
        }
    }

    private function send_via_smtpcom($to, $subject, $html_template, $headers, $attachments)
    {
        $options = get_option('wp_mail_master_options');
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $options['smtpcom_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $options['smtpcom_username'];
            $mail->Password = $options['smtpcom_password'];
            $mail->SMTPSecure = $options['smtpcom_encryption'];
            $mail->Port = $options['smtpcom_port'];

            // Recipients
            $mail->setFrom($options['from_email'], $options['from_name']);
            $mail->addAddress($to);

            // Attachments
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment);
                }
            }

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_template;

            $mail->send();
            $this->log_email($to, $subject, $html_template, 'success');
            return true;
        } catch (Exception $e) {
            $error_message = 'Mailer Error: ' . $mail->ErrorInfo;
            $this->log_email($to, $subject, $html_template, 'error', $error_message);
            error_log($error_message);
            return false;
        }
    }

    private function send_via_mailjet($to, $subject, $html_template, $headers, $attachments)
    {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['mailjet_api_key']) ? $options['mailjet_api_key'] : '';
        $secret_key = isset($options['mailjet_secret_key']) ? $options['mailjet_secret_key'] : '';

        if (empty($api_key) || empty($secret_key)) {
            $error_message = 'Mailjet API key or secret key is missing.';
            $this->log_email($to, $subject, $html_template, 'error', $error_message);
            error_log($error_message);
            return false;
        }

        $mj = new Client($api_key, $secret_key, true, ['version' => 'v3.1']);
        $body = [
            'Messages' => [
                [
                    'From' => [
                        'Email' => $options['from_email'],
                        'Name' => $options['from_name']
                    ],
                    'To' => [
                        [
                            'Email' => $to,
                            'Name' => $to
                        ]
                    ],
                    'Subject' => $subject,
                    'HTMLPart' => $html_template
                ]
            ]
        ];

        try {
            $response = $mj->post(Resources::$Email, ['body' => $body]);
            if ($response->success()) {
                $this->log_email($to, $subject, $html_template, 'success');
                return true;
            } else {
                $error_message = 'Mailjet API Error: ' . $response->getReasonPhrase();
                $error_info = 'Response: ' . json_encode($response->getBody());
                $this->log_email($to, $subject, $html_template, 'error', $error_info);
                error_log($error_message);
                return false;
            }
        } catch (Exception $e) {
            $error_message = 'Mailjet API Error: ' . $e->getMessage();
            $this->log_email($to, $subject, $html_template, 'error', $error_message);
            error_log($error_message);
            return false;
        }
    }

    public function log_email($to, $subject, $message, $status, $error_info = null)
    {
        global $wpdb;

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
        $subject = 'Test Email from WP Mail Master';
        $message = '<p>This is a test email from WP Mail Master plugin.</p>';
        return $this->send_email($to, $subject, $message);
    }

    private function get_email_template($subject, $message)
    {
        return '
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    </head>
    <body style="margin: 0; padding: 0;">
        <table border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td style="padding: 20px 0 30px 0;">
                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; border: 1px solid #cccccc;">
                        <tr>
                            <td align="center" bgcolor="#0073aa" style="padding: 40px 0 30px 0; color: #ffffff; font-size: 28px; font-weight: bold; font-family: Arial, sans-serif;">
                                ' . esc_html($subject) . '
                            </td>
                        </tr>
                        <tr>
                            <td bgcolor="#ffffff" style="padding: 40px 30px 40px 30px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="color: #153643; font-family: Arial, sans-serif; font-size: 16px; line-height: 24px;">
                                            ' . wp_kses_post($message) . '
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td bgcolor="#f7f7f7" style="padding: 30px 30px;">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                    <tr>
                                        <td style="color: #777777; font-family: Arial, sans-serif; font-size: 14px;" width="75%">
                                            &copy; ' . date('Y') . ' WP Mail Master. All rights reserved.<br/>
                                            <a href="' . site_url() . '" style="color: #0073aa; text-decoration: none;">Visit our website</a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
    }
}
