<?php
// Prevent direct access
if (!defined('WPINC')) {
    die;
}

// Ensure required variables are passed
if (
    !isset($dates) || !isset($sent_data) || !isset($error_data) ||
    !isset($total_sent) || !isset($total_errors)
) {
    return;
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