<?php
// admin/WP_Mail_Master_Error_Logs.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Error_Logs
{
    public function display_error_logs_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $error_handler = WP_Mail_Master_Error_Handler::getInstance();
        $errors = $error_handler->getErrors();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Error Logs', 'wp-mail-master'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Error Code', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Message', 'wp-mail-master'); ?></th>
                        <th><?php echo esc_html__('Details', 'wp-mail-master'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($errors)): ?>
                        <tr>
                            <td colspan="4"><?php echo esc_html__('No errors logged.', 'wp-mail-master'); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($errors as $error):
                        $details = json_decode($error->error_details, true);
                        ?>
                        <tr>
                            <td><?php echo esc_html(
                                date_i18n('Y-m-d H:i:s', strtotime($error->created_at))
                            ); ?></td>
                            <td><?php echo esc_html($error->error_code); ?></td>
                            <td><?php echo esc_html($error->error_message); ?></td>
                            <td>
                                <?php if (defined('WP_DEBUG') && WP_DEBUG): ?>
                                    <details>
                                        <summary><?php echo esc_html__('View Details', 'wp-mail-master'); ?></summary>
                                        <pre><?php echo esc_html(
                                            print_r($details, true)
                                        ); ?></pre>
                                    </details>
                                <?php else: ?>
                                    <?php echo esc_html__('Enable WP_DEBUG to view details', 'wp-mail-master'); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}