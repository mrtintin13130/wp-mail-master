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

    public function enqueue_dashboard_scripts($hook)
    {
        // Only load on dashboard page
        if ('index.php' !== $hook) {
            return;
        }

        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
        wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns', array('chart-js'), null, true);

        // Add custom CSS
        wp_add_inline_style('wp-admin', '
            #dashboard-widgets #wp_mail_master_dashboard_widget .inside {
                margin: 0;
                padding: 0;
            }
            #dashboard-widgets .wp-mail-master-widget-container {
                padding: 12px;
                margin: 0;
                position: relative;
            }
            #dashboard-widgets .wp-mail-master-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 12px;
                margin-bottom: 20px;
            }
            #dashboard-widgets .wp-mail-master-stat-box {
                padding: 15px;
                border-radius: 6px;
                text-align: center;
                transition: transform 0.2s ease;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            #dashboard-widgets .wp-mail-master-stat-box:hover {
                transform: translateY(-2px);
            }
            #dashboard-widgets .wp-mail-master-stat-box h3 {
                margin: 0 0 5px 0;
                font-size: 24px;
                font-weight: 600;
                line-height: 1.2;
                color: inherit;
            }
            #dashboard-widgets .wp-mail-master-stat-box p {
                margin: 0;
                font-size: 13px;
                opacity: 0.9;
                color: inherit;
            }
            #dashboard-widgets .wp-mail-master-stat-box.stat-success {
                background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
                color: white;
            }
            #dashboard-widgets .wp-mail-master-stat-box.stat-error {
                background: linear-gradient(135deg, #F44336 0%, #e53935 100%);
                color: white;
            }
            #dashboard-widgets .wp-mail-master-stat-box.stat-blocked {
                background: linear-gradient(135deg, #FF9800 0%, #fb8c00 100%);
                color: white;
            }
            #dashboard-widgets .wp-mail-master-chart-container {
                position: relative;
                height: 300px;
                margin-top: 20px;
                padding: 10px;
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
        $counts = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count 
            FROM $table_name 
            WHERE status IN ('success', 'error', 'spam', 'blocked')
            GROUP BY status
        "));

        $total_sent = 0;
        $total_errors = 0;
        $total_blocked = 0; // Combined spam and blocked

        if ($counts) {
            foreach ($counts as $count) {
                switch ($count->status) {
                    case 'success':
                        $total_sent = (int)$count->count;
                        break;
                    case 'error':
                        $total_errors = (int)$count->count;
                        break;
                    case 'spam':
                    case 'blocked':
                        $total_blocked += (int)$count->count;
                        break;
                }
            }
        }

        // Create a date range for the last 30 days
        $end_date = current_time('Y-m-d 23:59:59');
        $start_date = date('Y-m-d 00:00:00', strtotime('-30 days', strtotime($end_date)));

        // Get all data in a single query
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created_at) as log_date,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as error_count,
                SUM(CASE WHEN status IN ('spam', 'blocked') THEN 1 ELSE 0 END) as blocked_count
            FROM $table_name
            WHERE created_at BETWEEN %s AND %s
            GROUP BY DATE(created_at)
            ORDER BY log_date ASC
        ", $start_date, $end_date));

        // Initialize arrays with dates as keys
        $dates = array();
        $data_by_date = array();

        // Generate the last 30 days dates and initialize data structure
        for ($i = 30; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dates[] = $date;
            $data_by_date[$date] = array(
                'sent' => 0,
                'error' => 0,
                'blocked' => 0
            );
        }

        // Fill in the actual data
        if ($logs) {
            foreach ($logs as $log) {
                if (isset($data_by_date[$log->log_date])) {
                    $data_by_date[$log->log_date] = array(
                        'sent' => (int)$log->sent_count,
                        'error' => (int)$log->error_count,
                        'blocked' => (int)$log->blocked_count
                    );
                }
            }
        }

        // Convert data structure to arrays for the chart
        $sent_data = array();
        $error_data = array();
        $blocked_data = array();

        foreach ($dates as $date) {
            $sent_data[] = $data_by_date[$date]['sent'];
            $error_data[] = $data_by_date[$date]['error'];
            $blocked_data[] = $data_by_date[$date]['blocked'];
        }

        // Include the view file
        include_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
    }
}