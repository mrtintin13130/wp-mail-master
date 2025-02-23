<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Logs {
    private const PER_PAGE = 20;
    private const DEFAULT_RETENTION_DAYS = 30;

    private $plugin_name;
    private $version;
    private $db;
    private $is_main_instance;

    public function __construct($plugin_name, $version, $is_main_instance = false) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = new WP_Mail_Master_DB();
        $this->is_main_instance = $is_main_instance;

        add_action('admin_post_clear_logs', array($this, 'handle_clear_logs'));
        add_action('wp_mail_master_cleanup_logs', array($this, 'cleanup_old_logs'));

        // Add submenu for individual log view only if this is the main instance
        if ($this->is_main_instance) {
            add_action('admin_menu', array($this, 'add_hidden_menu'));
        }

        // Schedule cleanup if not already scheduled
        if (!wp_next_scheduled('wp_mail_master_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'wp_mail_master_cleanup_logs');
        }
    }

    public function add_hidden_menu() {
        // Hidden menu item for log details
        add_submenu_page(
            null, // No parent
            __('Log Details', 'wp-mail-master'),
            __('Log Details', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-log-details',
            array($this, 'view_log_details')
        );
    }

    public function view_log_details() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-mail-master'));
        }

        $log_id = isset($_GET['log_id']) ? intval($_GET['log_id']) : 0;
        if (!$log_id) {
            wp_die(esc_html__('Invalid log ID.', 'wp-mail-master'));
        }

        $log = $this->db->get_log_by_id($log_id);
        if (!$log) {
            wp_die(esc_html__('Log entry not found.', 'wp-mail-master'));
        }

        $metadata = json_decode($log->metadata, true);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Email Log Details', 'wp-mail-master'); ?></h1>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-master-logs')); ?>" class="button">
                    <?php esc_html_e('← Back to Logs', 'wp-mail-master'); ?>
                </a>
            </p>

            <div class="log-details-container">
                <div class="log-header">
                    <div class="log-meta">
                        <p><strong><?php esc_html_e('Date/Time:', 'wp-mail-master'); ?></strong> <?php echo esc_html($log->created_at); ?></p>
                        <p><strong><?php esc_html_e('Status:', 'wp-mail-master'); ?></strong> 
                            <span class="log-status-badge status-<?php echo esc_attr(strtolower($log->status)); ?>">
                                <?php 
                                    $status_labels = [
                                        'success' => __('Sent', 'wp-mail-master'),
                                        'error' => __('Failed', 'wp-mail-master'),
                                        'blocked' => __('Blocked (Spam)', 'wp-mail-master'),
                                        'warning' => __('Warning', 'wp-mail-master'),
                                        'info' => __('Info', 'wp-mail-master')
                                    ];
                                    echo esc_html(isset($status_labels[$log->status]) ? $status_labels[$log->status] : ucfirst($log->status));
                                ?>
                            </span>
                        </p>
                        <?php if ($metadata): ?>
                            <?php if (isset($metadata['to_email'])): ?>
                                <p><strong><?php esc_html_e('To:', 'wp-mail-master'); ?></strong> <?php echo esc_html($metadata['to_email']); ?></p>
                            <?php endif; ?>
                            <?php if (isset($metadata['subject'])): ?>
                                <p><strong><?php esc_html_e('Subject:', 'wp-mail-master'); ?></strong> <?php echo esc_html($metadata['subject']); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($metadata['error_info'])): ?>
                        <div class="error-info <?php echo strpos($metadata['error_info'], 'Spam protection:') !== false ? 'spam-info' : ''; ?>">
                            <h3>
                                <?php 
                                    if (strpos($metadata['error_info'], 'Spam protection:') !== false) {
                                        esc_html_e('Spam Detection Information', 'wp-mail-master');
                                    } else {
                                        esc_html_e('Error Information', 'wp-mail-master');
                                    }
                                ?>
                            </h3>
                            <pre><?php 
                                $error_info = $metadata['error_info'];
                                if (strpos($error_info, 'Spam protection:') === 0) {
                                    $error_info = substr($error_info, strlen('Spam protection:'));
                                }
                                echo esc_html(trim($error_info)); 
                            ?></pre>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($metadata['full_message'])): ?>
                    <div class="email-content-tabs">
                        <ul class="nav-tab-wrapper">
                            <li><a href="#rendered" class="nav-tab nav-tab-active"><?php esc_html_e('Rendered Email', 'wp-mail-master'); ?></a></li>
                            <li><a href="#source" class="nav-tab"><?php esc_html_e('Source Code', 'wp-mail-master'); ?></a></li>
                        </ul>

                        <div id="rendered" class="tab-content active">
                            <iframe id="email-preview" srcdoc="<?php echo esc_attr($metadata['full_message']); ?>" style="width: 100%; height: 500px; border: 1px solid #ddd;"></iframe>
                        </div>
                        <div id="source" class="tab-content" style="display: none;">
                            <pre><?php echo esc_html($metadata['full_message']); ?></pre>
                        </div>
                    </div>

                    <script>
                        jQuery(document).ready(function($) {
                            $('.nav-tab').on('click', function(e) {
                                e.preventDefault();
                                var targetId = $(this).attr('href').substring(1);
                                
                                // Update tabs
                                $('.nav-tab').removeClass('nav-tab-active');
                                $(this).addClass('nav-tab-active');
                                
                                // Update content
                                $('.tab-content').hide();
                                $('#' + targetId).show();
                            });
                        });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function log($type, $level, $status, $message, $details = null, $metadata = null) {
        // Validate status
        $valid_statuses = ['success', 'error', 'blocked', 'info'];
        $status = in_array($status, $valid_statuses) ? $status : 'info';

        // Validate level
        $valid_levels = ['debug', 'info', 'warning', 'error'];
        $level = in_array($level, $valid_levels) ? $level : 'info';

        return $this->db->insert_log($type, $level, $status, $message, $details, $metadata);
    }

    public function cleanup_old_logs() {
        $this->db->cleanup_old_logs();
    }

    private function get_logs_data($page_number = 1, $search = '', $type = 'all', $status = 'all', $level = 'all') {
        return $this->db->get_logs($page_number, self::PER_PAGE, [
            'search' => $search,
            'type' => $type,
            'status' => $status,
            'level' => $level
        ]);
    }

    public function handle_clear_logs() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to clear logs.', 'wp-mail-master'));
        }

        if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce($_REQUEST['_wpnonce'], 'clear_logs')) {
            wp_die(__('Security check failed.', 'wp-mail-master'));
        }

        $this->db->delete_tables();
        $this->db->create_tables();

        wp_safe_redirect(add_query_arg(
            array(
                'page' => 'wp-mail-master-logs',
                'cleared' => '1'
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    public function display_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-mail-master'));
        }

        // Get filters
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'all';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : 'all';

        // Get logs data
        $data = $this->get_logs_data($current_page, $search, $type, $status, $level);
        $logs = $data['logs'];
        $total_pages = $data['total_pages'];

        // Start output buffering
        ob_start();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html__('Logs', 'wp-mail-master'); ?></h1>

            <a href="<?php echo esc_url(wp_nonce_url(
                admin_url('admin-post.php?action=clear_logs'),
                'clear_logs'
            )); ?>" class="page-title-action" onclick="return confirm('<?php echo esc_js(__('Are you sure you want to clear all logs?', 'wp-mail-master')); ?>');">
                <?php echo esc_html__('Clear All Logs', 'wp-mail-master'); ?>
            </a>

            <form method="get" class="search-box" style="margin: 1em 0;">
                <input type="hidden" name="page" value="wp-mail-master-logs">
                
                <select name="type" style="margin-right: 10px;">
                    <option value="all" <?php selected($type, 'all'); ?>><?php esc_html_e('All Types', 'wp-mail-master'); ?></option>
                    <option value="email" <?php selected($type, 'email'); ?>><?php esc_html_e('Email', 'wp-mail-master'); ?></option>
                    <option value="error" <?php selected($type, 'error'); ?>><?php esc_html_e('Error', 'wp-mail-master'); ?></option>
                    <option value="security" <?php selected($type, 'security'); ?>><?php esc_html_e('Security', 'wp-mail-master'); ?></option>
                </select>

                <select name="status" style="margin-right: 10px;">
                    <option value="all" <?php selected($status, 'all'); ?>><?php esc_html_e('All Statuses', 'wp-mail-master'); ?></option>
                    <option value="success" <?php selected($status, 'success'); ?>><?php esc_html_e('Sent', 'wp-mail-master'); ?></option>
                    <option value="error" <?php selected($status, 'error'); ?>><?php esc_html_e('Failed', 'wp-mail-master'); ?></option>
                    <option value="blocked" <?php selected($status, 'blocked'); ?>><?php esc_html_e('Blocked (Spam)', 'wp-mail-master'); ?></option>
                    <option value="info" <?php selected($status, 'info'); ?>><?php esc_html_e('Info', 'wp-mail-master'); ?></option>
                </select>

                <select name="level" style="margin-right: 10px;">
                    <option value="all" <?php selected($level, 'all'); ?>><?php esc_html_e('All Levels', 'wp-mail-master'); ?></option>
                    <option value="debug" <?php selected($level, 'debug'); ?>><?php esc_html_e('Debug', 'wp-mail-master'); ?></option>
                    <option value="info" <?php selected($level, 'info'); ?>><?php esc_html_e('Info', 'wp-mail-master'); ?></option>
                    <option value="warning" <?php selected($level, 'warning'); ?>><?php esc_html_e('Warning', 'wp-mail-master'); ?></option>
                    <option value="error" <?php selected($level, 'error'); ?>><?php esc_html_e('Error', 'wp-mail-master'); ?></option>
                </select>

                <label class="screen-reader-text" for="log-search-input">
                    <?php esc_html_e('Search Logs:', 'wp-mail-master'); ?>
                </label>
                <input type="search" id="log-search-input" name="s"
                    value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search logs...', 'wp-mail-master'); ?>">
                <input type="submit" class="button" value="<?php esc_attr_e('Filter Logs', 'wp-mail-master'); ?>">
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-time"><?php echo esc_html__('Time', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-type"><?php echo esc_html__('Type', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-level"><?php echo esc_html__('Level', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-message"><?php echo esc_html__('Message', 'wp-mail-master'); ?></th>
                        <th scope="col" class="column-status"><?php echo esc_html__('Status', 'wp-mail-master'); ?></th>
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
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                            <td>
                                <span class="log-type-badge <?php echo esc_attr($log->type); ?>">
                                    <?php echo esc_html(ucfirst($log->type)); ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-level-badge <?php echo esc_attr($log->level); ?>">
                                    <?php echo esc_html(ucfirst($log->level)); ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $metadata = json_decode($log->metadata, true);
                                    if ($metadata && isset($metadata['subject'])) {
                                        echo '<strong>' . esc_html__('Subject:', 'wp-mail-master') . '</strong> ' . esc_html($metadata['subject']) . '<br>';
                                    }
                                    if ($metadata && isset($metadata['to_email'])) {
                                        echo '<strong>' . esc_html__('To:', 'wp-mail-master') . '</strong> ' . esc_html($metadata['to_email']) . '<br>';
                                    }
                                    
                                    // Add spam indicator if applicable
                                    if ($log->status === 'blocked' && !empty($metadata['error_info'])) {
                                        echo '<div class="spam-indicator">';
                                        echo '<span class="dashicons dashicons-shield"></span> ';
                                        $error_info = $metadata['error_info'];
                                        if (strpos($error_info, 'Spam protection:') === 0) {
                                            $error_info = substr($error_info, strlen('Spam protection:'));
                                        }
                                        echo esc_html(trim($error_info));
                                        echo '</div>';
                                    }
                                    
                                    echo '<div class="message-summary">' . esc_html($log->message) . '</div>';
                                ?>
                                <?php if (!empty($log->details) || !empty($log->metadata)): ?>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="<?php echo esc_url(admin_url('admin.php?page=wp-mail-master-log-details&log_id=' . $log->id)); ?>">
                                                <?php esc_html_e('View Details', 'wp-mail-master'); ?>
                                            </a>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="log-status-badge status-<?php echo esc_attr($log->status); ?>">
                                    <?php 
                                        $status_labels = [
                                            'success' => __('Sent', 'wp-mail-master'),
                                            'error' => __('Failed', 'wp-mail-master'),
                                            'blocked' => __('Blocked (Spam)', 'wp-mail-master'),
                                            'warning' => __('Warning', 'wp-mail-master'),
                                            'info' => __('Info', 'wp-mail-master')
                                        ];
                                        echo esc_html(isset($status_labels[$log->status]) ? $status_labels[$log->status] : ucfirst($log->status));
                                    ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
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
            .column-type { width: 10%; }
            .column-level { width: 10%; }
            .column-message { width: 50%; }
            .column-status { width: 15%; }

            .log-type-badge,
            .log-level-badge,
            .log-status-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 500;
            }

            /* Type badges */
            .log-type-badge.email {
                background-color: #e3f2fd;
                color: #1976d2;
            }
            .log-type-badge.error {
                background-color: #ffebee;
                color: #d32f2f;
            }
            .log-type-badge.security {
                background-color: #e8f5e9;
                color: #388e3c;
            }

            /* Level badges */
            .log-level-badge.debug {
                background-color: #f5f5f5;
                color: #616161;
            }
            .log-level-badge.info {
                background-color: #e3f2fd;
                color: #1976d2;
            }
            .log-level-badge.warning {
                background-color: #fff3e0;
                color: #f57c00;
            }
            .log-level-badge.error {
                background-color: #ffebee;
                color: #d32f2f;
            }

            /* Status badges */
            .log-status-badge.status-success {
                background-color: #e8f5e9;
                color: #388e3c;
            }
            .log-status-badge.status-error {
                background-color: #ffebee;
                color: #d32f2f;
            }
            .log-status-badge.status-blocked {
                background-color: #fff8e1;
                color: #ff6f00;
                border: 1px solid #ffd54f;
            }
            .log-status-badge.status-info {
                background-color: #e3f2fd;
                color: #1976d2;
            }

            .message-summary {
                margin: 8px 0;
                color: #666;
                font-style: italic;
            }

            .log-details {
                margin-top: 10px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .log-details .error-info {
                margin-bottom: 15px;
            }

            .log-details .error-info pre {
                background: #fff1f0;
                border-left: 3px solid #d32f2f;
                padding: 10px;
            }

            .log-details .full-message {
                margin-top: 15px;
            }

            .log-details .full-message pre {
                background: #fff;
                border: 1px solid #eee;
                padding: 10px;
                max-height: 300px;
                overflow-y: auto;
            }

            .log-details h4 {
                margin: 0 0 10px;
                color: #23282d;
            }

            .toggle-details {
                margin-left: 10px;
                color: #2271b1;
                text-decoration: none;
            }

            .toggle-details:hover {
                color: #135e96;
            }

            .search-box {
                display: flex;
                align-items: center;
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            $('.toggle-details').click(function() {
                var $button = $(this);
                var logId = $button.data('log-id');
                var $details = $('#log-details-' + logId);
                
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
} 