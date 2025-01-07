<?php
// includes/services/abstract/Abstract_Email_Service.php

namespace Martho\WpMailMaster\Services\Abstract;

use Martho\WpMailMaster\Services\Interfaces\Email_Service_Interface;
use Martho\WpMailMaster\WP_Mail_Master_Exception;
use Martho\WpMailMaster\WP_Mail_Master_Error_Handler;

abstract class Abstract_Email_Service implements Email_Service_Interface {
    /** @var array */
    protected $options;
    
    /** @var WP_Mail_Master_Error_Handler */
    protected $logger;

    /**
     * Constructor
     *
     * @param array $options Service configuration options
     */
    public function __construct(array $options) {
        $this->options = $options;
        $this->logger = WP_Mail_Master_Error_Handler::getInstance();
        
        // Validate config on instantiation
        if (!$this->validateConfig()) {
            throw new WP_Mail_Master_Exception(
                'Invalid configuration for ' . get_class($this),
                WP_Mail_Master_Exception::VALIDATION_ERROR
            );
        }
    }

    /**
     * Get the configured From email address
     *
     * @return string
     */
    protected function getFromEmail() {
        return !empty($this->options['from_email']) 
            ? $this->options['from_email'] 
            : get_option('admin_email');
    }

    /**
     * Get the configured From name
     *
     * @return string
     */
    protected function getFromName() {
        return !empty($this->options['from_name'])
            ? $this->options['from_name']
            : get_option('blogname');
    }

    /**
     * Log an error and return false
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return false
     */
    protected function logError($message, array $context = []) {
        $this->logger->handleException(
            new WP_Mail_Master_Exception(
                $message,
                WP_Mail_Master_Exception::SERVICE_ERROR,
                $context
            )
        );
        return false;
    }

    /**
     * Handle email attachments
     *
     * @param mixed $mailer The mailer instance
     * @param array $attachments Array of attachment paths
     * @return void
     */
    protected function handleAttachments($mailer, array $attachments) {
        if (empty($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            if (!file_exists($attachment)) {
                continue;
            }
            
            $filename = basename($attachment);
            $filetype = mime_content_type($attachment);
            $content = file_get_contents($attachment);
            
            if ($content === false) {
                continue;
            }

            $this->attachFile($mailer, $filename, $content, $filetype);
        }
    }

    /**
     * Attach a file to the email - to be implemented by specific services
     *
     * @param mixed $mailer The mailer instance
     * @param string $filename The name of the file
     * @param string $content The file content
     * @param string $filetype The file mime type
     * @return void
     */
    abstract protected function attachFile($mailer, string $filename, string $content, string $filetype);
}