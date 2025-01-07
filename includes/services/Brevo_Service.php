<?php
// includes/services/Brevo_Service.php

namespace Martho\WpMailMaster\Services;

use Martho\WpMailMaster\Services\Abstract\Abstract_Email_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use Brevo\Client\Api\TransactionalEmailsApi;
use Brevo\Client\Configuration;
use Brevo\Client\Model\SendSmtpEmail;

class Brevo_Service extends Abstract_Email_Service
{
    /** @var TransactionalEmailsApi */
    private $apiInstance;

    public function validateConfig(): bool
    {
        if (empty($this->options['brevo_api_key'])) {
            throw new WP_Mail_Master_Exception(
                'Brevo API key is required',
                WP_Mail_Master_Exception::API_KEY_ERROR
            );
        }
        return true;
    }

    public function send($to, $subject, $message, $headers = '', array $attachments = array())
    {
        // Basic email validation
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $to)) {
            throw new WP_Mail_Master_Exception(
                'Invalid recipient email address',
                WP_Mail_Master_Exception::INVALID_EMAIL_ERROR
            );
        }
        
        try {
            // Initialize API if not already done
            if (!$this->apiInstance) {
                $config = Configuration::getDefaultConfiguration()
                    ->setApiKey('api-key', $this->options['brevo_api_key']);

                $this->apiInstance = new TransactionalEmailsApi(
                    new \GuzzleHttp\Client(['timeout' => 15]),
                    $config
                );
            }

            // Prepare email data
            $emailData = [
                'sender' => [
                    'name' => $this->getFromName(),
                    'email' => $this->getFromEmail()
                ],
                'to' => [['email' => $to]],
                'subject' => $subject,
                'htmlContent' => $message
            ];

            // Add attachments if any
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        continue;
                    }
                    $emailData['attachment'][] = [
                        'name' => basename($attachment),
                        'content' => base64_encode(file_get_contents($attachment))
                    ];
                }
            }

            // Send email
            $result = $this->apiInstance->sendTransacEmail(new SendSmtpEmail($emailData));
            return true;

        } catch (\Exception $e) {
            throw new WP_Mail_Master_Exception(
                'Brevo API Error: ' . $e->getMessage(),
                WP_Mail_Master_Exception::API_ERROR
            );
        }
    }

    /**
     * Implementation of abstract attachFile method
     * Note: This is not directly used by Brevo but required by the abstract class
     * Brevo handles attachments differently in the send() method
     */
    protected function attachFile($mailer, string $filename, string $content, string $filetype): void
    {
        // This method is not used for Brevo API but needed for interface compatibility
        throw new WP_Mail_Master_Exception(
            'Direct file attachment not supported for Brevo API',
            WP_Mail_Master_Exception::SERVICE_ERROR
        );
    }
}