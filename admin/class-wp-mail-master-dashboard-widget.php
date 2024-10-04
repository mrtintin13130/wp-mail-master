<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Dashboard_Widget
{

    public function __construct()
    {
        // Hook into the WordPress dashboard setup action to add the widget
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }

    /**
     * Add the custom dashboard widget
     */
    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget(
            'wp_mail_master_dashboard_widget',
            __('Email Logs Overview', 'wp-mail-master'),
            array($this, 'display_dashboard_widget')
        );
    }

    /**
     * Enqueue the Chart.js library
     */
    public function enqueue_dashboard_scripts()
    {
        // Enqueue Chart.js
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);

        // Enqueue the date adapter for Chart.js
        wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns', array('chart-js'), null, true);
    }


    /**
     * Display the content inside the dashboard widget
     */
    public function display_dashboard_widget()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        // Fetch sent emails and errors, grouped by date
        $sent_logs = $wpdb->get_results("
            SELECT DATE(time) as log_date, COUNT(*) as sent_count
            FROM $table_name
            WHERE status = 'success'
            GROUP BY log_date
            ORDER BY log_date ASC
        ");

        $error_logs = $wpdb->get_results("
            SELECT DATE(time) as log_date, COUNT(*) as error_count
            FROM $table_name
            WHERE status = 'error'
            GROUP BY log_date
            ORDER BY log_date ASC
        ");

        // Prepare data for the chart
        $dates = [];
        $sent_data = [];
        $error_data = [];

        foreach ($sent_logs as $log) {
            $dates[] = $log->log_date;
            $sent_data[] = $log->sent_count;
        }

        foreach ($error_logs as $log) {
            $error_data[] = $log->error_count;
        }

        ?>
        <div>
            <canvas id="wp-mail-master-chart" width="400" height="200"></canvas>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var ctx = document.getElementById('wp-mail-master-chart').getContext('2d');
                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($dates); ?>, // Dates for the X-axis
                        datasets: [
                            {
                                label: 'Sent Emails',
                                data: <?php echo json_encode($sent_data); ?>, // Sent emails for each date
                                borderColor: '#4CAF50',
                                fill: false
                            },
                            {
                                label: 'Errors',
                                data: <?php echo json_encode($error_data); ?>, // Errors for each date
                                borderColor: '#F44336',
                                fill: false
                            }
                        ]
                    },
                    options: {
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day', // You can adjust this to 'week', 'month', etc.
                                    tooltipFormat: 'MMM DD, YYYY', // Custom date format for tooltips
                                }
                            }
                        }
                    }

                });
            });
        </script>
        <?php
    }
}
