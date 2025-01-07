<?php
// admin/WP_Mail_Master_Dashboard_Widget.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Dashboard_Widget
{
    public function __construct()
    {
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }

    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget(
            'wp_mail_master_dashboard_widget',
            __('Email Logs Overview', 'wp-mail-master'),
            array($this, 'display_dashboard_widget')
        );
    }

    public function enqueue_dashboard_scripts()
    {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
        wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns', array('chart-js'), null, true);

        // Add custom CSS
        wp_add_inline_style('wp-admin', '
            .wp-mail-master-widget-container {
                background: #fff;
            }
            .wp-mail-master-stats {
                display: flex;
                gap: 20px;
                margin-bottom: 20px;
                padding: 10px 0;
            }
            .wp-mail-master-stat-box {
                flex: 1;
                padding: 15px;
                border-radius: 6px;
                background: #f8f9fa;
                text-align: center;
            }
            .wp-mail-master-stat-box h3 {
                margin: 0 0 5px 0;
                font-size: 24px;
                font-weight: 600;
            }
            .wp-mail-master-stat-box p {
                margin: 0;
                color: #666;
                font-size: 14px;
            }
            .wp-mail-master-chart-container {
                position: relative;
                height: 300px;
            }
        ');
    }

    public function display_dashboard_widget()
    {
        global $wpdb;
        if (!$wpdb) {
            return;
        }
        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        // Get total counts for the summary using a single query
        $counts = $wpdb->get_results("
            SELECT status, COUNT(*) as count 
            FROM $table_name 
            WHERE status IN ('success', 'error') 
            GROUP BY status
        ");

        $total_sent = 0;
        $total_errors = 0;
        foreach ($counts as $count) {
            if ($count->status === 'success') {
                $total_sent = $count->count;
            } else if ($count->status === 'error') {
                $total_errors = $count->count;
            }
        }

        // Create a date range for the last 30 days
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));

        // Get all data in a single query
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(time) as log_date, 
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count
            FROM $table_name
            WHERE DATE(time) BETWEEN %s AND %s
            GROUP BY DATE(time)
            ORDER BY log_date ASC
        ", $start_date, $end_date));

        // Prepare data arrays
        $dates = array();
        $sent_data = array();
        $error_data = array();

        // Generate date range and fill with data
        $current_date = $start_date;
        while (strtotime($current_date) <= strtotime($end_date)) {
            $dates[] = $current_date;
            $sent_data[] = 0;
            $error_data[] = 0;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }

        // Fill in actual data
        foreach ($logs as $log) {
            $index = array_search($log->log_date, $dates);
            if ($index !== false) {
                $sent_data[$index] = (int) $log->sent_count;
                $error_data[$index] = (int) $log->error_count;
            }
        }

        // Include the view file
        include_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
    }
}