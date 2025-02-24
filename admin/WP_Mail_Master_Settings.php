<?php

// admin/WP_Mail_Master_Settings.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Settings {
    private $plugin_name;
    private $version;
    private $email_services;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Initialize with just the names first
        $this->email_services = array(
            'smtp' => array(
                'name' => 'SMTP',
            ),
            'brevo' => array(
                'name' => 'Brevo API',
            ),
            'sendgrid' => array(
                'name' => 'SendGrid API',
            ),
            'smtpcom' => array(
                'name' => 'SMTP.com',
            ),
            'mailjet' => array(
                'name' => 'Mailjet',
            ),
        );

        add_action('init', array($this, 'init_service_descriptions'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function init_service_descriptions() {
        // Add descriptions after WordPress is fully loaded for translations to work
        $this->email_services['smtp']['description'] = _x('Standard email protocol used by most email providers. Use this if you have an SMTP server or email hosting service.', 'Email service description', 'wp-mail-master');
        $this->email_services['brevo']['description'] = _x('Formerly Sendinblue. Professional email service with high deliverability rates and detailed analytics.', 'Email service description', 'wp-mail-master');
        $this->email_services['sendgrid']['description'] = _x('Twilio SendGrid\'s reliable email service with advanced features and excellent deliverability.', 'Email service description', 'wp-mail-master');
        $this->email_services['smtpcom']['description'] = _x('Enterprise-level email delivery service with advanced security and tracking features.', 'Email service description', 'wp-mail-master');
        $this->email_services['mailjet']['description'] = _x('European email service provider with GDPR compliance and powerful marketing tools.', 'Email service description', 'wp-mail-master');
    }

    public function register_settings() {
        register_setting(
            'wp_mail_master_options',
            'wp_mail_master_options',
            array($this, 'sanitize_settings')
        );

        // Add Spam Protection section
        add_settings_section(
            'wp_mail_master_spam_protection',
            __('Spam Protection Settings', 'wp-mail-master'),
            array($this, 'spam_protection_section_callback'),
            'wp_mail_master'
        );

        // Add spam protection fields
        add_settings_field(
            'hourly_rate_limit',
            __('Hourly Rate Limit', 'wp-mail-master'),
            array($this, 'hourly_rate_limit_callback'),
            'wp_mail_master',
            'wp_mail_master_spam_protection'
        );

        add_settings_field(
            'daily_email_limit',
            __('Daily Email Limit', 'wp-mail-master'),
            array($this, 'daily_email_limit_callback'),
            'wp_mail_master',
            'wp_mail_master_spam_protection'
        );

        add_settings_field(
            'enable_ip_reputation_check',
            __('IP Reputation Check', 'wp-mail-master'),
            array($this, 'enable_ip_reputation_check_callback'),
            'wp_mail_master',
            'wp_mail_master_spam_protection'
        );

        add_settings_field(
            'spam_keywords',
            __('Spam Keywords', 'wp-mail-master'),
            array($this, 'spam_keywords_callback'),
            'wp_mail_master',
            'wp_mail_master_spam_protection'
        );

        add_settings_section(
            'wp_mail_master_general_section',
            __('General Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_general_section_callback'),
            'wp_mail_master_settings_general'
        );

        add_settings_field(
            'wp_mail_master_email_service',
            __('Email Service', 'wp-mail-master'),
            array($this, 'wp_mail_master_email_service_callback'),
            'wp_mail_master_settings_general',
            'wp_mail_master_general_section'
        );

        add_settings_field(
            'wp_mail_master_from_email',
            __('From Email', 'wp-mail-master'),
            array($this, 'wp_mail_master_from_email_callback'),
            'wp_mail_master_settings_general',
            'wp_mail_master_general_section'
        );

        add_settings_field(
            'wp_mail_master_from_name',
            __('From Name', 'wp-mail-master'),
            array($this, 'wp_mail_master_from_name_callback'),
            'wp_mail_master_settings_general',
            'wp_mail_master_general_section'
        );

        $this->add_smtp_settings();
        $this->add_brevo_settings();
        $this->add_sendgrid_settings();
        $this->add_smtpcom_settings();
        $this->add_mailjet_settings();
    }

    public function wp_mail_master_general_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('Configure your email settings below. These settings determine how your WordPress site sends emails.', 'wp-mail-master') . '</p>';
        echo '<p>' . __('Tips:', 'wp-mail-master') . '</p>';
        echo '<ul class="wp-mail-master-tips">';
        echo '<li>' . __('Choose an email service that best fits your needs', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Test your settings using the "Send Test Email" button after saving', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Check the Email Logs section to monitor email delivery status', 'wp-mail-master') . '</li>';
        echo '</ul>';
        echo '</div>';
    }

    public function wp_mail_master_email_service_callback() {
        $options = get_option('wp_mail_master_options');
        $current_service = isset($options['email_service']) ? $options['email_service'] : 'smtp';
        ?>
        <div class="email-service-description">
            <p><?php _e('Select your preferred email service. Each service has its own advantages:', 'wp-mail-master'); ?></p>
        </div>
        <div class="email-service-grid">
            <?php foreach ($this->email_services as $value => $service): ?>
                <div class="email-service-item <?php echo $current_service === $value ? 'selected' : ''; ?>"
                     data-value="<?php echo esc_attr($value); ?>">
                    <input type="radio" name="wp_mail_master_options[email_service]" 
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked($current_service, $value); ?>>
                    <div class="email-service-icon">
                        <img src="<?php echo esc_url(plugins_url('assets/icons/' . $value . '.svg', WP_MAIL_MASTER_PLUGIN_FILE)); ?>" 
                             alt="<?php echo esc_attr($service['name']); ?> icon">
                    </div>
                    <div class="email-service-label"><?php echo esc_html($service['name']); ?></div>
                    <div class="email-service-description"><?php echo esc_html($service['description']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function wp_mail_master_from_email_callback() {
        $options = get_option('wp_mail_master_options', []);
        $from_email = isset($options['from_email']) ? esc_attr($options['from_email']) : '';
        ?>
        <div class="setting-field">
            <input type="email" name="wp_mail_master_options[from_email]" value="<?php echo $from_email; ?>" />
            <p class="description">
                <?php _e('The email address that all emails will be sent from. This should be a valid email address that you have access to.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }
    
    public function wp_mail_master_from_name_callback() {
        $options = get_option('wp_mail_master_options', []);
        $from_name = isset($options['from_name']) ? esc_attr($options['from_name']) : '';
        ?>
        <div class="setting-field">
            <input type="text" name="wp_mail_master_options[from_name]" value="<?php echo $from_name; ?>" />
            <p class="description">
                <?php _e('The name that will appear as the sender of emails. This could be your website name, company name, or your name.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    // SMTP
    private function add_smtp_settings() {
        add_settings_section(
            'wp_mail_master_smtp_section',
            __('SMTP Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtp_section_callback'),
            'wp_mail_master_smtp_section'
        );

        $smtp_fields = array(
            'smtp_host' => __('SMTP Host', 'wp-mail-master'),
            'smtp_port' => __('SMTP Port', 'wp-mail-master'),
            'smtp_username' => __('SMTP Username', 'wp-mail-master'),
            'smtp_password' => __('SMTP Password', 'wp-mail-master'),
            'smtp_encryption' => __('Encryption', 'wp-mail-master')
        );

        foreach ($smtp_fields as $field_name => $field_label) {
            add_settings_field(
                'wp_mail_master_' . $field_name,
                $field_label,
                array($this, 'wp_mail_master_smtp_field_callback'),
                'wp_mail_master_smtp_section',
                'wp_mail_master_smtp_section',
                array('field_name' => $field_name)
            );
        }
    }

    public function wp_mail_master_smtp_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('SMTP (Simple Mail Transfer Protocol) is the standard protocol for sending emails. Enter your SMTP server details below:', 'wp-mail-master') . '</p>';
        echo '<div class="wp-mail-master-help-box">';
        echo '<h4>' . __('Common SMTP Providers:', 'wp-mail-master') . '</h4>';
        echo '<ul>';
        echo '<li><strong>Gmail:</strong> smtp.gmail.com (Port: 587, Encryption: TLS)</li>';
        echo '<li><strong>Outlook/Office 365:</strong> smtp.office365.com (Port: 587, Encryption: TLS)</li>';
        echo '<li><strong>Yahoo:</strong> smtp.mail.yahoo.com (Port: 587, Encryption: TLS)</li>';
        echo '</ul>';
        echo '<p><em>' . __('Note: Some providers may require you to create an "App Password" for security.', 'wp-mail-master') . '</em></p>';
        echo '</div>';
        echo '</div>';
    }

    public function wp_mail_master_smtp_field_callback($args) {
        $options = get_option('wp_mail_master_options');
        $field_name = $args['field_name'];
        $field_value = isset($options[$field_name]) ? $options[$field_name] : '';

        $descriptions = array(
            'smtp_host' => __('The address of your SMTP server (e.g., smtp.gmail.com)', 'wp-mail-master'),
            'smtp_port' => __('The port your SMTP server uses (common ports: 25, 465, 587)', 'wp-mail-master'),
            'smtp_username' => __('Your SMTP account username (often your email address)', 'wp-mail-master'),
            'smtp_password' => __('Your SMTP account password or app-specific password', 'wp-mail-master'),
            'smtp_encryption' => __('The type of encryption your SMTP server uses', 'wp-mail-master')
        );

        echo '<div class="setting-field">';
        if ($field_name === 'smtp_password') {
            echo '<input type="password" name="wp_mail_master_options[' . esc_attr($field_name) . ']" value="' . esc_attr($field_value) . '" />';
        } elseif ($field_name === 'smtp_encryption') {
            ?>
            <select name="wp_mail_master_options[<?php echo esc_attr($field_name); ?>]">
                <option value="none" <?php selected($field_value, 'none'); ?>><?php _e('None', 'wp-mail-master'); ?></option>
                <option value="ssl" <?php selected($field_value, 'ssl'); ?>><?php _e('SSL', 'wp-mail-master'); ?></option>
                <option value="tls" <?php selected($field_value, 'tls'); ?>><?php _e('TLS (recommended)', 'wp-mail-master'); ?></option>
            </select>
            <?php
        } else {
            echo '<input type="text" name="wp_mail_master_options[' . esc_attr($field_name) . ']" value="' . esc_attr($field_value) . '" />';
        }
        
        if (isset($descriptions[$field_name])) {
            echo '<p class="description">' . esc_html($descriptions[$field_name]) . '</p>';
        }
        echo '</div>';
    }

    // Brevo
    private function add_brevo_settings() {
        add_settings_section(
            'wp_mail_master_brevo_section',
            __('Brevo API Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_brevo_section_callback'),
            'wp_mail_master_brevo_section'
        );

        add_settings_field(
            'wp_mail_master_brevo_api_key',
            __('Brevo API Key', 'wp-mail-master'),
            array($this, 'wp_mail_master_brevo_api_key_callback'),
            'wp_mail_master_brevo_section',
            'wp_mail_master_brevo_section'
        );
    }

    public function wp_mail_master_brevo_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('Brevo (formerly Sendinblue) is a professional email service that offers:', 'wp-mail-master') . '</p>';
        echo '<ul>';
        echo '<li>' . __('High deliverability rates', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Detailed email analytics', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Advanced marketing features', 'wp-mail-master') . '</li>';
        echo '</ul>';
        echo '<p><a href="https://www.brevo.com/pricing/" target="_blank">' . __('Sign up for a Brevo account', 'wp-mail-master') . '</a></p>';
        echo '</div>';
    }

    public function wp_mail_master_brevo_api_key_callback() {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['brevo_api_key']) ? $options['brevo_api_key'] : '';
        ?>
        <div class="setting-field">
            <input type="password" name="wp_mail_master_options[brevo_api_key]" value="<?php echo esc_attr($api_key); ?>" />
            <p class="description">
                <?php _e('Enter your Brevo API key. You can find this in your Brevo account under SMTP & API → API Keys.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    // SendGrid
    private function add_sendgrid_settings() {
        add_settings_section(
            'wp_mail_master_sendgrid_section',
            __('SendGrid Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_sendgrid_section_callback'),
            'wp_mail_master_sendgrid_section'
        );

        add_settings_field(
            'wp_mail_master_sendgrid_api_key',
            __('SendGrid API Key', 'wp-mail-master'),
            array($this, 'wp_mail_master_sendgrid_api_key_callback'),
            'wp_mail_master_sendgrid_section',
            'wp_mail_master_sendgrid_section'
        );
    }

    public function wp_mail_master_sendgrid_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('SendGrid is a leading email delivery platform that offers:', 'wp-mail-master') . '</p>';
        echo '<ul>';
        echo '<li>' . __('Industry-leading delivery rates', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Real-time analytics and reporting', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Advanced email validation', 'wp-mail-master') . '</li>';
        echo '<li>' . __('24/7 expert support', 'wp-mail-master') . '</li>';
        echo '</ul>';
        echo '<p><a href="https://sendgrid.com/pricing/" target="_blank">' . __('Sign up for a SendGrid account', 'wp-mail-master') . '</a></p>';
        echo '</div>';
    }

    public function wp_mail_master_sendgrid_api_key_callback() {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['sendgrid_api_key']) ? $options['sendgrid_api_key'] : '';
        ?>
        <div class="setting-field">
            <input type="password" name="wp_mail_master_options[sendgrid_api_key]" value="<?php echo esc_attr($api_key); ?>" />
            <p class="description">
                <?php _e('Enter your SendGrid API key. You can create one in your SendGrid account under Settings → API Keys.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    // SMTP.com
    private function add_smtpcom_settings() {
        add_settings_section(
            'wp_mail_master_smtpcom_section',
            __('SMTP.com Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_section_callback'),
            'wp_mail_master_smtpcom_section'
        );

        add_settings_field(
            'wp_mail_master_smtpcom_username',
            __('SMTP.com Username', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_username_callback'),
            'wp_mail_master_smtpcom_section',
            'wp_mail_master_smtpcom_section'
        );

        add_settings_field(
            'wp_mail_master_smtpcom_password',
            __('SMTP.com Password', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_password_callback'),
            'wp_mail_master_smtpcom_section',
            'wp_mail_master_smtpcom_section'
        );

        add_settings_field(
            'wp_mail_master_smtpcom_host',
            __('SMTP.com Host', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_host_callback'),
            'wp_mail_master_smtpcom_section',
            'wp_mail_master_smtpcom_section'
        );

        add_settings_field(
            'wp_mail_master_smtpcom_port',
            __('SMTP.com Port', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_port_callback'),
            'wp_mail_master_smtpcom_section',
            'wp_mail_master_smtpcom_section'
        );

        add_settings_field(
            'wp_mail_master_smtpcom_encryption',
            __('SMTP.com Encryption', 'wp-mail-master'),
            array($this, 'wp_mail_master_smtpcom_encryption_callback'),
            'wp_mail_master_smtpcom_section',
            'wp_mail_master_smtpcom_section'
        );
    }

    public function wp_mail_master_smtpcom_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('SMTP.com is an enterprise email delivery service offering:', 'wp-mail-master') . '</p>';
        echo '<ul>';
        echo '<li>' . __('Dedicated IP addresses', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Advanced security features', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Detailed delivery reports', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Enterprise-grade support', 'wp-mail-master') . '</li>';
        echo '</ul>';
        echo '<p><a href="https://smtp.com/pricing/" target="_blank">' . __('Sign up for an SMTP.com account', 'wp-mail-master') . '</a></p>';
        echo '</div>';
    }

    public function wp_mail_master_smtpcom_username_callback() {
        $options = get_option('wp_mail_master_options');
        $username = isset($options['smtpcom_username']) ? $options['smtpcom_username'] : '';
        ?>
        <div class="setting-field">
            <input type="text" name="wp_mail_master_options[smtpcom_username]" value="<?php echo esc_attr($username); ?>" />
            <p class="description">
                <?php _e('Your SMTP.com account username', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function wp_mail_master_smtpcom_password_callback() {
        $options = get_option('wp_mail_master_options');
        $password = isset($options['smtpcom_password']) ? $options['smtpcom_password'] : '';
        ?>
        <div class="setting-field">
            <input type="password" name="wp_mail_master_options[smtpcom_password]" value="<?php echo esc_attr($password); ?>" />
            <p class="description">
                <?php _e('Your SMTP.com account password', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function wp_mail_master_smtpcom_host_callback() {
        $options = get_option('wp_mail_master_options');
        $host = isset($options['smtpcom_host']) ? $options['smtpcom_host'] : '';
        ?>
        <div class="setting-field">
            <input type="text" name="wp_mail_master_options[smtpcom_host]" value="<?php echo esc_attr($host); ?>" />
            <p class="description">
                <?php _e('Your SMTP.com server hostname (usually smtp.com)', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function wp_mail_master_smtpcom_port_callback() {
        $options = get_option('wp_mail_master_options');
        $port = isset($options['smtpcom_port']) ? $options['smtpcom_port'] : '';
        ?>
        <div class="setting-field">
            <input type="text" name="wp_mail_master_options[smtpcom_port]" value="<?php echo esc_attr($port); ?>" />
            <p class="description">
                <?php _e('Your SMTP.com server port (usually 587 for TLS or 465 for SSL)', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function wp_mail_master_smtpcom_encryption_callback() {
        $options = get_option('wp_mail_master_options');
        $encryption = isset($options['smtpcom_encryption']) ? $options['smtpcom_encryption'] : '';
        ?>
        <div class="setting-field">
            <select name="wp_mail_master_options[smtpcom_encryption]">
                <option value="none" <?php selected($encryption, 'none'); ?>><?php _e('None', 'wp-mail-master'); ?></option>
                <option value="ssl" <?php selected($encryption, 'ssl'); ?>><?php _e('SSL', 'wp-mail-master'); ?></option>
                <option value="tls" <?php selected($encryption, 'tls'); ?>><?php _e('TLS (recommended)', 'wp-mail-master'); ?></option>
            </select>
            <p class="description">
                <?php _e('The encryption method used by your SMTP.com server (TLS is recommended)', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    // Mailjet
    private function add_mailjet_settings() {
        add_settings_section(
            'wp_mail_master_mailjet_section',
            __('Mailjet Settings', 'wp-mail-master'),
            array($this, 'wp_mail_master_mailjet_section_callback'),
            'wp_mail_master_mailjet_section'
        );

        add_settings_field(
            'wp_mail_master_mailjet_api_key',
            __('Mailjet API Key', 'wp-mail-master'),
            array($this, 'wp_mail_master_mailjet_api_key_callback'),
            'wp_mail_master_mailjet_section',
            'wp_mail_master_mailjet_section'
        );

        add_settings_field(
            'wp_mail_master_mailjet_secret_key',
            __('Mailjet Secret Key', 'wp-mail-master'),
            array($this, 'wp_mail_master_mailjet_secret_key_callback'),
            'wp_mail_master_mailjet_section',
            'wp_mail_master_mailjet_section'
        );
    }

    public function wp_mail_master_mailjet_section_callback() {
        echo '<div class="wp-mail-master-section-description">';
        echo '<p>' . __('Mailjet is a powerful email service provider offering:', 'wp-mail-master') . '</p>';
        echo '<ul>';
        echo '<li>' . __('GDPR compliance and EU data centers', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Advanced email analytics', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Drag & drop email designer', 'wp-mail-master') . '</li>';
        echo '<li>' . __('Transactional and marketing email support', 'wp-mail-master') . '</li>';
        echo '</ul>';
        echo '<p><a href="https://www.mailjet.com/pricing/" target="_blank">' . __('Sign up for a Mailjet account', 'wp-mail-master') . '</a></p>';
        echo '</div>';
    }

    public function wp_mail_master_mailjet_api_key_callback() {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['mailjet_api_key']) ? $options['mailjet_api_key'] : '';
        ?>
        <div class="setting-field">
            <input type="text" name="wp_mail_master_options[mailjet_api_key]" value="<?php echo esc_attr($api_key); ?>" />
            <p class="description">
                <?php _e('Enter your Mailjet API Key. You can find this in your Mailjet account under Account Settings → API Keys.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function wp_mail_master_mailjet_secret_key_callback() {
        $options = get_option('wp_mail_master_options');
        $secret_key = isset($options['mailjet_secret_key']) ? $options['mailjet_secret_key'] : '';
        ?>
        <div class="setting-field">
            <input type="password" name="wp_mail_master_options[mailjet_secret_key]" value="<?php echo esc_attr($secret_key); ?>" />
            <p class="description">
                <?php _e('Enter your Mailjet Secret Key. This is paired with your API Key for authentication.', 'wp-mail-master'); ?>
            </p>
        </div>
        <?php
    }

    public function spam_protection_section_callback() {
        echo '<p>' . __('Configure spam protection settings to prevent abuse.', 'wp-mail-master') . '</p>';
    }

    public function hourly_rate_limit_callback() {
        $options = get_option('wp_mail_master_options');
        $value = isset($options['hourly_rate_limit']) ? $options['hourly_rate_limit'] : 50;
        ?>
        <input type="number" 
               name="wp_mail_master_options[hourly_rate_limit]" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="1000" />
        <p class="description">
            <?php _e('Maximum number of emails allowed per hour from the same IP address.', 'wp-mail-master'); ?>
        </p>
        <?php
    }

    public function daily_email_limit_callback() {
        $options = get_option('wp_mail_master_options');
        $value = isset($options['daily_email_limit']) ? $options['daily_email_limit'] : 100;
        ?>
        <input type="number" 
               name="wp_mail_master_options[daily_email_limit]" 
               value="<?php echo esc_attr($value); ?>" 
               min="1" 
               max="5000" />
        <p class="description">
            <?php _e('Maximum number of emails allowed per day to the same email address.', 'wp-mail-master'); ?>
        </p>
        <?php
    }

    public function enable_ip_reputation_check_callback() {
        $options = get_option('wp_mail_master_options');
        $checked = isset($options['enable_ip_reputation_check']) ? $options['enable_ip_reputation_check'] : 0;
        ?>
        <label>
            <input type="checkbox" 
                   name="wp_mail_master_options[enable_ip_reputation_check]" 
                   value="1" 
                   <?php checked(1, $checked); ?> />
            <?php _e('Enable IP reputation checking', 'wp-mail-master'); ?>
        </label>
        <p class="description">
            <?php _e('Check sender IP reputation against known spam sources.', 'wp-mail-master'); ?>
        </p>
        <?php
    }

    public function spam_keywords_callback() {
        $keywords = get_option('wp_mail_master_spam_keywords', array());
        ?>
        <textarea name="wp_mail_master_spam_keywords" 
                  rows="5" 
                  cols="50"
                  placeholder="<?php _e('Enter keywords separated by commas', 'wp-mail-master'); ?>"
        ><?php echo esc_textarea(implode(', ', $keywords)); ?></textarea>
        <p class="description">
            <?php _e('Add custom spam keywords (comma-separated). These will be combined with default spam keywords.', 'wp-mail-master'); ?>
        </p>
        <?php
    }

    /**
     * Comprehensive settings sanitization
     */
    public function sanitize_settings($input) {
        // Verify nonce
        if (!isset($_POST['wp_mail_master_nonce']) 
        || !wp_verify_nonce($_POST['wp_mail_master_nonce'], 'wp_mail_master_settings') 
        || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            add_settings_error(
                'wp_mail_master_messages',
                'wp_mail_master_nonce_error',
                __('Security verification failed. Please try again.', 'wp-mail-master'),
                'error'
            );
            return get_option('wp_mail_master_options', array());
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            add_settings_error(
                'wp_mail_master_messages',
                'wp_mail_master_capability_error',
                __('You do not have sufficient permissions to modify these settings.', 'wp-mail-master'),
                'error'
            );
            return get_option('wp_mail_master_options', array());
        }

        $sanitized = array();

        // Sanitize general settings
        $sanitized['email_service'] = isset($input['email_service']) ? 
            sanitize_key($input['email_service']) : 'smtp';
        
        if (!array_key_exists($sanitized['email_service'], $this->email_services)) {
            $sanitized['email_service'] = 'smtp';
        }

        $sanitized['from_email'] = isset($input['from_email']) ? 
            sanitize_email($input['from_email']) : '';
        $sanitized['from_name'] = isset($input['from_name']) ? 
            sanitize_text_field($input['from_name']) : '';

        // Sanitize SMTP settings
        $sanitized['smtp_host'] = isset($input['smtp_host']) ? 
            sanitize_text_field($input['smtp_host']) : '';
        $sanitized['smtp_port'] = isset($input['smtp_port']) ? 
            absint($input['smtp_port']) : 587;
        $sanitized['smtp_username'] = isset($input['smtp_username']) ? 
            sanitize_text_field($input['smtp_username']) : '';
        
        // Handle password only if it's being updated
        if (!empty($input['smtp_password'])) {
            $sanitized['smtp_password'] = wp_hash_password($input['smtp_password']);
        } else {
            // Retain existing password if not being updated
            $existing_options = get_option('wp_mail_master_options', array());
            $sanitized['smtp_password'] = isset($existing_options['smtp_password']) ? 
                $existing_options['smtp_password'] : '';
        }

        $valid_encryptions = array('none', 'ssl', 'tls');
        $sanitized['smtp_encryption'] = isset($input['smtp_encryption']) && 
            in_array($input['smtp_encryption'], $valid_encryptions) ? 
            $input['smtp_encryption'] : 'none';

        // Sanitize API Keys
        $sanitized['brevo_api_key'] = isset($input['brevo_api_key']) ? 
            sanitize_text_field($input['brevo_api_key']) : '';
        $sanitized['sendgrid_api_key'] = isset($input['sendgrid_api_key']) ? 
            sanitize_text_field($input['sendgrid_api_key']) : '';
        $sanitized['mailjet_api_key'] = isset($input['mailjet_api_key']) ? 
            sanitize_text_field($input['mailjet_api_key']) : '';
        $sanitized['mailjet_secret_key'] = isset($input['mailjet_secret_key']) ? 
            sanitize_text_field($input['mailjet_secret_key']) : '';

        // Sanitize spam protection settings
        $sanitized['hourly_rate_limit'] = isset($input['hourly_rate_limit']) 
            ? absint($input['hourly_rate_limit']) 
            : 50;
            
        $sanitized['daily_email_limit'] = isset($input['daily_email_limit']) 
            ? absint($input['daily_email_limit']) 
            : 100;
            
        $sanitized['enable_ip_reputation_check'] = isset($input['enable_ip_reputation_check']) 
            ? 1 
            : 0;

        // Sanitize and save spam keywords
        if (isset($_POST['wp_mail_master_spam_keywords'])) {
            $keywords = explode(',', sanitize_text_field($_POST['wp_mail_master_spam_keywords']));
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);
            update_option('wp_mail_master_spam_keywords', $keywords);
        }

        return $sanitized;
    }

    /**
     * Simple encryption for sensitive data
     */
    private function encrypt_password($password) {
        if (function_exists('sodium_crypto_secretbox')) {
            $key = $this->get_encryption_key();
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($password, $nonce, $key);
            return base64_encode($nonce . $encrypted);
        }
        return $password; // Fallback if sodium is not available
    }

    /**
     * Get or generate encryption key
     */
    private function get_encryption_key() {
        $key = get_option('wp_mail_master_encrypt_key');
        if (empty($key)) {
            $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            update_option('wp_mail_master_encrypt_key', $key);
        }
        return $key;
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt_password($encrypted) {
        if (function_exists('sodium_crypto_secretbox') && !empty($encrypted)) {
            try {
                $decoded = base64_decode($encrypted);
                $key = $this->get_encryption_key();
                $nonce = mb_substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, '8bit');
                $ciphertext = mb_substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES, null, '8bit');
                return sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
            } catch (\Exception $e) {
                error_log('WP Mail Master: Password decryption failed: ' . $e->getMessage());
                return false;
            }
        }
        return $encrypted; // Fallback if sodium is not available
    }

    /**
     * Add nonce field to settings form
     */
    public function settings_fields() {
        wp_nonce_field('wp_mail_master_settings', 'wp_mail_master_nonce');
    }
}
