=== WP Mail Master - WordPress Email Manager ===
Contributors: martho
Tags: email, smtp, mail, logs, email-logs, email-tracking, sendinblue, brevo, sendgrid, mailjet
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A comprehensive email management solution for WordPress with support for multiple email services, logging, and analytics.

== Description ==

WP Mail Master is a powerful email management plugin that provides complete control over your WordPress site's email functionality. With support for multiple email service providers, detailed logging, and comprehensive analytics, it ensures reliable email delivery and tracking.

= Key Features =

* **Multiple Email Service Providers**
  * SMTP configuration
  * Brevo (formerly Sendinblue) integration
  * SendGrid support
  * SMTP.com compatibility
  * Mailjet integration

* **Email Logging**
  * Track all outgoing emails
  * Monitor delivery status
  * View detailed error messages
  * Advanced search and filtering capabilities

* **Dashboard Analytics**
  * Visual email statistics
  * Success/failure rate tracking
  * Sending trend analysis
  * Real-time monitoring

* **Advanced Features**
  * HTML email templates
  * Test email functionality
  * Custom 'From' name and email
  * Comprehensive error logging

= Pro Features =
* Advanced email analytics
* Priority support
* Custom email templates
* Extended log retention
* API access

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-mail-master` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to WP Mail Master in your WordPress admin menu to configure the settings.

= Basic Configuration =

1. Choose your preferred email service provider
2. Enter the required credentials
3. Configure your From Name and From Email
4. Send a test email to verify the setup

== Frequently Asked Questions ==

= Which email services are supported? =

WP Mail Master supports SMTP, Brevo (formerly Sendinblue), SendGrid, SMTP.com, and Mailjet.

= Can I track email delivery status? =

Yes, the plugin includes comprehensive email logging that tracks delivery status, opens, and any delivery errors.

= Is it compatible with HTML emails? =

Yes, the plugin fully supports HTML email formatting and templates.

= What happens to my logs if I deactivate the plugin? =

By default, logs are preserved when deactivating the plugin but can be removed during uninstallation if selected.

= Does it work with WordPress Multisite? =

Yes, the plugin is fully compatible with WordPress Multisite installations.

== Screenshots ==

1. Dashboard Overview
2. Email Service Configuration
3. Email Logs Interface
4. Analytics Dashboard
5. Settings Page

== Changelog ==

= 1.1.0 =
* Added support for Mailjet
* Improved dashboard analytics
* Enhanced error logging
* Bug fixes and performance improvements

= 1.0.0 =
* Initial release
* Basic email service support
* Email logging functionality
* Dashboard widget implementation

== Upgrade Notice ==

= 1.1.0 =
This version adds Mailjet support and includes important bug fixes. Upgrade recommended for all users.

== Developer Documentation ==

= Actions =

`wp_mail_master_before_send` - Triggered before sending an email
`wp_mail_master_after_send` - Triggered after sending an email

= Filters =

`wp_mail_master_email_template` - Modify email template
`wp_mail_master_headers` - Modify email headers 