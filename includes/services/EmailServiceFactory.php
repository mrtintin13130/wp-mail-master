<?php
// includes/services/EmailServiceFactory.php

namespace Martho\WpMailMaster\Services;

class EmailServiceFactory {
    public static function create($service_type, array $options) {
        switch ($service_type) {
            case 'smtp':
                return new SMTP_Service($options);
            case 'brevo':
                return new Brevo_Service($options);
            case 'sendgrid':
                return new SendGrid_Service($options);
            case 'mailjet':
                return new Mailjet_Service($options);
            case 'smtpcom':
                return new SMTPcom_Service($options);
            default:
                throw new \InvalidArgumentException("Unknown email service: $service_type");
        }
    }
}