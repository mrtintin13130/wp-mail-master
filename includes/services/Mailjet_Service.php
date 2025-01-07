<?php
// includes/services/Mailjet_Service.php

namespace Martho\WpMailMaster\Services;

use Martho\WpMailMaster\Services\Abstract\Abstract_Email_Service;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use Mailjet\Client;
use Mailjet\Resources;

class Mailjet_Service extends Abstract_Email_Service
{
    /** @var Client */
    private $client;

    public function __construct(array $options, Client $client = null)
    {
        parent::__construct($options);

        if ($client !== null) {
            $this->client = $client;
        } else {
            if (!empty($options['mailjet_api_key']) && !empty($options['mailjet_secret_key'])) {
                $this->client = new Client(
                    $options['mailjet_api_key'],
                    $options['mailjet_secret_key'],
                    true,
                    ['version' => 'v3.1', 'timeout' => 15]
                );
            } else {
                // Delay creation until validateConfig() or send() since we need keys anyway
                $this->client = null;
            }
        }
    }

    public function validateConfig(): bool
    {
        if (empty($this->options['mailjet_api_key']) || empty($this->options['mailjet_secret_key'])) {
            throw new WP_Mail_Master_Exception(
                'Mailjet API and Secret keys are required',
                WP_Mail_Master_Exception::API_KEY_ERROR
            );
        }
        return true;
    }

    public function send($to, $subject, $message, $headers = '', array $attachments = array())
    {
        try {
            if (!$this->client) {
                throw new WP_Mail_Master_Exception(
                    'No Mailjet client configured',
                    WP_Mail_Master_Exception::API_KEY_ERROR
                );
            }

            // Basic email validation
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new WP_Mail_Master_Exception(
                    'Invalid recipient email address',
                    WP_Mail_Master_Exception::VALIDATION_ERROR
                );
            }

            // Prepare email data
            $body = [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => $this->getFromEmail(),
                            'Name' => $this->getFromName()
                        ],
                        'To' => [
                            ['Email' => $to]
                        ],
                        'Subject' => $subject,
                        'HTMLPart' => $message
                    ]
                ]
            ];

            // Add attachments if any
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!file_exists($attachment)) {
                        continue;
                    }
                    $body['Messages'][0]['Attachments'][] = [
                        'Filename' => basename($attachment),
                        'ContentType' => mime_content_type($attachment),
                        'Base64Content' => base64_encode(file_get_contents($attachment))
                    ];
                }
            }

            // Send email
            $response = $this->client->post(Resources::$Email, ['body' => $body]);

            if (!$response->success()) {
                $error = $response->getData();
                throw new WP_Mail_Master_Exception(
                    'Mailjet API Error: ' . ($error['ErrorMessage'] ?? 'Unknown error'),
                    WP_Mail_Master_Exception::API_ERROR
                );
            }

            return true;

        } catch (WP_Mail_Master_Exception $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WP_Mail_Master_Exception(
                'Mailjet Error: ' . $e->getMessage(),
                WP_Mail_Master_Exception::SERVICE_ERROR
            );
        }
    }

    /**
     * Implementation of abstract attachFile method
     * Note: This is not directly used by Mailjet but required by the abstract class
     * Mailjet handles attachments differently in the send() method
     */
    protected function attachFile($mailer, string $filename, string $content, string $filetype): void
    {
        throw new WP_Mail_Master_Exception(
            'Direct file attachment not supported for Mailjet API',
            WP_Mail_Master_Exception::SERVICE_ERROR
        );
    }
}