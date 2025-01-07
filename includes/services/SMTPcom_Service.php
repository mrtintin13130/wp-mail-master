<?php
// includes/services/SMTPcom_Service.php

namespace Martho\WpMailMaster\Services;

use Martho\WpMailMaster\Services\Abstract\Abstract_Email_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use PHPMailer\PHPMailer\PHPMailer;

class SMTPcom_Service extends Abstract_Email_Service
{
    /** @var PHPMailer */
    private $mailer;

    public function validateConfig(): bool
    {
        if (
            empty($this->options['smtpcom_username']) ||
            empty($this->options['smtpcom_password']) ||
            empty($this->options['smtpcom_host']) ||
            empty($this->options['smtpcom_port'])
        ) {
            throw new WP_Mail_Master_Exception(
                'SMTP.com configuration is incomplete',
                WP_Mail_Master_Exception::INVALID_CONFIG_ERROR
            );
        }
        return true;
    }

    public function send($to, $subject, $message, $headers = '', array $attachments = array())
    {
        try {
            // Initialize mailer if not already done
            if (!$this->mailer) {
                $this->mailer = new PHPMailer(true);

                // Server settings
                $this->mailer->isSMTP();
                $this->mailer->Host = $this->options['smtpcom_host'];
                $this->mailer->SMTPAuth = true;
                $this->mailer->Username = $this->options['smtpcom_username'];
                $this->mailer->Password = $this->options['smtpcom_password'];
                $this->mailer->Port = $this->options['smtpcom_port'];

                // Set encryption if specified
                if (!empty($this->options['smtpcom_encryption'])) {
                    $this->mailer->SMTPSecure = $this->options['smtpcom_encryption'];
                }

                // Set timeout
                $this->mailer->Timeout = 15;
            }

            // Clear previous settings
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();

            // Basic email validation
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new WP_Mail_Master_Exception(
                    'Invalid recipient email address',
                    WP_Mail_Master_Exception::VALIDATION_ERROR
                );
            }

            // Recipients
            $this->mailer->setFrom($this->getFromEmail(), $this->getFromName());
            $this->mailer->addAddress($to);

            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;
            $this->mailer->AltBody = wp_strip_all_tags($message);

            // Add attachments if any
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        continue;
                    }
                    $this->mailer->addAttachment($attachment);
                }
            }

            // Send email
            if (!$this->mailer->send()) {
                throw new WP_Mail_Master_Exception(
                    'SMTP.com Error: ' . $this->mailer->ErrorInfo,
                    WP_Mail_Master_Exception::SMTP_ERROR
                );
            }

            return true;

        } catch (WP_Mail_Master_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WP_Mail_Master_Exception(
                'SMTP.com Error: ' . $e->getMessage(),
                WP_Mail_Master_Exception::SERVICE_ERROR
            );
        }
    }

    protected function attachFile($mailer, string $filename, string $content, string $filetype): void
    {
        try {
            $tempFile = wp_tempnam($filename);
            if (file_put_contents($tempFile, $content) === false) {
                throw new WP_Mail_Master_Exception(
                    'Failed to create temporary file for attachment',
                    WP_Mail_Master_Exception::INVALID_ATTACHMENT_ERROR
                );
            }

            $mailer->addAttachment($tempFile, $filename, 'base64', $filetype);

            // Clean up temp file
            register_shutdown_function(function () use ($tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            });

        } catch (\Exception $e) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            throw new WP_Mail_Master_Exception(
                'Failed to attach file: ' . $e->getMessage(),
                WP_Mail_Master_Exception::INVALID_ATTACHMENT_ERROR
            );
        }
    }
}