<?php
// admin/WP_Mail_Master_Error_Logs.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Error_Logs
{
    private const PER_PAGE = 10;
    private const MAX_CACHE_TIME = 300; // 5 minutes

    public function __construct()
    {
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Display admin notices for actions
     */
    public function admin_notices()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'wp-mail-master-error-logs') {
            return;
        }

        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Error log entry deleted successfully.', 'wp-mail-master'); ?></p>
            </div>
            <?php
        }

        if (isset($_GET['cleared']) && $_GET['cleared'] === '1') {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('All error logs cleared successfully.', 'wp-mail-master'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Handle clearing all error logs
     */
    public function handle_clear_error_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to clear error logs.', 'wp-mail-master'));
        }

        // Verify nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'clear_error_logs')) {
            wp_die(__('Security check failed.', 'wp-mail-master'));
        }

        global $wpdb;
        if (!$wpdb) {
            wp_die(__('WordPress database object not available.', 'wp-mail-master'));
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_errors';
        $result = $wpdb->query("TRUNCATE TABLE $table_name");

        if ($result === false) {
            wp_die(__('Failed to clear error logs.', 'wp-mail-master'));
        }

        // Clear cache
        wp_cache_delete('wp_mail_master_error_logs_count');
        for ($i = 1; $i <= 100; $i++) {
            wp_cache_delete('wp_mail_master_error_logs_' . $i);
        }

        // Redirect back with success message
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'wp-mail-master-error-logs',
                'cleared' => '1'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    private function get_error_logs_data($page_number = 1, $search = '')
    {
        global $wpdb;
        if (!$wpdb) {
            return array(
                'errors' => array(),
                'total_items' => 0,
                'total_pages' => 0
            );
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_errors';
        
        // Generate cache key
        $cache_key = 'wp_mail_master_error_logs_' . $page_number . '_' . md5($search);
        
        // Try to get cached data
        $cached_data = wp_cache_get($cache_key);
        if (false !== $cached_data) {
            return $cached_data;
        }

        // Calculate offset
        $offset = ($page_number - 1) * self::PER_PAGE;

        $where = '';
        $params = [self::PER_PAGE, $offset];

        if (!empty($search)) {
            $where = "WHERE error_message LIKE %s OR error_details LIKE %s";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            array_push($params, $search_term, $search_term);
        }

        $query = "SELECT SQL_CALC_FOUND_ROWS *
            FROM $table_name $where
            ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $errors = $wpdb->get_results($wpdb->prepare($query, $params));
        $total_items = $wpdb->get_var("SELECT FOUND_ROWS()");

        $data = array(
            'errors' => $errors,
            'total_items' => $total_items,
            'total_pages' => ceil($total_items / self::PER_PAGE)
        );

        wp_cache_set($cache_key, $data, '', self::MAX_CACHE_TIME);

        return $data;
    }

    public function display_error_logs_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-mail-master'));
        }

        // Get current page number
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        // Get error logs data
        $data = $this->get_error_logs_data($current_page, $search);
        $errors = $data['errors'];
        $total_pages = $data['total_pages'];

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Error Logs', 'wp-mail-master'); ?></h1>

            <!-- Add clear logs button -->
            <a href="<?php echo esc_url(wp_nonce_url(
                admin_url('admin-post.php?action=clear_error_logs'),
                'clear_error_logs'
            )); ?>" class="page-title-action" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all error logs?', 'wp-mail-master')); ?>');">
                <?php echo esc_html__('Clear All Logs', 'wp-mail-master'); ?>
            </a>

            <!-- Add search form -->
            <form method="get" class="search-box" style="margin-bottom: 1em;">
                <input type="hidden" name="page" value="wp-mail-master-error-logs">
                <label class="screen-reader-text" for="error-log-search-input">
                    <?php echo esc_html__('Search Error Logs:', 'wp-mail-master'); ?>
                </label>
                <input type="search" id="error-log-search-input" name="s"
                    value="<?php echo esc_attr($search); ?>">
                <input type="submit" class="button" value="<?php echo esc_attr__('Search Error Logs', 'wp-mail-master'); ?>">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-time"><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-code"><?php echo esc_html__('Error Code', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-message"><?php echo esc_html__('Message', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-actions"><?php echo esc_html__('Actions', 'wp-mail-master'); ?></th>
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
                            <td>
                                <?php echo esc_html($error->error_message); ?>
                                <?php if (defined('WP_DEBUG') && WP_DEBUG && !empty($details)): ?>
                                    <button type="button" class="button-link toggle-details" 
                                            aria-expanded="false"
                                            data-error-id="<?php echo esc_attr($error->id); ?>">
                                        <?php echo esc_html__('Show Details', 'wp-mail-master'); ?>
                                    </button>
                                    <div id="error-details-<?php echo esc_attr($error->id); ?>" 
                                         class="error-details" style="display: none;">
                                        <pre><?php echo esc_html(print_r($details, true)); ?></pre>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <span class="delete">
                                        <a href="<?php echo esc_url(wp_nonce_url(
                                            admin_url('admin-post.php?action=delete_error_log&error_id=' . $error->id),
                                            'delete_error_log_' . $error->id
                                        )); ?>" class="submitdelete"
                                            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete this error log?', 'wp-mail-master')); ?>');">
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

        <style>
            .column-time { width: 15%; }
            .column-code { width: 10%; }
            .column-message { width: 60%; }
            .column-actions { width: 15%; }
            .error-details { 
                margin-top: 10px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 4px;
            }
            .error-details pre {
                margin: 0;
                white-space: pre-wrap;
            }
            .toggle-details {
                margin-left: 10px;
                color: #2271b1;
            }
            .toggle-details:hover {
                color: #135e96;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.toggle-details').click(function() {
                var $button = $(this);
                var errorId = $button.data('error-id');
                var $details = $('#error-details-' + errorId);
                
                if ($details.is(':visible')) {
                    $details.slideUp();
                    $button.text('<?php echo esc_js(__('Show Details', 'wp-mail-master')); ?>');
                    $button.attr('aria-expanded', 'false');
                } else {
                    $details.slideDown();
                    $button.text('<?php echo esc_js(__('Hide Details', 'wp-mail-master')); ?>');
                    $button.attr('aria-expanded', 'true');
                }
            });
        });
        </script>
        <?php
        echo ob_get_clean();
    }

    public function handle_error_log_deletion()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to delete error logs.', 'wp-mail-master'));
        }

        $error_id = isset($_GET['error_id']) ? absint($_GET['error_id']) : 0;

        if (!$error_id) {
            wp_die(__('Invalid error log ID.', 'wp-mail-master'));
        }

        // Verify nonce
        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'delete_error_log_' . $error_id)) {
            wp_die(__('Security check failed.', 'wp-mail-master'));
        }

        global $wpdb;
        if (!$wpdb) {
            wp_die(__('WordPress database object not available.', 'wp-mail-master'));
        }

        $table_name = $wpdb->prefix . 'wp_mail_master_errors';

        $result = $wpdb->delete(
            $table_name,
            array('id' => $error_id),
            array('%d')
        );

        if ($result === false) {
            wp_die(__('Failed to delete error log.', 'wp-mail-master'));
        }

        // Clear cache
        wp_cache_delete('wp_mail_master_error_logs_count');
        for ($i = 1; $i <= 100; $i++) {
            wp_cache_delete('wp_mail_master_error_logs_' . $i);
        }

        // Redirect back to the error logs page
        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'wp-mail-master-error-logs',
                'deleted' => '1'
            ),
            admin_url('admin.php')
        ));
        exit;
    }
}