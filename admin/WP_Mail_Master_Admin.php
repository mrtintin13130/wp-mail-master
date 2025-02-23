<?php

// admin/WP_Mail_Master_Admin.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Admin
{
    private $plugin_name;
    private $version;
    private $settings_instance;
    private $templates_instance;
    private $logs_instance;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Initialize instances
        $this->settings_instance = new WP_Mail_Master_Settings($this->plugin_name, $this->version);
        $this->templates_instance = new WP_Mail_Master_Templates();
        $this->logs_instance = new WP_Mail_Master_Logs($this->plugin_name, $this->version, true);

        // Initialize AJAX handler
        new WP_Mail_Master_Ajax($this->plugin_name, $this->version);

        // Add menu items
        add_action('admin_menu', array($this, 'add_plugin_admin_menu'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WP_MAIL_MASTER_PLUGIN_FILE, array($this, 'add_settings_link'));

        // Register admin assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // Add notices handler
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Conditionally enqueue admin assets
     *
     * @param string $hook The current admin page hook
     */
    public function enqueue_assets($hook)
    {
        // Only load assets on plugin pages or dashboard (for widget)
        if (!$this->is_plugin_page($hook) && $hook !== 'index.php') {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'admin/css/wp-mail-master-admin.css',
            array(),
            $this->version,
            'all'
        );

        // Enqueue scripts
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'admin/js/wp-mail-master-admin.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localize script
        wp_localize_script(
            $this->plugin_name,
            'wpMailMasterAdmin',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wp_mail_master_nonce'),
                'i18n' => array(
                    'confirmDelete' => __('Are you sure you want to delete this item?', 'wp-mail-master'),
                    'error' => __('An error occurred. Please try again.', 'wp-mail-master'),
                    'success' => __('Operation completed successfully.', 'wp-mail-master')
                )
            )
        );
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook The current admin page hook
     * @return bool
     */
    private function is_plugin_page($hook)
    {
        $plugin_pages = array(
            'toplevel_page_' . $this->plugin_name,
            $this->plugin_name . '_page_wp-mail-master-logs',
            $this->plugin_name . '_page_wp-mail-master-templates'
        );

        return in_array($hook, $plugin_pages);
    }

    /**
     * Register the admin menu and submenus
     */
    public function add_plugin_admin_menu()
    {
        // Add main menu item
        add_menu_page(
            __('WP Mail Master Settings', 'wp-mail-master'),
            __('WP Mail Master', 'wp-mail-master'),
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page'),
            'dashicons-email-alt',
            85
        );

        // Add logs submenu
        add_submenu_page(
            $this->plugin_name,
            __('Logs', 'wp-mail-master'),
            __('Logs', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-logs',
            array($this->logs_instance, 'display_logs_page')
        );

        // Add templates submenu
        add_submenu_page(
            $this->plugin_name,
            __('Email Templates', 'wp-mail-master'),
            __('Email Templates', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-templates',
            array($this->templates_instance, 'render_templates_page')
        );
    }

    /**
     * Add settings link to plugin listing
     *
     * @param array $links Existing plugin links
     * @return array Modified plugin links
     */
    public function add_settings_link($links)
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=' . $this->plugin_name),
            __('Settings', 'wp-mail-master')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Display the main plugin settings page
     */
    public function display_plugin_setup_page()
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-mail-master'));
        }

        // Make settings instance available to the template
        $settings = $this->settings_instance;

        // Include the template
        include_once WP_MAIL_MASTER_PLUGIN_DIR . 'admin/views/settings.php';
    }

    /**
     * Display admin notices
     */
    public function admin_notices()
    {
        if (!$this->is_plugin_page(get_current_screen()->id)) {
            return;
        }

        // Display success message if settings were saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            $this->display_notice(
                __('Settings saved successfully.', 'wp-mail-master'),
                'success'
            );
        }

        // Display success message if test email was sent
        if (isset($_GET['test-email-sent']) && $_GET['test-email-sent']) {
            $this->display_notice(
                __('Test email sent successfully.', 'wp-mail-master'),
                'success'
            );
        }
    }

    /**
     * Display a notice
     *
     * @param string $message The notice message
     * @param string $type The notice type (success, error, warning, info)
     */
    private function display_notice($message, $type = 'info')
    {
        ?>
        <div class="notice notice-<?php echo esc_attr($type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
        <?php
    }
}
