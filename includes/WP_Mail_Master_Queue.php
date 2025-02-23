<?php
// includes/WP_Mail_Master_Queue.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Queue
{
    const QUEUE_TABLE = 'wp_mail_master_queue';

    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('wp_mail_master_process_queue', array($this, 'process_queue'));

        if (!wp_next_scheduled('wp_mail_master_process_queue')) {
            wp_schedule_event(time(), 'every_five_minutes', 'wp_mail_master_process_queue');
        }
    }

    public function add_to_queue($to, $subject, $message, $headers = '', $attachments = array())
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;

        return $wpdb->insert(
            $table_name,
            array(
                'to_email' => $to,
                'subject' => $subject,
                'message' => $message,
                'headers' => maybe_serialize($headers),
                'attachments' => maybe_serialize($attachments),
                'status' => 'pending',
                'created_at' => current_time('mysql'),
                'attempts' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
    public function process_queue()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;

        // Get pending emails with limit
        $emails = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name 
            WHERE status = 'pending' 
            AND attempts < 3 
            ORDER BY created_at ASC 
            LIMIT %d
        ", 50));

        if (!$emails) {
            return;
        }

        $emailer = new WP_Mail_Master_Emailer($this->plugin_name, $this->version);

        foreach ($emails as $email) {
            $result = $emailer->send_email(
                $email->to_email,
                $email->subject,
                $email->message,
                maybe_unserialize($email->headers),
                maybe_unserialize($email->attachments)
            );

            if ($result !== null && $result !== false) {
                $wpdb->delete($table_name, array('id' => $email->id));
            } else {
                $wpdb->update(
                    $table_name,
                    array(
                        'attempts' => $email->attempts + 1,
                        'last_attempt' => current_time('mysql'),
                        'status' => ($email->attempts >= 2) ? 'failed' : 'pending'
                    ),
                    array('id' => $email->id)
                );
            }
        }
    }
    public function create_queue_table()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $table_name = $wpdb->prefix . self::QUEUE_TABLE;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            to_email varchar(100) NOT NULL,
            subject varchar(255) NOT NULL,
            message longtext NOT NULL,
            headers text,
            attachments text,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL,
            last_attempt datetime DEFAULT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}