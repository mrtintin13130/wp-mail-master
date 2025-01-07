<?php
// includes/services/SendGrid_Service.php

namespace Martho\WpMailMaster\Services;

use Martho\WpMailMaster\Services\Abstract\Abstract_Email_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use SendGrid;
use SendGrid\Mail\Mail;

class SendGrid_Service extends Abstract_Email_Service
{
    /** @var SendGrid */
    private $client;

    public function validateConfig(): bool
    {
        if (empty($this->options['sendgrid_api_key'])) {
            throw new WP_Mail_Master_Exception(
                'SendGrid API key is required',
                WP_Mail_Master_Exception::API_KEY_ERROR
            );
        }
        return true;
    }

    public function send($to, $subject, $message, $headers = '', array $attachments = array())
    {
        try {
            // Initialize client if not already done
            if (!$this->client) {
                $this->client = new SendGrid($this->options['sendgrid_api_key']);
            }

            // Basic email validation
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new WP_Mail_Master_Exception(
                    'Invalid recipient email address',
                    WP_Mail_Master_Exception::VALIDATION_ERROR
                );
            }

            // Create email
            $email = new Mail();
            $email->setFrom($this->getFromEmail(), $this->getFromName());
            $email->setSubject($subject);
            $email->addTo($to);
            $email->addContent("text/html", $message);

            // Add attachments if any
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        continue;
                    }

                    $content = file_get_contents($attachment);
                    if ($content === false) {
                        continue;
                    }

                    $email->addAttachment(
                        base64_encode($content),
                        mime_content_type($attachment),
                        basename($attachment)
                    );
                }
            }

            // Send email
            $response = $this->client->send($email);

            // Check response
            if ($response->statusCode() >= 400) {
                throw new WP_Mail_Master_Exception(
                    'SendGrid API Error: ' . $response->body(),
                    $this->mapStatusCodeToExceptionCode($response->statusCode())
                );
            }

            return true;

        } catch (WP_Mail_Master_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WP_Mail_Master_Exception(
                'SendGrid Error: ' . $e->getMessage(),
                WP_Mail_Master_Exception::SERVICE_ERROR
            );
        }
    }

    private function mapStatusCodeToExceptionCode(int $statusCode): int
    {
        switch ($statusCode) {
            case 401:
            case 403:
                return WP_Mail_Master_Exception::API_KEY_ERROR;
            case 429:
                return WP_Mail_Master_Exception::API_RATE_LIMIT_ERROR;
            default:
                return WP_Mail_Master_Exception::API_ERROR;
        }
    }

    /**
     * Implementation of abstract attachFile method
     * Note: This is not directly used by SendGrid but required by the abstract class
     * SendGrid handles attachments differently in the send() method
     */
    protected function attachFile($mailer, string $filename, string $content, string $filetype): void
    {
        throw new WP_Mail_Master_Exception(
            'Direct file attachment not supported for SendGrid API',
            WP_Mail_Master_Exception::SERVICE_ERROR
        );
    }
}