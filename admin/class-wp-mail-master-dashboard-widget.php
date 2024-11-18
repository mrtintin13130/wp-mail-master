<?php

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
                padding: 15px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        // Get total counts for the summary
        $total_sent = $wpdb->get_var("
            SELECT COUNT(*) FROM $table_name WHERE status = 'success'
        ");
        $total_errors = $wpdb->get_var("
            SELECT COUNT(*) FROM $table_name WHERE status = 'error'
        ");

        // Create a date range for the last 30 days
        $end_date = date('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-30 days'));

        // Generate an array of all dates in the range
        $date_range = array();
        $current_date = $start_date;
        while (strtotime($current_date) <= strtotime($end_date)) {
            $date_range[] = $current_date;
            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
        }

        // Get sent emails data with proper date formatting
        $sent_logs = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(time) as log_date, COUNT(*) as sent_count
            FROM $table_name
            WHERE status = 'success'
            AND DATE(time) BETWEEN %s AND %s
            GROUP BY DATE(time)
            ORDER BY log_date ASC
        ", $start_date, $end_date));

        // Get error logs data with proper date formatting
        $error_logs = $wpdb->get_results($wpdb->prepare("
            SELECT DATE(time) as log_date, COUNT(*) as error_count
            FROM $table_name
            WHERE status = 'error'
            AND DATE(time) BETWEEN %s AND %s
            GROUP BY DATE(time)
            ORDER BY log_date ASC
        ", $start_date, $end_date));

        // Create associative arrays for easy lookup
        $sent_data_map = array();
        foreach ($sent_logs as $log) {
            $sent_data_map[$log->log_date] = $log->sent_count;
        }

        $error_data_map = array();
        foreach ($error_logs as $log) {
            $error_data_map[$log->log_date] = $log->error_count;
        }

        // Prepare complete data arrays including zeros for missing dates
        $dates = array();
        $sent_data = array();
        $error_data = array();

        foreach ($date_range as $date) {
            $dates[] = $date;
            $sent_data[] = isset($sent_data_map[$date]) ? $sent_data_map[$date] : 0;
            $error_data[] = isset($error_data_map[$date]) ? $error_data_map[$date] : 0;
        }

        ?>
        <div class="wp-mail-master-widget-container">
            <div class="wp-mail-master-stats">
                <div class="wp-mail-master-stat-box">
                    <h3><?php echo number_format($total_sent); ?></h3>
                    <p><?php _e('Total Emails Sent', 'wp-mail-master'); ?></p>
                </div>
                <div class="wp-mail-master-stat-box">
                    <h3><?php echo number_format($total_errors); ?></h3>
                    <p><?php _e('Total Errors', 'wp-mail-master'); ?></p>
                </div>
            </div>
            <div class="wp-mail-master-chart-container">
                <canvas id="wp-mail-master-chart"></canvas>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var ctx = document.getElementById('wp-mail-master-chart').getContext('2d');

                // Custom gradient for the sent emails line
                var sentGradient = ctx.createLinearGradient(0, 0, 0, 400);
                sentGradient.addColorStop(0, 'rgba(76, 175, 80, 0.4)');
                sentGradient.addColorStop(1, 'rgba(76, 175, 80, 0)');

                // Custom gradient for the errors line
                var errorGradient = ctx.createLinearGradient(0, 0, 0, 400);
                errorGradient.addColorStop(0, 'rgba(244, 67, 54, 0.4)');
                errorGradient.addColorStop(1, 'rgba(244, 67, 54, 0)');

                var chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($dates); ?>,
                        datasets: [
                            {
                                label: 'Sent Emails',
                                data: <?php echo json_encode($sent_data); ?>,
                                borderColor: '#4CAF50',
                                backgroundColor: sentGradient,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#4CAF50',
                                pointBorderWidth: 2
                            },
                            {
                                label: 'Errors',
                                data: <?php echo json_encode($error_data); ?>,
                                borderColor: '#F44336',
                                backgroundColor: errorGradient,
                                borderWidth: 2,
                                fill: true,
                                tension: 0.4,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#F44336',
                                pointBorderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        },
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: {
                                        size: 13
                                    }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(255, 255, 255, 0.95)',
                                titleColor: '#333',
                                bodyColor: '#666',
                                borderColor: '#e0e0e0',
                                borderWidth: 1,
                                padding: 12,
                                boxPadding: 6,
                                usePointStyle: true,
                                callbacks: {
                                    title: function (context) {
                                        return new Date(context[0].label).toLocaleDateString(undefined, {
                                            weekday: 'long',
                                            year: 'numeric',
                                            month: 'long',
                                            day: 'numeric'
                                        });
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                type: 'time',
                                time: {
                                    unit: 'day',
                                    tooltipFormat: 'MMM dd, yyyy',
                                    displayFormats: {
                                        day: 'MMM dd'
                                    }
                                },
                                grid: {
                                    display: false
                                },
                                border: {
                                    display: true
                                }
                            },
                            y: {
                                beginAtZero: true,
                                grid: {
                                    borderDash: [2, 4],
                                    color: '#e0e0e0'
                                },
                                ticks: {
                                    stepSize: 1,
                                    precision: 0
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