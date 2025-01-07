<?php

// admin/WP_Mail_Master_Admin.php

namespace Martho\WpMailMaster;

class WP_Mail_Master_Admin
{
    private $plugin_name;
    private $version;
    private $logs_instance;
    private $settings_instance;
    private $templates_instance;

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
        $this->logs_instance = new WP_Mail_Master_Logs();
        $this->settings_instance = new WP_Mail_Master_Settings($this->plugin_name, $this->version);
        $this->templates_instance = new WP_Mail_Master_Templates();

        // Initialize AJAX handler
        new WP_Mail_Master_Ajax();

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

        // Enqueue main CSS file
        wp_enqueue_style(
            $this->plugin_name,
            $this->get_asset_url('css/wp-mail-master-admin.css'),
            array(),
            $this->version,
            'all'
        );

        // Only load Chart.js on dashboard
        if ($hook === 'index.php') {
            wp_enqueue_script(
                'chart-js',
                'https://cdn.jsdelivr.net/npm/chart.js',
                array(),
                $this->version,
                true
            );
            wp_enqueue_script(
                'chartjs-adapter-date-fns',
                'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns',
                array('chart-js'),
                $this->version,
                true
            );
        }

        // Load wp-mail-master-admin.js on the settings page
        if ($hook === 'toplevel_page_' . $this->plugin_name) {
            wp_enqueue_script(
                $this->plugin_name . '-admin',
                $this->get_asset_url('js/wp-mail-master-admin.js'),
                array('jquery'),
                $this->version,
                true
            );

            wp_localize_script(
                $this->plugin_name . '-admin',
                'wp_mail_master',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('send_test_email_nonce'),
                    'i18n' => array(
                        'confirmDelete' => __('Are you sure you want to delete this?', 'wp-mail-master'),
                        'testEmailSuccess' => __('Test email sent successfully.', 'wp-mail-master'),
                        'testEmailError' => __('Failed to send test email.', 'wp-mail-master')
                    )
                )
            );
        }

        // Load templates.js only on the templates page
        if ($hook === $this->plugin_name . '_page_wp-mail-master-templates') {
            // Load required WordPress scripts
            wp_enqueue_media();
            wp_enqueue_editor();
            wp_enqueue_style('wp-color-picker');

            wp_enqueue_script(
                $this->plugin_name . '-templates',
                $this->get_asset_url('js/templates.js'),
                array('jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-editor'),
                $this->version,
                true
            );

            wp_localize_script(
                $this->plugin_name . '-templates',
                'wpMailMasterTemplates',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('template_nonce'),
                    'site_name' => get_bloginfo('name'),
                    'site_url' => get_bloginfo('url'),
                    'available_vars' => $this->templates_instance->get_available_variables(),
                    'i18n' => array(
                        'enterTestEmail' => __('Enter email address for test:', 'wp-mail-master'),
                        'testEmailSent' => __('Test email sent successfully!', 'wp-mail-master'),
                        'testEmailError' => __('Failed to send test email.', 'wp-mail-master'),
                        'confirmDelete' => __('Are you sure you want to delete this template?', 'wp-mail-master'),
                        'selectImage' => __('Select Image', 'wp-mail-master'),
                        'insertImage' => __('Insert Image', 'wp-mail-master'),
                        'deleteError' => __('Failed to delete template.', 'wp-mail-master')
                    )
                )
            );
        }
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

        // Add submenu for email logs
        add_submenu_page(
            $this->plugin_name,
            __('Email Logs', 'wp-mail-master'),
            __('Email Logs', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-email-logs',
            array($this->logs_instance, 'display_email_logs_page')
        );

        // Add Error Logs submenu
        add_submenu_page(
            $this->plugin_name,
            __('Error Logs', 'wp-mail-master'),
            __('Error Logs', 'wp-mail-master'),
            'manage_options',
            'wp-mail-master-error-logs',
            array(new WP_Mail_Master_Error_Logs(), 'display_error_logs_page')
        );

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

        // Display notice if logs were deleted
        if (isset($_GET['deleted']) && $_GET['deleted']) {
            $this->display_notice(
                __('Log entry deleted successfully.', 'wp-mail-master'),
                'success'
            );
        }
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook The current admin page hook
     * @return boolean
     */
    private function is_plugin_page($hook)
    {
        $plugin_pages = array(
            'toplevel_page_' . $this->plugin_name,
            $this->plugin_name . '_page_wp-mail-master-email-logs'
        );

        return in_array($hook, $plugin_pages);
    }

    /**
     * Get the URL for an asset file
     *
     * @param string $file The asset file path
     * @return string The full URL to the asset
     */
    private function get_asset_url($file)
    {
        // Check if debug mode is enabled
        if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {
            $file = str_replace('.min', '', $file);
        }

        return WP_MAIL_MASTER_PLUGIN_URL . 'admin/' . $file;
    }

    /**
     * Display an admin notice
     *
     * @param string $message The notice message
     * @param string $type The notice type (success, error, warning, info)
     */
    private function display_notice($message, $type = 'success')
    {
        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }
}
