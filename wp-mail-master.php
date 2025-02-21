<?php
/**
 * Plugin Name: WP Mail Master
 * Plugin URI: https://example.com/wp-mail-master
 * Description: A fully featured email plugin for WordPress with API and SMTP support, email logging, and more.
 * Version: 1.1.0
 * Author: Martho
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Text Domain: wp-mail-master
 * Domain Path: /languages
 */

namespace Martho\WpMailMaster;

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('WP_MAIL_MASTER_VERSION', '1.0.0');
define('WP_MAIL_MASTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_MAIL_MASTER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_MAIL_MASTER_PLUGIN_FILE', __FILE__);

// Include the Composer autoloader
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

/**
 * The core plugin class
 */
require_once WP_MAIL_MASTER_PLUGIN_DIR . 'includes/WP_Mail_Master.php';

// Include the dashboard widget class
require_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/WP_Mail_Master_Dashboard_Widget.php';

// Function to delete the table
function wp_mail_master_delete_table()
{
    global $wpdb;

    if ($wpdb instanceof \wpdb) {
        $table_name = $wpdb->prefix . 'wp_mail_master_logs';
        $sql = "DROP TABLE IF EXISTS $table_name;";
        $wpdb->query($sql);
    }
}

// Register the activation hook
register_activation_hook(__FILE__, function () {
    // Create/upgrade database tables
    $db = new WP_Mail_Master_DB();
    $db->create_tables();
    
    // Create queue table if you're using it
    if (class_exists('WP_Mail_Master_Queue')) {
        WP_Mail_Master_Queue::create_queue_table();
    }
});

// Register the deactivation hook
register_deactivation_hook(__FILE__, function() {
    $db = new WP_Mail_Master_DB();
    $db->delete_tables();
});


// Schedule cleanup
if (!wp_next_scheduled('wp_mail_master_cleanup')) {
    wp_schedule_event(time(), 'daily', 'wp_mail_master_cleanup');
}

add_action('wp_mail_master_cleanup', function () {
    $db = new WP_Mail_Master_DB();
    $db->cleanup_old_logs();
});

// Initialize the dashboard widget
function wp_mail_master_init_dashboard_widget()
{
    $widget = new WP_Mail_Master_Dashboard_Widget();
}
add_action('plugins_loaded', __NAMESPACE__ . '\\wp_mail_master_init_dashboard_widget');

/**
 * Begins execution of the plugin.
 */
function run_wp_mail_master()
{
    $plugin = new WP_Mail_Master();
    $plugin->run();
}
run_wp_mail_master();