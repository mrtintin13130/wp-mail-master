<?php
// includes/services/SMTP_Service.php

namespace Martho\WpMailMaster\Services;

use Martho\WpMailMaster\Services\Abstract\Abstract_Email_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SMTP_Service extends Abstract_Email_Service
{
    /**
     * @var PHPMailer
     */
    protected $mailer;

    public function __construct(array $options, PHPMailer $mailer = null)
    {
        parent::__construct($options);
        $this->mailer = $mailer ?: new PHPMailer(true);
    }

    public function validateConfig()
    {
        // Check if SMTP is configured at all
        if (!$this->isConfigured()) {
            return false;
        }
        
        // Validate configuration when called explicitly
        return $this->validateSMTPConfig();
    }

    protected function isConfigured()
    {
        // Check if any SMTP settings are present
        $smtp_fields = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'];
        foreach ($smtp_fields as $field) {
            if (!empty($this->options[$field])) {
                return true;
            }
        }
        return false;
    }

    protected function validateSMTPConfig()
    {
        // If not configured, don't validate
        if (!$this->isConfigured()) {
            return false;
        }

        $required = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password'];

        foreach ($required as $field) {
            if (empty($this->options[$field])) {
                throw new WP_Mail_Master_Exception(
                    sprintf('Missing required SMTP configuration: %s', $field),
                    WP_Mail_Master_Exception::INVALID_CONFIG_ERROR,
                    ['field' => $field]
                );
            }
        }

        // Validate port number
        if (!is_numeric($this->options['smtp_port']) || $this->options['smtp_port'] <= 0) {
            throw new WP_Mail_Master_Exception(
                'Invalid SMTP port number',
                WP_Mail_Master_Exception::VALIDATION_ERROR,
                ['field' => 'smtp_port']
            );
        }

        return true;
    }

    public function send($to, $subject, $message, $headers = '', $attachments = [])
    {
        // Check if SMTP is configured before attempting to send
        if (!$this->isConfigured()) {
            throw new WP_Mail_Master_Exception(
                'SMTP is not configured. Please configure SMTP settings before sending emails.',
                WP_Mail_Master_Exception::INVALID_CONFIG_ERROR
            );
        }

        // Validate config before attempting to send
        $this->validateSMTPConfig();

        try {
            // Validate email address
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new WP_Mail_Master_Exception(
                    'Invalid recipient email address',
                    WP_Mail_Master_Exception::INVALID_EMAIL_ERROR,
                    ['email' => $to]
                );
            }

            $mail = $this->mailer;

            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->options['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->options['smtp_username'];
            $mail->Password = $this->options['smtp_password'];
            $mail->SMTPSecure = $this->options['smtp_encryption'] ?? 'tls';
            $mail->Port = $this->options['smtp_port'];

            // Enable debug mode if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function ($str, $level) {
                    error_log("PHPMailer Debug: $str");
                };
            }

            try {
                // Recipients
                $mail->setFrom($this->getFromEmail(), $this->getFromName());
                $mail->addAddress($to);

                // Add custom headers if provided
                if (!empty($headers)) {
                    if (!is_array($headers) && $headers !== null) {
                        // Ensure headers is a string before processing
                        $headers = (string)$headers;
                        $headers = explode("\n", str_replace("\r\n", "\n", $headers));
                    } elseif ($headers === null) {
                        $headers = array();
                    }
                    
                    // Ensure each header is a string
                    $headers = array_map(function($header) {
                        return $header === null ? '' : (string)$header;
                    }, (array)$headers);
                    
                    foreach ($headers as $header) {
                        // Skip if not a string or empty
                        if (empty($header)) {
                            continue;
                        }

                        // Skip if no colon found
                        $header_str = (string)$header;
                        if (strpos($header_str, ':') === false) {
                            continue;
                        }

                        list($name, $value) = explode(':', $header_str, 2);
                        $name = trim((string)$name);
                        $value = trim((string)$value);
                        
                        if (!empty($name) && !empty($value)) {
                            $mail->addCustomHeader($name, $value);
                        }
                    }
                }

                // Content
                $mail->isHTML(true);
                $mail->Subject = (string)($subject ?: '');
                $message_str = (string)($message ?: '');
                $mail->Body = $message_str;
                $mail->AltBody = wp_strip_all_tags($message_str); // Plain text version

                // Handle attachments
                if (!empty($attachments)) {
                    try {
                        $this->handleAttachments($mail, $attachments);
                    } catch (\Exception $e) {
                        throw new WP_Mail_Master_Exception(
                            'Failed to process attachments: ' . $e->getMessage(),
                            WP_Mail_Master_Exception::INVALID_ATTACHMENT_ERROR,
                            ['attachments' => $attachments]
                        );
                    }
                }

                // Send the email
                if (!$mail->send()) {
                    throw new WP_Mail_Master_Exception(
                        'Failed to send email: ' . $mail->ErrorInfo,
                        WP_Mail_Master_Exception::SMTP_ERROR,
                        ['error_info' => $mail->ErrorInfo]
                    );
                }

                return true;

            } catch (PHPMailerException $e) {
                throw new WP_Mail_Master_Exception(
                    'SMTP Error: ' . $e->getMessage(),
                    WP_Mail_Master_Exception::SMTP_ERROR,
                    [
                        'error_info' => $mail->ErrorInfo ?? '',
                        'smtp_host' => $mail->Host,
                        'smtp_port' => $mail->Port
                    ]
                );
            }

        } catch (WP_Mail_Master_Exception $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (\Exception $e) {
            // Convert any other exceptions to our custom exception
            throw new WP_Mail_Master_Exception(
                'Unexpected error while sending email: ' . $e->getMessage(),
                WP_Mail_Master_Exception::SERVICE_ERROR,
                ['original_exception' => get_class($e)]
            );
        }
    }

    protected function attachFile($mailer, string $filename, string $content, string $filetype)
    {
        try {
            $mailer->addStringAttachment($content, $filename, 'base64', $filetype);
        } catch (\Exception $e) {
            throw new WP_Mail_Master_Exception(
                'Failed to attach file: ' . $e->getMessage(),
                WP_Mail_Master_Exception::INVALID_ATTACHMENT_ERROR,
                [
                    'filename' => $filename,
                    'filetype' => $filetype
                ]
            );
        }
    }
}