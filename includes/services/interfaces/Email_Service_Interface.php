<?php
// includes/services/interfaces/Email_Service_Interface.php

namespace Martho\WpMailMaster\Services\Interfaces;

interface Email_Service_Interface {
    /**
     * Send an email using the service
     *
     * @param string $to Email recipient
     * @param string $subject Email subject
     * @param string $message Email message body
     * @param string|array $headers Optional. Email headers
     * @param array $attachments Optional. Email attachments
     * @return bool Success status
     */
    public function send($to, $subject, $message, $headers = '', array $attachments = array());

    /**
     * Validate the service configuration
     *
     * @return bool Whether the configuration is valid
     */
    public function validateConfig();
}