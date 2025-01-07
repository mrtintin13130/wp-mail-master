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
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Admin.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Ajax.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Logs.php';
        require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Settings.php';
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

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_assets');
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_settings, 'register_settings');
    }

    private function define_email_hooks()
    {
        $this->loader->add_filter('wp_mail', $this, 'override_wp_mail');
    }

    public function override_wp_mail($atts)
    {
        $emailer = new WP_Mail_Master_Emailer();

        // Extract the wp_mail attributes
        $to = $atts['to'];
        $subject = $atts['subject'];
        $message = $atts['message'];
        $headers = isset($atts['headers']) ? $atts['headers'] : array();
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $attachments = isset($atts['attachments']) ? $atts['attachments'] : array();

        // Use the custom send_email method
        return $emailer->send_email($to, $subject, $message, $headers, $attachments);
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
