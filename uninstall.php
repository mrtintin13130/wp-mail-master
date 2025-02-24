<?php

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin constants if not already defined
if (!defined('WP_MAIL_MASTER_PLUGIN_DIR')) {
    define('WP_MAIL_MASTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

// Include necessary files
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

global $wpdb;

// Drop plugin tables
$tables = [
    $wpdb->prefix . 'wp_mail_master_logs'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Clear scheduled hooks
wp_clear_scheduled_hook('wp_mail_master_cleanup');

// Remove plugin options
delete_option('wp_mail_master_settings');
delete_option('wp_mail_master_db_version');

// Remove all plugin options
$options_to_delete = array(
    'wp_mail_master_log_retention_days',
    'wp_mail_master_options',
    'wp_mail_master_templates',
    'wp_mail_master_selected_template',
    'wp_mail_master_custom_templates',
    'wp_mail_master_security_options',
    'wp_mail_master_spam_keywords',
    'wp_mail_master_ip_blacklist',
    'wp_mail_master_encrypt_key'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Remove any transients
delete_transient('wp_mail_master_cache');

// Clean up user meta if any
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}usermeta WHERE meta_key LIKE %s",
    'wp_mail_master_%'
));

// Clear any cached data
wp_cache_flush(); 