<?php
// includes/WP_Mail_Master.php

namespace Martho\WpMailMaster;

class WP_Mail_Master
{
    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct()
    {
        $this->version = WP_MAIL_MASTER_VERSION;
        $this->plugin_name = 'wp-mail-master';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_email_hooks();
    }

    private function load_dependencies()
    {
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Loader.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_i18n.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Emailer.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_DB.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Queue.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Error_Handler.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Exception.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master_Spam_Protection.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Admin.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Ajax.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Logs.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Settings.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Security.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Security_Logs.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Error_Logs.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Templates.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Template_Manager.php';

        $this->loader = new WP_Mail_Master_Loader();
    }

    private function set_locale()
    {
        $plugin_i18n = new WP_Mail_Master_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks()
    {
        $plugin_admin = new WP_Mail_Master_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_settings = new WP_Mail_Master_Settings($this->get_plugin_name(), $this->get_version());
        $plugin_security = new WP_Mail_Master_Security($this->get_plugin_name(), $this->get_version());
        $plugin_security_logs = new WP_Mail_Master_Security_Logs($this->get_plugin_name(), $this->get_version());
        $error_logs = new WP_Mail_Master_Error_Logs();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_assets');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');
        $this->loader->add_action('admin_init', $plugin_security, 'register_settings');
        
        // Add error logs handlers
        $this->loader->add_action('admin_post_delete_error_log', $error_logs, 'handle_error_log_deletion');
        $this->loader->add_action('admin_post_clear_error_logs', $error_logs, 'handle_clear_error_logs');
    }

    private function define_email_hooks()
    {
        $this->loader->add_filter('pre_wp_mail', $this, 'override_wp_mail', 999999, 5);
    }

    public function override_wp_mail($pre_wp_mail, $to, $subject = '', $message = '', $headers = '', $attachments = array())
    {
        try {
            error_log('WP Mail Master - Starting wp_mail override');
            
            if ($pre_wp_mail !== null) {
                error_log('WP Mail Master - Email already handled by another plugin');
                return $pre_wp_mail;
            }

            error_log('WP Mail Master - Initial arguments: ' . print_r([
                'to' => $to,
                'subject' => $subject,
                'message' => substr($message, 0, 100) . '...', 
                'headers' => $headers,
                'attachments' => $attachments
            ], true));

            $emailer = new WP_Mail_Master_Emailer();

            if (is_array($to)) {
                error_log('WP Mail Master - Processing array input');
                if (isset($to['to'])) {
                    $args = $to;
                } else {
                    $args = array(
                        'to' => $to,
                        'subject' => $subject,
                        'message' => $message,
                        'headers' => $headers,
                        'attachments' => $attachments
                    );
                }
                
                $to = $args['to'];
                $subject = $args['subject'];
                $message = $args['message'];
                $headers = $args['headers'];
                $attachments = $args['attachments'];
            }

            if (!is_array($headers)) {
                $headers = explode("\n", str_replace("\r\n", "\n", $headers));
            }
            error_log('WP Mail Master - Normalized headers: ' . print_r($headers, true));

            if (!is_array($attachments)) {
                $attachments = explode("\n", str_replace("\r\n", "\n", $attachments));
            }
            error_log('WP Mail Master - Normalized attachments: ' . print_r($attachments, true));

            error_log('WP Mail Master - Calling send_email');
            $result = $emailer->send_email($to, $subject, $message, $headers, $attachments);
            
            error_log('WP Mail Master - Send result: ' . var_export($result, true));
            
            return true;

        } catch (\Exception $e) {
            error_log('WP Mail Master - wp_mail override error: ' . $e->getMessage());
            error_log('WP Mail Master - Error trace: ' . $e->getTraceAsString());
            return false;
        }
    }

    public function enqueue_scripts() {
        // Enqueue existing scripts
        wp_enqueue_script(
            'wp-mail-master-admin',
            plugin_dir_url(WP_MAIL_MASTER_PLUGIN_FILE) . 'assets/js/wp-mail-master-admin.js',
            array('jquery'),
            WP_MAIL_MASTER_VERSION,
            true
        );

        // Enqueue honeypot script for all pages (not just admin)
        wp_enqueue_script(
            'wp-mail-master-honeypot',
            plugin_dir_url(WP_MAIL_MASTER_PLUGIN_FILE) . 'assets/js/wp-mail-master-honeypot.js',
            array('jquery'),
            WP_MAIL_MASTER_VERSION,
            true
        );

        // Add honeypot CSS
        wp_add_inline_style(
            'wp-mail-master-admin',
            '.wp-mail-master-hp { position: absolute !important; visibility: hidden !important; }'
        );

        // Localize script
        wp_localize_script('wp-mail-master-admin', 'wp_mail_master', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_mail_master_nonce')
        ));
    }

    public function run()
    {
        $this->loader->run();
    }

    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    public function get_loader()
    {
        return $this->loader;
    }

    public function get_version()
    {
        return $this->version;
    }
}
