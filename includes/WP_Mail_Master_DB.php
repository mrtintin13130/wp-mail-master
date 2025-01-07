<?php
// includes/WP_Mail_Master_DBdb.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_DB
{
    private $db_version = '1.0.1'; // Increment this when making db changes

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'check_db_version'));
    }

    public function check_db_version()
    {
        if (get_option('wp_mail_master_db_version') !== $this->db_version) {
            $this->upgrade();
        }
    }

    public function create_tables()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $charset_collate = $wpdb->get_charset_collate();

        // Email logs table
        $logs_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_mail_master_logs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            to_email varchar(100) NOT NULL,
            subject varchar(255) NOT NULL,
            message text NOT NULL,
            status varchar(20) NOT NULL,
            error_info text,
            PRIMARY KEY  (id),
            KEY idx_time (time),
            KEY idx_status (status),
            KEY idx_to_email (to_email)
        ) $charset_collate;";

        // Error logs table
        $errors_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_mail_master_errors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            error_code int(11) NOT NULL,
            error_message text NOT NULL,
            error_details longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($logs_table);
        dbDelta($errors_table);

        update_option('wp_mail_master_db_version', $this->db_version);
    }
    public function upgrade()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $installed_version = get_option('wp_mail_master_db_version');

        if (version_compare($installed_version, '1.0.1', '<')) {
            // Add new indexes to logs table
            $logs_table = $wpdb->prefix . 'wp_mail_master_logs';
            $wpdb->query("ALTER TABLE $logs_table ADD INDEX idx_time (time)");
            $wpdb->query("ALTER TABLE $logs_table ADD INDEX idx_status (status)");
            $wpdb->query("ALTER TABLE $logs_table ADD INDEX idx_to_email (to_email)");

            // Create errors table if it doesn't exist
            $this->create_tables();
        }

        update_option('wp_mail_master_db_version', $this->db_version);
    }
    public function cleanup_old_logs()
    {
        global $wpdb;

        if (!$wpdb) {
            return;
        }

        // Clean up email logs
        $logs_table = $wpdb->prefix . 'wp_mail_master_logs';
        $days = apply_filters('wp_mail_master_logs_retention', 30);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $logs_table WHERE time < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));

        // Clean up error logs
        $errors_table = $wpdb->prefix . 'wp_mail_master_errors';
        $error_days = apply_filters('wp_mail_master_error_logs_retention', 30);
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $errors_table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $error_days
        ));
    }
    public function delete_tables()
    {
        global $wpdb;

        if (!$wpdb) {
            return;
        }

        $tables = array(
            $wpdb->prefix . 'wp_mail_master_logs',
            $wpdb->prefix . 'wp_mail_master_errors'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }

        delete_option('wp_mail_master_db_version');
    }
}