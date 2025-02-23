<?php
// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Initialize variables with defaults
$total_sent = $total_sent ?? 0;
$total_errors = $total_errors ?? 0;
$total_blocked = $total_blocked ?? 0;
$dates = $dates ?? array();
$sent_data = $sent_data ?? array();
$error_data = $error_data ?? array();
$blocked_data = $blocked_data ?? array();

// Generate a unique ID for the chart
$chart_id = 'wp-mail-master-chart-' . wp_rand();
?>
<div class="wp-mail-master-widget-container">
    <div class="wp-mail-master-stats">
        <div class="wp-mail-master-stat-box stat-success">
            <h3><?php echo esc_html(number_format($total_sent)); ?></h3>
            <p><?php esc_html_e('Emails Sent', 'wp-mail-master'); ?></p>
        </div>
        <div class="wp-mail-master-stat-box stat-error">
            <h3><?php echo esc_html(number_format($total_errors)); ?></h3>
            <p><?php esc_html_e('Errors', 'wp-mail-master'); ?></p>
        </div>
        <div class="wp-mail-master-stat-box stat-blocked">
            <h3><?php echo esc_html(number_format($total_blocked)); ?></h3>
            <p><?php esc_html_e('Blocked/Spam', 'wp-mail-master'); ?></p>
        </div>
    </div>
    <div class="wp-mail-master-chart-container">
        <canvas id="<?php echo esc_attr($chart_id); ?>"></canvas>
    </div>
</div>
<script>
    jQuery(document).ready(function($) {
        var ctx = document.getElementById('<?php echo esc_js($chart_id); ?>').getContext('2d');
        
        // Format dates and prepare data points
        var chartData = [];
        <?php
        // Format the data for output
        foreach ($dates as $index => $date) {
            $formatted_date = date('M j', strtotime($date));
            echo "chartData.push({
                date: '" . esc_js($formatted_date) . "',
                sent: " . (int)$sent_data[$index] . ",
                error: " . (int)$error_data[$index] . ",
                blocked: " . (int)$blocked_data[$index] . "
            });\n";
        }
        ?>

        // Custom gradients
        var sentGradient = ctx.createLinearGradient(0, 0, 0, 400);
        sentGradient.addColorStop(0, 'rgba(76, 175, 80, 0.4)');
        sentGradient.addColorStop(1, 'rgba(76, 175, 80, 0)');

        var errorGradient = ctx.createLinearGradient(0, 0, 0, 400);
        errorGradient.addColorStop(0, 'rgba(244, 67, 54, 0.4)');
        errorGradient.addColorStop(1, 'rgba(244, 67, 54, 0)');

        var blockedGradient = ctx.createLinearGradient(0, 0, 0, 400);
        blockedGradient.addColorStop(0, 'rgba(255, 152, 0, 0.4)');
        blockedGradient.addColorStop(1, 'rgba(255, 152, 0, 0)');

        // Create the chart
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.map(item => item.date),
                datasets: [
                    {
                        label: '<?php esc_html_e('Sent Emails', 'wp-mail-master'); ?>',
                        data: chartData.map(item => item.sent),
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
                        label: '<?php esc_html_e('Errors', 'wp-mail-master'); ?>',
                        data: chartData.map(item => item.error),
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
                    },
                    {
                        label: '<?php esc_html_e('Blocked/Spam', 'wp-mail-master'); ?>',
                        data: chartData.map(item => item.blocked),
                        borderColor: '#FF9800',
                        backgroundColor: blockedGradient,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#FF9800',
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
                        usePointStyle: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            maxRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
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