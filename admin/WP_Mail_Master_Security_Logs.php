<?php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Security_Logs {
    private $plugin_name;
    private $version;
    private $per_page = 20;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        add_action('admin_menu', array($this, 'add_security_logs_menu'));
    }

    public function add_security_logs_menu() {
        add_submenu_page(
            'wp-mail-master',
            __('Security Logs', 'wp-mail-master'),
            __('Security Logs', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-security-logs',
            array($this, 'render_security_logs_page')
        );
    }

    public function render_security_logs_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle bulk actions
        if (isset($_POST['action']) && $_POST['action'] === 'clear_logs' && 
            check_admin_referer('wp_mail_master_security_logs')) {
            $this->clear_security_logs();
            add_settings_error(
                'wp_mail_master_messages',
                'logs_cleared',
                __('Security logs cleared successfully.', 'wp-mail-master'),
                'updated'
            );
        }

        // Get current page and order parameters
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'time';
        $order = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'DESC';

        // Get logs
        $logs = $this->get_security_logs($paged, $orderby, $order);
        $total_items = $this->get_total_logs();
        $total_pages = ceil($total_items / $this->per_page);

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('wp_mail_master_messages'); ?>

            <div class="tablenav top">
                <form method="post" action="">
                    <?php wp_nonce_field('wp_mail_master_security_logs'); ?>
                    <input type="hidden" name="action" value="clear_logs">
                    <input type="submit" class="button action" value="<?php _e('Clear All Logs', 'wp-mail-master'); ?>"
                           onclick="return confirm('<?php _e('Are you sure you want to clear all security logs?', 'wp-mail-master'); ?>');">
                </form>

                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">
                            <?php $this->column_header('time', __('Time', 'wp-mail-master')); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php $this->column_header('event_type', __('Event Type', 'wp-mail-master')); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php $this->column_header('check_type', __('Check Type', 'wp-mail-master')); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php $this->column_header('identifier', __('Identifier', 'wp-mail-master')); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Details', 'wp-mail-master'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php $this->column_header('ip_address', __('IP Address', 'wp-mail-master')); ?>
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6"><?php _e('No security logs found.', 'wp-mail-master'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log->time); ?></td>
                                <td><?php echo esc_html($this->get_event_type_label($log->event_type)); ?></td>
                                <td><?php echo esc_html($this->get_check_type_label($log->check_type)); ?></td>
                                <td><?php echo esc_html($log->identifier); ?></td>
                                <td><?php echo $this->format_details($log->details); ?></td>
                                <td><?php echo esc_html($log->ip_address); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;'),
                        'next_text' => __('&raquo;'),
                        'total' => $total_pages,
                        'current' => $paged
                    ));
                    ?>
                </div>
            </div>
        </div>

        <style>
            .column-time { width: 15%; }
            .column-event_type { width: 10%; }
            .column-check_type { width: 10%; }
            .column-identifier { width: 20%; }
            .column-details { width: 30%; }
            .column-ip_address { width: 15%; }
            .details-list { margin: 0; }
            .details-list li { margin-bottom: 5px; }
        </style>
        <?php
    }

    private function get_security_logs($paged = 1, $orderby = 'time', $order = 'DESC') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wp_mail_master_security_logs';
        $offset = ($paged - 1) * $this->per_page;
        
        // Sanitize order and orderby
        $allowed_orderby = ['time', 'event_type', 'check_type', 'identifier', 'ip_address'];
        $orderby = in_array($orderby, $allowed_orderby) ? $orderby : 'time';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY $orderby $order LIMIT %d OFFSET %d",
            $this->per_page,
            $offset
        );
        
        return $wpdb->get_results($sql);
    }

    private function get_total_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_mail_master_security_logs';
        return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }

    private function clear_security_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_mail_master_security_logs';
        $wpdb->query("TRUNCATE TABLE $table_name");
    }

    private function column_header($column, $title) {
        $orderby = isset($_GET['orderby']) ? $_GET['orderby'] : 'time';
        $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
        $new_order = ($orderby === $column && $order === 'desc') ? 'asc' : 'desc';
        
        $url = add_query_arg(array(
            'orderby' => $column,
            'order' => $new_order
        ));
        
        $current = $orderby === $column ? " sorted $order" : '';
        echo '<a href="' . esc_url($url) . '" class="sortable' . $current . '">';
        echo '<span>' . esc_html($title) . '</span>';
        echo '<span class="sorting-indicator"></span>';
        echo '</a>';
    }

    private function get_event_type_label($event_type) {
        $labels = [
            'rate_limit' => __('Rate Limit', 'wp-mail-master')
        ];
        return isset($labels[$event_type]) ? $labels[$event_type] : $event_type;
    }

    private function get_check_type_label($check_type) {
        $labels = [
            'ip' => __('IP Address', 'wp-mail-master'),
            'sender' => __('Sender Email', 'wp-mail-master'),
            'recipient' => __('Recipient Email', 'wp-mail-master')
        ];
        return isset($labels[$check_type]) ? $labels[$check_type] : $check_type;
    }

    private function format_details($details_json) {
        $details = json_decode($details_json, true);
        if (!is_array($details)) return '';

        $output = '<ul class="details-list">';
        foreach ($details as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            if ($key === 'remaining_time') {
                $value = human_time_diff(0, $value);
            }
            $output .= sprintf('<li><strong>%s:</strong> %s</li>', esc_html($label), esc_html($value));
        }
        $output .= '</ul>';

        return $output;
    }
} 