# WP Mail Master - WordPress Email Manager

[![WordPress](https://img.shields.io/wordpress/v/wp-mail-master.svg)](https://wordpress.org/plugins/wp-mail-master/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0%2B-red.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A comprehensive email management solution for WordPress with support for multiple email services, logging, and analytics.

## Features

- **Multiple Email Service Providers**
  - SMTP
  - Brevo (formerly Sendinblue)
  - SendGrid
  - SMTP.com
  - Mailjet

- **Email Logging**
  - Track all sent emails
  - Monitor delivery status
  - View detailed error messages
  - Search and filter logs

- **Dashboard Analytics**
  - Visual email statistics
  - Success/failure rates
  - Sending trends
  - Real-time monitoring

- **Advanced Features**
  - HTML email templates
  - Test email functionality
  - Custom 'From' name and email
  - Comprehensive error logging

## Requirements

- WordPress 6.0 or higher
- PHP 8.1 or higher
- MySQL 5.6 or higher

## Installation

1. **Manual Installation**
   ```bash
   # Download and unzip the plugin
   cd wp-content/plugins/
   wget https://example.com/wp-mail-master.zip
   unzip wp-mail-master.zip
   ```

2. **Via WordPress Admin**
   - Go to Plugins > Add New
   - Search for "WP Mail Master"
   - Click "Install Now"
   - Activate the plugin

## Configuration

### Basic Setup

1. Navigate to WP Mail Master in your WordPress admin menu
2. Choose your preferred email service provider
3. Configure the service settings:
   - From Name
   - From Email
   - Service-specific credentials

### Email Service Configuration

#### SMTP
```
Host: smtp.your-provider.com
Port: 587 (or as specified by provider)
Username: your-username
Password: your-password
Encryption: TLS/SSL
```

#### Brevo (formerly Sendinblue)
```
API Key: Your Brevo API key
```

#### SendGrid
```
API Key: Your SendGrid API key
```

#### SMTP.com
```
Username: Your SMTP.com username
Password: Your SMTP.com password
```

#### Mailjet
```
API Key: Your Mailjet API key
Secret Key: Your Mailjet secret key
```

## Usage

### Sending Test Email

1. Click "Send Test Email" in the plugin settings
2. Enter the recipient email address
3. Click "Send Email"
4. Check the logs for delivery status

### Viewing Email Logs

1. Navigate to WP Mail Master > Email Logs
2. View detailed information about sent emails:
   - Recipient
   - Subject
   - Status
   - Time sent
   - Error messages (if any)

### Dashboard Widget

The plugin adds a dashboard widget showing:
- Email sending statistics
- Success/failure rates
- Sending trends over time

## Hooks and Filters

### Actions

```php
// Before sending email
do_action('wp_mail_master_before_send', $to, $subject, $message);

// After sending email
do_action('wp_mail_master_after_send', $to, $subject, $message, $status);
```

### Filters

```php
// Modify email template
add_filter('wp_mail_master_email_template', function($template, $subject, $message) {
    return $modified_template;
}, 10, 3);

// Modify email headers
add_filter('wp_mail_master_headers', function($headers, $to, $subject) {
    return $modified_headers;
}, 10, 3);
```

## Troubleshooting

### Common Issues

1. **Emails Not Sending**
   - Verify service credentials
   - Check error logs
   - Confirm WordPress is properly configured

2. **SMTP Connection Issues**
   - Verify port numbers
   - Check firewall settings
   - Confirm encryption settings

3. **API Authentication Failures**
   - Verify API keys
   - Check API permissions
   - Confirm service status

## Support

- Documentation: [Link to docs]
- Support Forum: [Link to forum]
- Bug Reports: [Link to GitHub issues]

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.
```

## Credits

Created by Martho

## Changelog

### 1.1.0
- Added support for Mailjet
- Improved dashboard analytics
- Enhanced error logging
- Bug fixes and performance improvements

### 1.0.0
- Initial release
- Basic email service support
- Email logging functionality
- Dashboard widget