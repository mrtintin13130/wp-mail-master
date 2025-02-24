<?php
// includes/WP_Mail_Master_DBdb.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_DB
{
    private $db_version = '1.1.0'; // Increment this when making db changes

    public function __construct()
    {
        add_action('plugins_loaded', array($this, 'check_db_version'));
    }

    public function check_db_version()
    {
        global $wpdb;
        
        // Check if the table exists
        $table_name = $wpdb->prefix . 'wp_mail_master_logs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
        
        if (!$table_exists || get_option('wp_mail_master_db_version') !== $this->db_version) {
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

        // Unified logs table
        $logs_table = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wp_mail_master_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            type varchar(20) NOT NULL,
            level varchar(10) NOT NULL,
            status varchar(10) NOT NULL,
            message text NOT NULL,
            details longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_created_at (created_at),
            KEY idx_type (type),
            KEY idx_status (status),
            KEY idx_level (level)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($logs_table);

        update_option('wp_mail_master_db_version', $this->db_version);
    }

    public function upgrade()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        
        // Always create tables to ensure correct structure
        $this->create_tables();
        
        $installed_version = get_option('wp_mail_master_db_version');
        if (version_compare($installed_version, '1.1.0', '<')) {
            // Update existing warning statuses to blocked using prepared statement
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->prefix}wp_mail_master_logs SET status = %s WHERE status = %s AND metadata LIKE %s",
                    'blocked',
                    'warning',
                    '%Spam protection:%'
                )
            );
            
            // Migrate existing logs if old tables exist
            $this->migrate_old_logs();
            // Drop old tables
            $this->drop_old_tables();
        }

        update_option('wp_mail_master_db_version', $this->db_version);
    }

    private function migrate_old_logs()
    {
        global $wpdb;
        
        // Migrate email logs using prepared statement
        $old_email_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_mail_master_logs WHERE 1=%d",
                1
            )
        );
        if ($old_email_logs) {
            foreach ($old_email_logs as $log) {
                $metadata = [
                    'to_email' => $log->to_email,
                    'subject' => $log->subject
                ];
                if (!empty($log->error_info)) {
                    $metadata['error_info'] = $log->error_info;
                }

                $wpdb->insert(
                    $wpdb->prefix . 'wp_mail_master_logs',
                    [
                        'created_at' => $log->time,
                        'type' => 'email',
                        'level' => $log->status === 'failed' ? 'error' : 'info',
                        'status' => $log->status === 'sent' ? 'success' : $log->status,
                        'message' => $log->message,
                        'metadata' => json_encode($metadata)
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s']
                );
            }
        }

        // Migrate error logs using prepared statement
        $old_error_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}wp_mail_master_errors WHERE 1=%d",
                1
            )
        );
        if ($old_error_logs) {
            foreach ($old_error_logs as $log) {
                $wpdb->insert(
                    $wpdb->prefix . 'wp_mail_master_logs',
                    [
                        'created_at' => $log->created_at,
                        'type' => 'error',
                        'level' => 'error',
                        'status' => 'error',
                        'message' => $log->error_message,
                        'details' => $log->error_details,
                        'metadata' => json_encode(['error_code' => $log->error_code])
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
            }
        }
    }

    private function drop_old_tables()
    {
        global $wpdb;
        $old_tables = [
            $wpdb->prefix . 'wp_mail_master_logs',
            $wpdb->prefix . 'wp_mail_master_errors',
            $wpdb->prefix . 'wp_mail_master_security_logs'
        ];

        foreach ($old_tables as $table) {
            $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS %i", $table));
        }
    }

    public function cleanup_old_logs()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $retention_days = absint(get_option('wp_mail_master_log_retention_days', 30));
        
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}wp_mail_master_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $retention_days
            )
        );
    }

    public function delete_tables()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }

        $tables = [
            $wpdb->prefix . 'wp_mail_master_logs'
        ];

        foreach ($tables as $table) {
            $wpdb->query(
                $wpdb->prepare("DROP TABLE IF EXISTS %i", $table)
            );
        }

        delete_option('wp_mail_master_db_version');
    }

    public function insert_log($type, $level, $status, $message, $details = null, $metadata = null)
    {
        global $wpdb;
        if (!$wpdb) {
            return false;
        }

        $data = [
            'created_at' => current_time('mysql'),
            'type' => $type,
            'level' => $level,
            'status' => $status,
            'message' => $message,
            'details' => is_null($details) ? null : json_encode($details),
            'metadata' => is_null($metadata) ? null : json_encode($metadata)
        ];

        return $wpdb->insert($wpdb->prefix . 'wp_mail_master_logs', $data);
    }

    public function get_logs($page = 1, $per_page = 20, $filters = [])
    {
        global $wpdb;
        if (!$wpdb) {
            return [
                'logs' => [],
                'total_items' => 0,
                'total_pages' => 0
            ];
        }

        // Sanitize inputs
        $page = absint($page);
        $per_page = absint($per_page);
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = [];
        $where_values = [];

        if (!empty($filters['search'])) {
            $where_conditions[] = "(message LIKE %s OR details LIKE %s)";
            $search_term = '%' . $wpdb->esc_like(sanitize_text_field($filters['search'])) . '%';
            array_push($where_values, $search_term, $search_term);
        }

        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $where_conditions[] = "type = %s";
            $where_values[] = sanitize_key($filters['type']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $where_conditions[] = "status = %s";
            $where_values[] = sanitize_key($filters['status']);
        }

        if (!empty($filters['level']) && $filters['level'] !== 'all') {
            $where_conditions[] = "level = %s";
            $where_values[] = sanitize_key($filters['level']);
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Add pagination parameters
        array_push($where_values, $per_page, $offset);

        $query = $wpdb->prepare(
            "SELECT SQL_CALC_FOUND_ROWS *
            FROM {$wpdb->prefix}wp_mail_master_logs
            {$where_clause}
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $where_values
        );

        $logs = $wpdb->get_results($query);
        $total_items = (int) $wpdb->get_var("SELECT FOUND_ROWS()");

        return [
            'logs' => $logs,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / $per_page)
        ];
    }

    public function get_log_by_id($id)
    {
        global $wpdb;
        if (!$wpdb) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}wp_mail_master_logs WHERE id = %d",
            $id
        ));
    }
}