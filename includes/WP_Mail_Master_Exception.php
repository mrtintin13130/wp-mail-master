<?php
// includes/WP_Mail_Master_Exception.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Exception extends \Exception
{
    // Service-specific errors (100-199)
    const SMTP_ERROR = 100;
    const SERVICE_ERROR = 101;
    const CONNECTION_ERROR = 102;
    const AUTHENTICATION_ERROR = 103;

    // API-specific errors (200-299)
    const API_ERROR = 200;
    const API_KEY_ERROR = 201;
    const API_RATE_LIMIT_ERROR = 202;
    const API_RESPONSE_ERROR = 203;

    // Database errors (300-399)
    const DATABASE_ERROR = 300;
    const QUERY_ERROR = 301;
    const CONNECTION_LOST_ERROR = 302;

    // Validation errors (400-499)
    const VALIDATION_ERROR = 400;
    const INVALID_EMAIL_ERROR = 401;
    const INVALID_CONFIG_ERROR = 402;
    const INVALID_ATTACHMENT_ERROR = 403;

    // Permission errors (500-599)
    const PERMISSION_ERROR = 500;
    const UNAUTHORIZED_ERROR = 501;
    const INSUFFICIENT_PRIVILEGES_ERROR = 502;

    // Spam protection related error codes
    const RATE_LIMIT_ERROR = 8;
    const SPAM_CONTENT_ERROR = 9;
    const IP_BLOCKED_ERROR = 10;
    const SPAM_DETECTED_ERROR = 11;

    /** @var array */
    protected $context;

    /**
     * Constructor
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param array $context Additional context information
     * @param \Exception|null $previous Previous exception if nested
     */
    public function __construct($message, $code = 0, $context = [], \Exception $previous = null)
    {
        $this->context = $context;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error context
     *
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * Get a human-readable error type based on the error code
     *
     * @return string
     */
    public function getErrorType()
    {
        $code = $this->getCode();

        if ($code >= 100 && $code < 200) {
            return 'Service Error';
        } elseif ($code >= 200 && $code < 300) {
            return 'API Error';
        } elseif ($code >= 300 && $code < 400) {
            return 'Database Error';
        } elseif ($code >= 400 && $code < 500) {
            return 'Validation Error';
        } elseif ($code >= 500 && $code < 600) {
            return 'Permission Error';
        }

        return 'Unknown Error';
    }
}