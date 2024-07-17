<?php

// admin/class-wp-mail-master-logs.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Logs
{
    public function __construct()
    {
    }

    public function display_email_logs_page()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;

        if ($log_id > 0) {
            $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $log_id));
            if ($log) {
                $this->display_single_log($log);
                return;
            }
        }

        $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY time DESC");

        ob_start();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email Logs', 'wp-mail-master'); ?></h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('To', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Subject', 'wp-mail-master'); ?></th>
                        <!-- <th><?php echo esc_html__('Message', 'wp-mail-master'); ?></th> -->
                        <th><?php echo esc_html__('Status', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Error Info', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Action', 'wp-mail-master'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d/m/Y - H:i:s', strtotime($log->time))); ?></td>
                            <td><?php echo esc_html($log->to_email); ?></td>
                            <td><?php echo esc_html($log->subject); ?></td>
                            <!-- <td><?php echo esc_html(substr($log->message, 0, 100)); ?>...</td> -->
                            <td><?php echo esc_html($log->status); ?></td>
                            <td><?php echo esc_html(substr($log->error_info, 0, 100)); ?>...</td>
                            <td>
                                <a
                                    href="<?php echo esc_url(add_query_arg('log_id', $log->id, admin_url('admin.php?page=wp-mail-master-email-logs'))); ?>">
                                    <?php echo esc_html__('View', 'wp-mail-master'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        echo ob_get_clean();
    }

    private function display_single_log($log)
    {
        $back_url = admin_url('admin.php?page=wp-mail-master-email-logs');

        ob_start();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email Log Details', 'wp-mail-master'); ?></h1>
            <p><a href="<?php echo esc_url($back_url); ?>">&laquo;
                    <?php echo esc_html__('Back to logs', 'wp-mail-master'); ?></a></p>
            <table class="form-table">
                <tr>
                    <th><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html(date_i18n('d/m/Y - H:i:s', strtotime($log->time))); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('To', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html($log->to_email); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Subject', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html($log->subject); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Message', 'wp-mail-master'); ?></th>
                    <td><?php echo wp_kses_post($log->message); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Status', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html($log->status); ?></td>
                </tr>
                <tr>
                    <th><?php echo esc_html__('Error Info', 'wp-mail-master'); ?></th>
                    <td><?php echo wp_kses_post($log->error_info); ?></td>
                </tr>
            </table>
        </div>
        <?php
        echo ob_get_clean();
    }

}
