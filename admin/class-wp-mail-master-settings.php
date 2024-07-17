<?php

// admin/class-wp-mail-master-settings.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Settings {
    private $plugin_name;
    private $version;
    private $email_services;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->email_services = array(
            'smtp' => 'SMTP',
            'brevo' => 'Brevo API',
            'sendgrid' => 'SendGrid API',
        );

        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings() {
        register_setting('wp_mail_master_settings', 'wp_mail_master_options');

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
    }

    public function wp_mail_master_general_section_callback() {
        echo '<p>' . __('Configure your email settings below:', 'wp-mail-master') . '</p>';
    }

    public function wp_mail_master_email_service_callback() {
        $options = get_option('wp_mail_master_options');
        $current_service = isset($options['email_service']) ? $options['email_service'] : 'smtp';
        ?>
        <div class="email-service-grid">
            <?php foreach ($this->email_services as $value => $label): ?>
                <div class="email-service-item <?php echo $current_service === $value ? 'selected' : ''; ?>"
                     data-value="<?php echo esc_attr($value); ?>">
                    <input type="radio" name="wp_mail_master_options[email_service]" 
                           value="<?php echo esc_attr($value); ?>"
                           <?php checked($current_service, $value); ?>>
                    <div class="email-service-icon">
                        <img src="<?php echo esc_url(plugins_url('assets/icons/' . $value . '.svg', WP_MAIL_MASTER_PLUGIN_FILE)); ?>" alt="<?php echo esc_attr($label); ?> icon">
                    </div>
                    <div class="email-service-label"><?php echo esc_html($label); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function wp_mail_master_from_email_callback() {
        $options = get_option('wp_mail_master_options');
        echo '<input type="email" name="wp_mail_master_options[from_email]" value="' . esc_attr($options['from_email']) . '" />';
    }

    public function wp_mail_master_from_name_callback() {
        $options = get_option('wp_mail_master_options');
        echo '<input type="text" name="wp_mail_master_options[from_name]" value="' . esc_attr($options['from_name']) . '" />';
    }

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
        echo '<p>' . __('Enter your SMTP settings below:', 'wp-mail-master') . '</p>';
    }

    public function wp_mail_master_smtp_field_callback($args) {
        $options = get_option('wp_mail_master_options');
        $field_name = $args['field_name'];
        $field_value = isset($options[$field_name]) ? $options[$field_name] : '';

        if ($field_name === 'smtp_password') {
            echo '<input type="password" name="wp_mail_master_options[' . esc_attr($field_name) . ']" value="' . esc_attr($field_value) . '" />';
        } elseif ($field_name === 'smtp_encryption') {
            ?>
            <select name="wp_mail_master_options[<?php echo esc_attr($field_name); ?>]">
                <option value="none" <?php selected($field_value, 'none'); ?>>None</option>
                <option value="ssl" <?php selected($field_value, 'ssl'); ?>>SSL</option>
                <option value="tls" <?php selected($field_value, 'tls'); ?>>TLS</option>
            </select>
            <?php
        } else {
            echo '<input type="text" name="wp_mail_master_options[' . esc_attr($field_name) . ']" value="' . esc_attr($field_value) . '" />';
        }
    }

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
        echo '<p>' . __('Enter your Brevo API settings below:', 'wp-mail-master') . '</p>';
    }

    public function wp_mail_master_brevo_api_key_callback() {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['brevo_api_key']) ? $options['brevo_api_key'] : '';
        echo '<input type="text" name="wp_mail_master_options[brevo_api_key]" value="' . esc_attr($api_key) . '" />';
    }

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
        echo '<p>' . __('Enter your SendGrid API settings below:', 'wp-mail-master') . '</p>';
    }

    public function wp_mail_master_sendgrid_api_key_callback() {
        $options = get_option('wp_mail_master_options');
        $api_key = isset($options['sendgrid_api_key']) ? $options['sendgrid_api_key'] : '';
        echo '<input type="text" name="wp_mail_master_options[sendgrid_api_key]" value="' . esc_attr($api_key) . '" />';
    }
}
