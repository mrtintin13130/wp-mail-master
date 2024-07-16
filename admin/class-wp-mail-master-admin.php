<?php

// admin/class-wp-mail-master-admin.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Admin
{
    private $plugin_name;
    private $version;
    private $logs_instance;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->logs_instance = new WP_Mail_Master_Logs();

        // Initialize other admin classes
        new WP_Mail_Master_Settings($this->plugin_name, $this->version);
        new WP_Mail_Master_Ajax();

        // add_action('wp_ajax_send_test_email', array($this, 'send_test_email'));        
    }

    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, WP_MAIL_MASTER_PLUGIN_URL . 'admin/css/wp-mail-master-admin.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script($this->plugin_name, WP_MAIL_MASTER_PLUGIN_URL . 'admin/js/wp-mail-master-admin.js', array('jquery'), $this->version, false);

        // Localize the script with the AJAX URL
        wp_localize_script($this->plugin_name, 'wp_mail_master', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('send_test_email_nonce')
        ));
    }

    public function add_plugin_admin_menu()
    {
        add_menu_page(
            'WP Mail Master Settings',
            'WP Mail Master',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-email-alt'
        );

        // Add submenu for email logs
        add_submenu_page(
            $this->plugin_name,
            __('Email Logs', 'wp-mail-master'),
            __('Email Logs', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-email-logs',
            array($this->logs_instance, 'display_email_logs_page')
            
        );
    }

    public function display_plugin_setup_page()
    {
        include_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/partials/wp-mail-master-admin-display.php';

    }
}