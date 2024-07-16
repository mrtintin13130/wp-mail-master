<?php

// public/class-wp-mail-master-public.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Public {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, WP_MAIL_MASTER_PLUGIN_URL . 'public/css/wp-mail-master-public.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, WP_MAIL_MASTER_PLUGIN_URL . 'public/js/wp-mail-master-public.js', array('jquery'), $this->version, false);
    }

    // Add any public-facing functions here
}