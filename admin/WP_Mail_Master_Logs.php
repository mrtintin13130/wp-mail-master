<?php

// admin/WP_Mail_Master_Logs.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Logs
{
    // Define as class constants instead of hardcoded values
    private const PER_PAGE = 10;
    private const MAX_CACHE_TIME = 300; // 5 minutes

    /**
     * Initialize the class
     */
    public function __construct()
    {
        add_action('admin_post_delete_log', array($this, 'handle_log_deletion'));
    }

    /**
     * Get logs data with caching
     * 
     * @param int $page_number Current page number
     * @return array
     */
    private function get_logs_data($page_number = 1, $search = '')
    {
        global $wpdb;
        if (!$wpdb) {
            return array(
                'logs' => array(),
                'total_items' => 0,
                'total_pages' => 0
            );
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        // Generate cache key based on page number and search term
        $cache_key = 'wp_mail_master_logs_' . $page_number . '_' . md5($search);

        // Try to get cached data
        $cached_data = wp_cache_get($cache_key);

        if (false !== $cached_data) {
            return $cached_data;
        }

        // Calculate the offset
        $offset = ($page_number - 1) * self::PER_PAGE;

        $where = '';
        $params = [self::PER_PAGE, $offset];

        if (!empty($search)) {
            $where = "WHERE to_email LIKE %s OR subject LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $search_term, $search_term);
        }

        $query = "SELECT SQL_CALC_FOUND_ROWS 
            id, time, to_email, subject, status
        FROM $table_name USE INDEX (idx_time) $where ORDER BY time DESC LIMIT %d OFFSET %d";
        $logs = $wpdb->get_results($wpdb->prepare($query, $params));

        // Get total items for pagination
        $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");

        $data = array(
            'logs' => $logs,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / self::PER_PAGE)
        );

        // Cache the results for 5 minutes
        wp_cache_set($cache_key, $data, '', self::MAX_CACHE_TIME);

        return $data;
    }
    /**
     * Display the logs page
     */
    public function display_email_logs_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-mail-master'));
        }

        // Get the current page number
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

        // Get logs data
        $data = $this->get_logs_data($current_page);
        $logs = $data['logs'];
        $total_pages = $data['total_pages'];

        // Check if viewing a single log
        if (isset($_GET['log_id']) && !empty($_GET['log_id'])) {
            $this->display_single_log(absint($_GET['log_id']));
            return;
        }

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Email Logs', 'wp-mail-master'); ?></h1>

            <!-- Add export button -->
            <a href="<?php echo esc_url(add_query_arg('action', 'export_logs')); ?>" class="page-title-action">
                <?php echo esc_html__('Export Logs', 'wp-mail-master'); ?>
            </a>

            <!-- Add search form -->
            <form method="get" class="search-box" style="margin-bottom: 1em;">
                <input type="hidden" name="page" value="wp-mail-master-email-logs">
                <label class="screen-reader-text" for="log-search-input">
                    <?php echo esc_html__('Search Logs:', 'wp-mail-master'); ?>
                </label>
                <input type="search" id="log-search-input" name="s"
                    value="<?php echo esc_attr(isset($_GET['s']) ? $_GET['s'] : ''); ?>">
                <input type="submit" class="button" value="<?php echo esc_attr__('Search Logs', 'wp-mail-master'); ?>">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                        <th scope="col"><?php echo esc_html__('To', 'wp-mail-master'); ?></th>
                        <th scope="col"><?php echo esc_html__('Subject', 'wp-mail-master'); ?></th>
                        <th scope="col"><?php echo esc_html__('Status', 'wp-mail-master'); ?></th>
                        <th scope="col"><?php echo esc_html__('Actions', 'wp-mail-master'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="5"><?php echo esc_html__('No logs found.', 'wp-mail-master'); ?></td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->time))); ?></td>
                            <td><?php echo esc_html($log->to_email); ?></td>
                            <td><?php echo esc_html($log->subject); ?></td>
                            <td>
                                <span class="status-<?php echo esc_attr($log->status); ?>">
                                    <?php echo esc_html($log->status); ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <span class="view">
                                        <a href="<?php echo esc_url(add_query_arg('log_id', $log->id)); ?>">
                                            <?php echo esc_html__('View', 'wp-mail-master'); ?>
                                        </a> |
                                    </span>
                                    <span class="delete">
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            admin_url('admin-post.php?action=delete_log&log_id=' . $log->id),
                                            'delete_log_' . $log->id
                                        )); ?>" class="submitdelete"
                                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this log?', 'wp-mail-master')); ?>');">
                                            <?php echo esc_html__('Delete', 'wp-mail-master'); ?>
                                        </a>
                                    </span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Display pagination
            echo '<div class="tablenav bottom">';
            echo '<div class="tablenav-pages">';
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => __('&laquo;'),
                'next_text' => __('&raquo;'),
                'total' => $total_pages,
                'current' => $current_page
            ));
            echo '</div>';
            echo '</div>';
            ?>
        </div>
        <?php
        echo ob_get_clean();
    }

    /**
     * Display single log entry
     */
    private function display_single_log($log_id)
    {
        global $wpdb;
        if (!$wpdb) {
            wp_die(__('WordPress database object not available.', 'wp-mail-master'));
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $log_id
        ));

        if (!$log) {
            wp_die(__('Log entry not found.', 'wp-mail-master'));
        }

        $back_url = remove_query_arg('log_id');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Email Log Details', 'wp-mail-master'); ?></h1>

            <p>
                <a href="<?php echo esc_url($back_url); ?>" class="button">
                    <?php echo esc_html__('← Back to Logs', 'wp-mail-master'); ?>
                </a>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html(date_i18n('F j, Y H:i:s', strtotime($log->time))); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('To', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html($log->to_email); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Subject', 'wp-mail-master'); ?></th>
                    <td><?php echo esc_html($log->subject); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Status', 'wp-mail-master'); ?></th>
                    <td>
                        <span class="status-<?php echo esc_attr($log->status); ?>">
                            <?php echo esc_html($log->status); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Message', 'wp-mail-master'); ?></th>
                    <td>
                        <div class="email-content">
                            <?php echo wp_kses_post($log->message); ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Handle log deletion
     */
    public function handle_log_deletion()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete logs.', 'wp-mail-master'));
        }

        $log_id = isset($_GET['log_id']) ? absint($_GET['log_id']) : 0;

        if (!$log_id) {
            wp_die(__('Invalid log ID.', 'wp-mail-master'));
        }

        // Verify nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_log_' . $log_id)) {
            wp_die(__('Security check failed.', 'wp-mail-master'));
        }

        global $wpdb;
        if (!$wpdb) {
            wp_die(__('WordPress database object not available.', 'wp-mail-master'));
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_logs';

        $result = $wpdb->delete(
            $table_name,
            array('id' => $log_id),
            array('%d')
        );

        if ($result === false) {
            wp_die(__('Failed to delete log.', 'wp-mail-master'));
        }

        // Clear cache for all pages as we don't know which page this log was on
        wp_cache_delete('wp_mail_master_logs_count');
        for ($i = 1; $i <= 100; $i++) { // Arbitrary limit of 100 pages
            wp_cache_delete('wp_mail_master_logs_' . $i);
        }

        // Redirect back to the logs page
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'wp-mail-master-email-logs',
                'deleted' => '1'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}